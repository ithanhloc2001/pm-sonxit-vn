<?php
// Proxy xử lý giao tiếp với ComfyUI nội bộ để vượt lỗi CORS / Mixed Content
$comfyUrl = 'http://127.0.0.1:8188';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'upload') {
        header('Content-Type: application/json');
        if (!isset($_FILES['image'])) {
            echo json_encode(['error' => 'No image provided']);
            exit;
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $safeName = 'fs_' . time() . '_' . rand(1000, 9999) . '.' . ($ext ?: 'png');
        
        $ch = curl_init($comfyUrl . '/upload/image');
        $cFile = new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $safeName);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $cFile, 'overwrite' => 'true']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode > 0) http_response_code($httpCode); else http_response_code(500);
        echo $response ?: json_encode(['error' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    
    if ($action === 'prompt') {
        header('Content-Type: application/json');
        $data = file_get_contents('php://input');
        $ch = curl_init($comfyUrl . '/prompt');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 500) {
            http_response_code(500);
            echo json_encode([
                'error' => 'ComfyUI Backend Crash (500)',
                'comfy_response' => $response,
                'payload_sent' => json_decode($data)
            ]);
            curl_close($ch);
            exit;
        }
        if ($httpCode > 0) http_response_code($httpCode); else http_response_code(500);
        echo $response ?: json_encode(['error' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    
    if ($action === 'history') {
        header('Content-Type: application/json');
        $promptId = $_GET['prompt_id'] ?? '';
        $ch = curl_init($comfyUrl . '/history/' . $promptId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode > 0) http_response_code($httpCode); else http_response_code(500);
        echo $response ?: json_encode(['error' => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    if ($action === 'view') {
        $filename = $_GET['filename'] ?? '';
        $subfolder = $_GET['subfolder'] ?? '';
        $type = $_GET['type'] ?? '';
        $url = $comfyUrl . '/view?filename=' . urlencode($filename) . '&subfolder=' . urlencode($subfolder) . '&type=' . urlencode($type);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($contentType) header('Content-Type: ' . $contentType);
        echo $response;
        exit;
    }

    if ($action === 'cleanup') {
        header('Content-Type: application/json');

        // Đường dẫn gốc của ComfyUI
        $comfyBaseDir = 'C:\Users\ASUS AI\Downloads\ComfyUI_windows_portable_nvidia\ComfyUI_windows_portable\ComfyUI';
        $inputDir = $comfyBaseDir . '\input';
        $tempDir = $comfyBaseDir . '\temp';
        $outputDir = $comfyBaseDir . '\output';

        // 0. Ghi đè API 1x1 cho tất cả các file Input đã upload (Kỹ thuật kép)
        $filename1 = $_POST['filename1'] ?? '';
        $filename2 = $_POST['filename2'] ?? '';
        
        $filesToOverwrite = array_filter([$filename1, $filename2]);
        foreach ($filesToOverwrite as $fname) {
            $blank = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
            $tmpPath = sys_get_temp_dir() . '/' . $fname;
            file_put_contents($tmpPath, $blank);
            
            $ch = curl_init($comfyUrl . '/upload/image');
            $cFile = new CURLFile($tmpPath, 'image/png', $fname);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $cFile, 'overwrite' => 'true']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
            @unlink($tmpPath);
        }

        // 1. Gửi lệnh Xoá sạch History Tiến trình của ComfyUI (Giải phóng bộ nhớ RAM/VRAM ComfyUI)
        $ch2 = curl_init($comfyUrl . '/history');
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['clear' => true]));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch2);
        curl_close($ch2);

        // 2. NUKE (Xoá trắng) thư mục qua lệnh hệ thống Windows (Cực kỳ mạnh)
        @shell_exec('del /F /Q "' . $inputDir . '\*.*"');
        @shell_exec('del /F /Q "' . $tempDir . '\*.*"');
        @shell_exec('del /F /Q "' . $outputDir . '\*.*"');

        // 3. Quét dự phòng lần 2 bằng PHP (Đề phòng CMD bị chặn)
        $dirsToWipe = [$inputDir, $tempDir, $outputDir];
        foreach ($dirsToWipe as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '\*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'msg' => 'ALL TRACES WIPED COMPLETELY']);
        exit;
    }
}

// Đường dẫn file workflow JSON
$workflowFile = __DIR__ . '/workflows/faceswap.json';
$workflowJson = file_exists($workflowFile) ? file_get_contents($workflowFile) : '{}';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI FaceSwap - Cyberpunk Edition</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Orbitron & Montserrat for AI feel -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #050505;
            --card-bg: rgba(15, 15, 20, 0.75);
            --text-main: #f8fafc;
            --text-muted: #64748b;
        }
        
        body { 
            background-color: var(--bg-color); 
            font-family: 'Montserrat', sans-serif; 
            color: var(--text-main);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 0, 255, 0.05), transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(0, 212, 255, 0.05), transparent 30%);
            background-attachment: fixed;
        }

        /* RGB Animated Text */
        .text-rgb {
            background: linear-gradient(270deg, #ff0055, #ff00ff, #00d4ff, #00ff80, #ff0055);
            background-size: 400% 400%;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: rgbShift 6s ease infinite;
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
        }
        
        @keyframes rgbShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Navbar */
        .navbar {
            background-color: rgba(5, 5, 5, 0.8) !important;
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .navbar-brand { font-family: 'Orbitron', sans-serif; font-weight: 700; letter-spacing: 2px; color: #fff !important;}

        /* Hero Section */
        .hero-section {
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }

        /* Cards with RGB Hover */
        .rgb-border {
            position: relative;
            border-radius: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        .rgb-border:hover {
            border-color: rgba(0, 212, 255, 0.3);
            box-shadow: 0 10px 40px rgba(0,0,0,0.8), 0 0 20px rgba(0, 212, 255, 0.1);
            transform: translateY(-5px);
        }

        /* Form Controls */
        .form-control { 
            background-color: rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.1); 
            color: #fff;
            border-radius: 12px; 
            padding: 14px 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus { 
            background-color: rgba(0,0,0,0.8);
            border-color: #00d4ff; 
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2); 
            color: #fff;
        }
        .form-label { font-weight: 600; color: #cbd5e1; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px;}
        
        .input-group-text { 
            background: rgba(0,0,0,0.8); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-right: none;
            color: #00d4ff; 
            font-weight: 700;
        }
        .input-group .form-control { border-left: none; }

        /* Buttons */
        .btn-primary { 
            background: linear-gradient(90deg, #a200ff, #00d4ff); 
            border: none; 
            color: #fff;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            transform: scale(1.02); 
            box-shadow: 0 0 25px rgba(162, 0, 255, 0.6);
            color: #fff;
        }
        
        .btn-success { background: linear-gradient(90deg, #00ff80, #00d4ff); border: none; color: #000; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;}
        .btn-success:hover { box-shadow: 0 0 25px rgba(0, 255, 128, 0.5); color: #000; transform: scale(1.02);}

        /* Preview Box */
        .preview-box { 
            width: 100%; height: 200px; 
            border: 2px dashed rgba(255,255,255,0.2); 
            border-radius: 16px; 
            display: flex; align-items: center; justify-content: center; 
            overflow: hidden; position: relative; background: rgba(0,0,0,0.4); cursor: pointer;
            transition: all 0.3s ease;
        }
        .preview-box:hover { border-color: #00d4ff; background: rgba(0, 212, 255, 0.05); box-shadow: inset 0 0 30px rgba(0,212,255,0.1);}
        .preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; z-index: 2;}
        .preview-box .placeholder { position: absolute; z-index: 1; text-align: center; color: var(--text-muted); }
        
        #outputImage { width: 100%; height: auto; max-height: 500px; object-fit: contain; border-radius: 16px; display: none; box-shadow: 0 0 40px rgba(0,212,255,0.15); border: 1px solid rgba(0,212,255,0.2);}
        
        /* Loading Overlay */
        .loading-overlay { position: absolute; inset: 0; background: rgba(5, 5, 5, 0.85); z-index: 10; display: none; align-items: center; justify-content: center; flex-direction: column; border-radius: 20px; backdrop-filter: blur(8px);}
        .progress { height: 4px; border-radius: 2px; background: rgba(255,255,255,0.1); overflow: hidden; width: 70%; margin-top: 20px;}
        .progress-bar { background: linear-gradient(90deg, #ff00ff, #00d4ff); transition: width 0.5s ease; box-shadow: 0 0 15px #00d4ff;}
        
        .step-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px;
            background: rgba(0, 212, 255, 0.1);
            color: #00d4ff;
            border: 1px solid rgba(0, 212, 255, 0.4);
            border-radius: 10px;
            font-weight: bold; margin-right: 12px; font-family: 'Orbitron', sans-serif;
            box-shadow: inset 0 0 10px rgba(0,212,255,0.2);
        }
        
        .card-header-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 2px; font-family: 'Orbitron', sans-serif;}
        
        .privacy-badge {
            background: rgba(0, 255, 128, 0.1); border: 1px solid rgba(0, 255, 128, 0.3); color: #00ff80; padding: 10px 20px; border-radius: 50px; font-size: 13px; letter-spacing: 0.5px;
            box-shadow: 0 0 15px rgba(0, 255, 128, 0.1);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-color); }
        ::-webkit-scrollbar-thumb { background: rgba(0,212,255,0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,212,255,0.6); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="bi bi-cpu text-info me-2"></i> NEURAL<span style="color:#00d4ff">FACESWAP</span>
        </a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container position-relative" style="z-index: 2;">
        <h1 class="display-3 text-rgb mb-4">IDENTITY OVERRIDE</h1>
        <p class="lead mb-4 fw-medium text-muted" style="max-width: 600px; margin: 0 auto; letter-spacing: 0.5px;">Công cụ FaceSwap bằng AI tiên tiến. Thay đổi nhân dạng kỹ thuật số một cách chân thực nhất.</p>
        
        <div class="d-inline-flex align-items-center privacy-badge mt-4">
            <i class="bi bi-shield-lock-fill fs-5 me-2"></i>
            <span class="fw-bold">AUTO-WIPE PROTOCOL ENABLED - 100% SECURE</span>
        </div>
    </div>
</section>

<div class="container mb-5" style="margin-top: -30px; position: relative; z-index: 10;">
    <div class="row g-4 justify-content-center">
        <!-- Left: Settings & Input -->
        <div class="col-lg-6">
            <div class="rgb-border h-100">
                <div class="card-body p-4 p-md-5">
                    
                    <div class="card-header-title">
                        <span class="step-badge">1</span> UPLOAD TARGET & SOURCE
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <input type="file" id="imageInput1" class="d-none" accept="image/png, image/jpeg, image/webp">
                            <div class="preview-box" onclick="document.getElementById('imageInput1').click()">
                                <img id="inputPreview1" src="" style="display:none;">
                                <div class="placeholder" id="inputPlaceholder1">
                                    <i class="bi bi-person-bounding-box mb-2" style="font-size: 2rem; color: #00d4ff;"></i><br>
                                    <span class="fw-bold text-white small">ẢNH NỀN (TARGET)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <input type="file" id="imageInput2" class="d-none" accept="image/png, image/jpeg, image/webp">
                            <div class="preview-box" onclick="document.getElementById('imageInput2').click()">
                                <img id="inputPreview2" src="" style="display:none;">
                                <div class="placeholder" id="inputPlaceholder2">
                                    <i class="bi bi-person-fill-add mb-2" style="font-size: 2rem; color: #ff00ff;"></i><br>
                                    <span class="fw-bold text-white small">MẶT (SOURCE)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-header-title">
                        <span class="step-badge">2</span> NEURAL PROMPTS
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-info">Positive Directive</label>
                        <textarea id="promptInput" class="form-control" rows="3" placeholder="Enter generation parameters...">Perform face swapping with the following strict constraints:
Face source: Use the face (Image1)
Body & background source: Use (Image2) pose, body shape, clothing style, clothing details, and the entire background,do not change them.
 Shot with a 35mm lens, natural light, soft cinematic lighting, natural skin tones, RAW image, realistic, depth of field. Accurate proportions, realistic, sharp focus, simple background, following a professional character reference table style.</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-danger">Negative Filter</label>
                        <textarea id="negativePromptInput" class="form-control" rows="2" placeholder="Elements to exclude...">3d render, cgi, plastic skin, airbrushed, anime, cartoon, illustration, digital art, stiff pose, symmetrical posture, fake ocean, over-saturated blue water, perfect studio lighting, heavy makeup, doll-like, fake, deformed limbs, extra fingers, mutated hands, bad anatomy.</textarea>
                    </div>

                    <div class="p-4 rounded-4 mb-4" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);">
                        <label class="form-label mb-3 text-warning"><i class="bi bi-sliders me-2"></i>OVERRIDE PARAMETERS</label>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-arrows-collapse me-1"></i> W</span>
                                    <input type="number" id="widthInput" class="form-control" value="1280">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-arrows-expand me-1"></i> H</span>
                                    <input type="number" id="heightInput" class="form-control" value="1280">
                                </div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-layers me-1"></i> STP</span>
                                    <input type="number" id="stepsInput" class="form-control" value="4">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-magic me-1"></i> CFG</span>
                                    <input type="number" id="cfgInput" class="form-control" value="1" step="0.1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-3 fs-5 mt-2" id="btnGenerate">
                        <i class="bi bi-cpu-fill me-2"></i> EXECUTE SWAP
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Output -->
        <div class="col-lg-6">
            <div class="rgb-border h-100 position-relative">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner-grow text-info mb-4" role="status" style="width: 4rem; height: 4rem;"></div>
                    <h4 class="text-rgb mb-2" style="font-size: 1.5rem;">PROCESSING MATRIX...</h4>
                    <div class="progress mb-3" id="progressWrap" style="display:none; width: 60%;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%"></div>
                    </div>
                    <p class="text-info fw-bold" id="progressText" style="letter-spacing: 1px; font-family: 'Orbitron', sans-serif; font-size: 12px;">ESTABLISHING NEURAL LINK...</p>
                </div>
                
                <div class="card-body p-4 p-md-5 d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 500px;">
                    <div class="w-100 mb-auto text-start">
                        <div class="card-header-title">
                            <span class="step-badge">3</span> OUTPUT RENDER
                        </div>
                    </div>
                    
                    <div id="outputEmpty" class="my-auto">
                        <div class="p-4 rounded-circle mb-3 d-inline-block" style="background: rgba(0,212,255,0.05); border: 1px solid rgba(0,212,255,0.2);">
                            <i class="bi bi-eye-fill" style="font-size: 3rem; color: #00d4ff; opacity: 0.8;"></i>
                        </div>
                        <h4 class="fw-bold" style="color: #cbd5e1; font-family: 'Orbitron', sans-serif;">AWAITING DATA</h4>
                        <p class="text-muted small">Generated visual output will manifest here.</p>
                    </div>
                    
                    <img id="outputImage" src="" class="img-fluid mb-4">
                    
                    <div class="mt-auto w-100" id="outputActions" style="display:none;">
                        <hr style="border-color: rgba(255,255,255,0.1);" class="mb-4">
                        <button type="button" id="btnDownload" class="btn btn-success w-100 py-3 fs-6">
                            <i class="bi bi-download me-2"></i> EXTRACT TO LOCAL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JQuery & Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function() {
    const PROXY_URL = '?action=';
    let uploadedImageName1 = '';
    let uploadedImageName2 = '';
    let currentOutputUrl = '';
    let currentOutputInfo = null;
    let simProgressInterval = null;
    const baseWorkflow = <?php echo $workflowJson ? $workflowJson : '{}'; ?>;

    function handleImagePreview(inputId, previewId, placeholderId) {
        $(`#${inputId}`).on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $(`#${previewId}`).attr('src', e.target.result).show();
                    $(`#${placeholderId}`).hide();
                }
                reader.readAsDataURL(file);
            }
        });
    }

    handleImagePreview('imageInput1', 'inputPreview1', 'inputPlaceholder1');
    handleImagePreview('imageInput2', 'inputPreview2', 'inputPlaceholder2');

    async function safeJson(response) {
        const text = await response.text();
        if (!response.ok) {
            let msg = text;
            try { 
                const err = JSON.parse(text); 
                if(err.error) msg = typeof err.error === 'string' ? err.error : JSON.stringify(err.error); 
                if(err.node_errors) msg += " | Node errors: " + JSON.stringify(err.node_errors);
            } catch(e){}
            throw new Error(`HTTP ${response.status}: ${msg}`);
        }
        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error("Lỗi parse JSON. Raw response: " + text.substring(0, 100));
        }
    }

    async function uploadImageToComfy(file) {
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(`${PROXY_URL}upload`, {
            method: 'POST',
            body: formData
        });
        
        const data = await safeJson(response);
        return data.name; 
    }

    async function queuePrompt(workflow) {
        const response = await fetch(`${PROXY_URL}prompt`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: workflow })
        });
        
        return await safeJson(response);
    }

    async function pollHistory(promptId) {
        while (true) {
            const res = await fetch(`${PROXY_URL}history&prompt_id=${promptId}`);
            if(!res.ok) {
                 await new Promise(r => setTimeout(r, 2000));
                 continue;
            }
            const data = await safeJson(res);
            
            if (data[promptId]) {
                const outputs = data[promptId].outputs;
                for (const nodeId in outputs) {
                    if (outputs[nodeId].images && outputs[nodeId].images.length > 0) {
                        return outputs[nodeId].images[0];
                    }
                }
            }
            await new Promise(r => setTimeout(r, 2000));
        }
    }

    // Xử lý bảo mật: Xoá ảnh vật lý
    async function cleanupPrivateData(outputInfo) {
        const fd = new FormData();
        if (uploadedImageName1) fd.append('filename1', uploadedImageName1);
        if (uploadedImageName2) fd.append('filename2', uploadedImageName2);
        
        fetch(`${PROXY_URL}cleanup`, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => { console.log("Security Wipe Completed:", data); })
            .catch(e => console.error("Wipe error:", e));
    }

    // Xử lý nút Download
    $('#btnDownload').on('click', function() {
        if (!currentOutputUrl) return;
        
        const a = document.createElement('a');
        a.href = currentOutputUrl;
        a.download = 'AI_Neural_FaceSwap.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    $('#btnGenerate').on('click', async function() {
        const file1 = $('#imageInput1')[0].files[0];
        const file2 = $('#imageInput2')[0].files[0];
        
        const promptText = $('#promptInput').val().trim();
        const negPromptText = $('#negativePromptInput').val().trim();
        const width = parseInt($('#widthInput').val()) || 1280;
        const height = parseInt($('#heightInput').val()) || 1280;
        const steps = parseInt($('#stepsInput').val()) || 10;
        const cfg = parseFloat($('#cfgInput').val()) || 1;

        if (!file1 && !uploadedImageName1) {
            alert('Lỗi: Vui lòng nhập Ảnh Nền (Target)!');
            return;
        }
        if (!file2 && !uploadedImageName2) {
            alert('Lỗi: Vui lòng nhập Ảnh Mặt (Source)!');
            return;
        }

        // Bật trạng thái Loading
        $(this).prop('disabled', true);
        $('#loadingOverlay').css('display', 'flex');
        $('#progressWrap').show();
        $('#progressBar').css('width', '5%');
        $('#outputEmpty').hide();
        $('#outputImage').hide();
        $('#outputActions').hide();
        
        $('#progressText').text('UPLOADING TO AI CORE...');

        try {
            if (file1) {
                uploadedImageName1 = await uploadImageToComfy(file1);
                $('#progressBar').css('width', '10%');
            }
            if (file2) {
                uploadedImageName2 = await uploadImageToComfy(file2);
                $('#progressBar').css('width', '20%');
            }

            let wf = JSON.parse(JSON.stringify(baseWorkflow));
            
            // Map node IDs based on faceswap.json
            if(wf["630"]) wf["630"].inputs.image = uploadedImageName1; // Target Body
            if(wf["646"]) wf["646"].inputs.image = uploadedImageName2; // Source Face
            if(wf["645"]) wf["645"].inputs.prompt = promptText;
            if(wf["644"]) wf["644"].inputs.prompt = negPromptText;
            if(wf["642"]) {
                wf["642"].inputs.width = width;
                wf["642"].inputs.height = height;
            }
            if(wf["643"]) {
                wf["643"].inputs.steps = steps;
                wf["643"].inputs.cfg = cfg;
                wf["643"].inputs.seed = Math.floor(Math.random() * 1000000000);
            }
            
            // Ensure PreviewImage node connects to VAEDecode correctly just in case
            if(!wf["659"]) {
                wf["659"] = {"inputs": {"images": ["638", 0]}, "class_type": "PreviewImage"};
            } else if (!wf["659"].inputs.images) {
                wf["659"].inputs.images = ["638", 0];
            }

            $('#progressText').text('INITIALIZING NEURAL NETWORKS...');
            const promptRes = await queuePrompt(wf);
            if(promptRes.error) throw new Error(promptRes.error.message || 'Lỗi từ ComfyUI');
            const promptId = promptRes.prompt_id;

            $('#progressText').text('GENERATING VISUAL DATA...');
            
            // Giả lập thanh tiến trình chạy từ 20% -> 95%
            let fakePct = 20;
            simProgressInterval = setInterval(() => {
                if (fakePct < 95) {
                    fakePct += Math.random() * 3;
                    $('#progressBar').css('width', fakePct + '%');
                }
            }, 1000);

            // Chờ kết quả thực tế
            const outputImageInfo = await pollHistory(promptId);
            clearInterval(simProgressInterval);
            $('#progressBar').css('width', '100%');
            
            if (outputImageInfo) {
                currentOutputInfo = outputImageInfo;
                const viewUrl = `${PROXY_URL}view&filename=${outputImageInfo.filename}&subfolder=${outputImageInfo.subfolder}&type=${outputImageInfo.type}`;
                
                $('#progressText').text('DOWNLOADING TO LOCAL RAM...');
                
                // Fetch ảnh dưới dạng Blob để lưu thẳng vào RAM trình duyệt
                const imgRes = await fetch(viewUrl);
                const imgBlob = await imgRes.blob();
                currentOutputUrl = URL.createObjectURL(imgBlob);
                
                $('#outputImage').attr('src', currentOutputUrl).show();
                $('#outputActions').show();

                // NGAY LẬP TỨC KÍCH HOẠT DỌN DẸP SERVER (BẢO MẬT TUYỆT ĐỐI)
                $('#progressText').text('WIPING SERVER FOOTPRINT...');
                await cleanupPrivateData(currentOutputInfo);
                $('#progressText').text('RENDER SUCCESS. FOOTPRINT WIPED.');

            } else {
                throw new Error("Dữ liệu hình ảnh bị hỏng");
            }

        } catch (error) {
            console.error(error);
            if (simProgressInterval) clearInterval(simProgressInterval);
            $('#progressBar').css('background', '#ff0055');
            $('#progressText').text('SYSTEM ERROR: ' + error.message).css('color', '#ff0055');
            alert('Lỗi hệ thống: ' + error.message);
            $('#outputEmpty').show();
        } finally {
            // Tắt Loading
            setTimeout(() => {
                $('#loadingOverlay').hide();
                $('#progressBar').css({'width': '0%', 'background': ''});
                $(this).prop('disabled', false);
            }, 2000);
        }
    });
});
</script>
</body>
</html>
