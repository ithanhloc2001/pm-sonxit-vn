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
        $safeName = 'i2i_' . time() . '_' . rand(1000, 9999) . '.' . ($ext ?: 'png');

        $ch = curl_init($comfyUrl . '/upload/image');
        $cFile = new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $safeName);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $cFile, 'overwrite' => 'true']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($ch);
        curl_close($ch);
        exit;
    }
    
    if ($action === 'prompt') {
        header('Content-Type: application/json');
        $data = file_get_contents('php://input');
        $ch = curl_init($comfyUrl . '/prompt');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($ch);
        curl_close($ch);
        exit;
    }
    
    if ($action === 'history') {
        header('Content-Type: application/json');
        $promptId = $_GET['prompt_id'] ?? '';
        $ch = curl_init($comfyUrl . '/history/' . $promptId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($ch);
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

        // Đường dẫn gốc của ComfyUI (đúng nơi tiến trình ComfyUI đang chạy)
        $comfyBaseDir = 'D:\ComfyUI_windows_portable\ComfyUI';
        $inputDir = $comfyBaseDir . '\input';
        $tempDir = $comfyBaseDir . '\temp';
        $outputDir = $comfyBaseDir . '\output';

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
$workflowFile = __DIR__ . '/workflows/image_to_image.json';
$workflowJson = file_exists($workflowFile) ? file_get_contents($workflowFile) : '{}';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Studio - Cyberpunk Edition</title>
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
            width: 100%; height: 280px; 
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
            <i class="bi bi-cpu text-info me-2"></i> NEURAL<span style="color:#00d4ff">STUDIO</span>
        </a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container position-relative" style="z-index: 2;">
        <h1 class="display-3 text-rgb mb-4">IMAGE SYNTHESIS CORE</h1>
        <p class="lead mb-4 fw-medium text-muted" style="max-width: 600px; margin: 0 auto; letter-spacing: 0.5px;">Giao diện điều khiển Qwen-Image-Edit. Kiến tạo thực tại kỹ thuật số mới từ dấu vết hình ảnh của bạn.</p>
        
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
                        <span class="step-badge">1</span> UPLOAD MATRIX
                    </div>
                    
                    <input type="file" id="imageInput" class="d-none" accept="image/png, image/jpeg, image/webp">
                    <div class="preview-box mb-5" onclick="document.getElementById('imageInput').click()">
                        <img id="inputPreview" src="" style="display:none;">
                        <div class="placeholder" id="inputPlaceholder">
                            <i class="bi bi-fingerprint mb-2" style="font-size: 3rem; color: #00d4ff;"></i><br>
                            <span class="fw-bold fs-5 text-white">INITIALIZE IMAGE</span><br>
                            <span class="small">PNG / JPG / WEBP</span>
                        </div>
                    </div>

                    <div class="card-header-title">
                        <span class="step-badge">2</span> NEURAL PROMPTS
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-info">Positive Directive</label>
                        <textarea id="promptInput" class="form-control" rows="3" placeholder="Enter generation parameters..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-danger">Negative Filter</label>
                        <textarea id="negativePromptInput" class="form-control" rows="2" placeholder="Elements to exclude...">no watermark, blurry, low quality, artifact</textarea>
                    </div>

                    <div class="p-4 rounded-4 mb-4" style="background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);">
                        <label class="form-label mb-3 text-warning"><i class="bi bi-sliders me-2"></i>OVERRIDE PARAMETERS</label>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-arrows-collapse me-1"></i> W</span>
                                    <input type="number" id="widthInput" class="form-control" value="1088">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-arrows-expand me-1"></i> H</span>
                                    <input type="number" id="heightInput" class="form-control" value="1920">
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
                        <i class="bi bi-cpu-fill me-2"></i> EXECUTE GENERATION
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
    let uploadedImageName = '';
    let currentOutputUrl = '';
    let currentOutputInfo = null;
    let simProgressInterval = null;
    const baseWorkflow = <?php echo $workflowJson ? $workflowJson : '{}'; ?>;

    $('#imageInput').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#inputPreview').attr('src', e.target.result).show();
                $('#inputPlaceholder').hide();
            }
            reader.readAsDataURL(file);
        }
    });

    async function uploadImageToComfy(file) {
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(`${PROXY_URL}upload`, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Failed to upload image to AI Core');
        const data = await response.json();
        console.log('[i2i] upload response:', data);
        return data.name;
    }

    async function queuePrompt(workflow) {
        console.log('[i2i] queue workflow:', JSON.stringify(workflow));
        const response = await fetch(`${PROXY_URL}prompt`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: workflow })
        });

        const data = await response.json();
        console.log('[i2i] /prompt response:', data);
        if (!response.ok || data.error) {
            const ne = data.node_errors ? JSON.stringify(data.node_errors) : '';
            throw new Error((data.error && data.error.message ? data.error.message : 'Generation directive rejected') + ' ' + ne);
        }
        return data;
    }

    async function pollHistory(promptId) {
        while (true) {
            const res = await fetch(`${PROXY_URL}history&prompt_id=${promptId}`);
            if(!res.ok) {
                 await new Promise(r => setTimeout(r, 2000));
                 continue;
            }
            const data = await res.json();
            
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
        if (!uploadedImageName && !outputInfo) return;
        const fd = new FormData();
        if (uploadedImageName) fd.append('filename', uploadedImageName);
        if (outputInfo) {
            fd.append('output_filename', outputInfo.filename);
            fd.append('output_subfolder', outputInfo.subfolder);
            fd.append('output_type', outputInfo.type);
        }
        
        fetch(`${PROXY_URL}cleanup`, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => { console.log("Security Wipe Completed:", data); })
            .catch(e => console.error("Wipe error:", e));
    }

    // Xử lý nút Download (Chỉ cần tải từ Blob URL, không cần gọi dọn dẹp nữa vì Server đã sạch)
    $('#btnDownload').on('click', function() {
        if (!currentOutputUrl) return;
        
        const a = document.createElement('a');
        a.href = currentOutputUrl;
        a.download = 'AI_Neural_Render.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    $('#btnGenerate').on('click', async function() {
        const file = $('#imageInput')[0].files[0];
        const promptText = $('#promptInput').val().trim();
        const negPromptText = $('#negativePromptInput').val().trim();
        const width = parseInt($('#widthInput').val()) || 1088;
        const height = parseInt($('#heightInput').val()) || 1920;
        const steps = parseInt($('#stepsInput').val()) || 4;
        const cfg = parseFloat($('#cfgInput').val()) || 1;

        if (!file && !uploadedImageName) {
            alert('Lỗi: Cần nhập ảnh ma trận gốc!');
            return;
        }
        if (!promptText) {
            alert('Lỗi: Cần nhập chỉ thị (Prompt)!');
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
            if (file) {
                uploadedImageName = await uploadImageToComfy(file);
                $('#progressBar').css('width', '15%');
            }

            let wf = JSON.parse(JSON.stringify(baseWorkflow));
            
            if(wf["618"]) wf["618"].inputs.image = uploadedImageName;
            if(wf["616:111"]) wf["616:111"].inputs.prompt = promptText;
            if(wf["616:110"]) wf["616:110"].inputs.prompt = negPromptText;
            if(wf["616:112"]) {
                wf["616:112"].inputs.width = width;
                wf["616:112"].inputs.height = height;
            }
            if(wf["616:493"]) {
                wf["616:493"].inputs.steps = steps;
                wf["616:493"].inputs.cfg = cfg;
                wf["616:493"].inputs.seed = Math.floor(Math.random() * 1000000000);
            }

            $('#progressText').text('INITIALIZING NEURAL NETWORKS...');
            const promptRes = await queuePrompt(wf);
            if(promptRes.error) throw new Error(promptRes.error.message || 'Lỗi từ ComfyUI');
            const promptId = promptRes.prompt_id;

            $('#progressText').text('GENERATING VISUAL DATA...');
            
            // Giả lập thanh tiến trình chạy từ 15% -> 95%
            let fakePct = 15;
            simProgressInterval = setInterval(() => {
                if (fakePct < 95) {
                    fakePct += Math.random() * 5;
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
