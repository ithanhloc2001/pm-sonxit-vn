<?php
require_once __DIR__ . '/../../config.php';

// Check admin permission
if (!$isAdmin) {
    jOut(['ok' => false, 'msg' => 'Quyền truy cập bị từ chối.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$upFolder = $uploadFolder ?? 'uploads';

// Ensure table exists (using tableExists helper from functions.php)
if (!tableExists($ithanhloc, 'site_media_library')) {
    $ithanhloc->query("CREATE TABLE `site_media_library` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `file_type` VARCHAR(100),
        `file_size` BIGINT,
        `title` VARCHAR(255),
        `alt_text` VARCHAR(255),
        `caption` TEXT,
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`file_type`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Helper for multi-upload
if (!function_exists('normalizeUploadedFiles')) {
    function normalizeUploadedFiles(array $fileInput): array {
        $files = [];
        if (!isset($fileInput['name'])) return $files;
        if (is_array($fileInput['name'])) {
            for ($i = 0; $i < count($fileInput['name']); $i++) {
                $files[] = [
                    'name' => $fileInput['name'][$i],
                    'type' => $fileInput['type'][$i],
                    'tmp_name' => $fileInput['tmp_name'][$i],
                    'error' => $fileInput['error'][$i],
                    'size' => $fileInput['size'][$i],
                ];
            }
        } else {
            $files[] = $fileInput;
        }
        return $files;
    }
}

switch ($action) {
    case 'list':
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);

        $sql = "SELECT * FROM site_media_library WHERE 1=1";
        $params = [];
        $types = "";

        if ($search !== '') {
            $sql .= " AND (file_name LIKE ? OR title LIKE ? OR alt_text LIKE ?)";
            $term = "%$search%";
            $params[] = $term; $params[] = $term; $params[] = $term;
            $types .= "sss";
        }

        if ($type !== '') {
            if ($type === 'image') {
                $sql .= " AND file_type LIKE 'image/%'";
            } elseif ($type === 'video') {
                $sql .= " AND file_type LIKE 'video/%'";
            } else {
                $sql .= " AND file_type = ?";
                $params[] = $type;
                $types .= "s";
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Lỗi chuẩn bị truy vấn: ' . $ithanhloc->error]);
        }
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        if ($stmt) $stmt->close();

        foreach ($data as &$item) {
            $item['url'] = to_abs_url($upFolder . '/' . $item['file_path'], $baseUrl);
        }

        jOut(['ok' => true, 'data' => $data]);
        break;

    case 'upload':
        if (empty($_FILES['files'])) {
            jOut(['ok' => false, 'msg' => 'Không có tệp nào được tải lên.']);
        }

        $files = normalizeUploadedFiles($_FILES['files']);
        $saved = [];
        $errors = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;

            $fileName = $file['name'];
            $tmp = $file['tmp_name'];
            $size = $file['size'];
            $type = $file['type'];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $cleanName = 'media_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            
            $destDir = __DIR__ . '/../../' . $upFolder . '/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $dest = $destDir . $cleanName;

            if (move_uploaded_file($tmp, $dest)) {
                // Đẩy lên VPS media. Trả true nếu: remote tắt (no-op) HOẶC đẩy thành công.
                // Trả false khi remote BẬT nhưng đẩy LỖI → giữ file local, KHÔNG route media domain.
                global $mediaRemoteEnabled;
                $remoteOn = !empty($mediaRemoteEnabled);
                $published = true;
                if (function_exists('media_publish_local_file')) {
                    $published = media_publish_local_file($upFolder . '/' . $cleanName);
                }
                // Nếu remote bật mà đẩy thất bại → cảnh báo + phục vụ ảnh từ server GỐC
                // (file vẫn còn local) thay vì link media domain bị 404.
                $pushFailed = ($remoteOn && !$published);
                if ($pushFailed) {
                    $errors[] = "Đã lưu local nhưng KHÔNG đẩy được lên media VPS: $fileName (kiểm tra receiver.php trên VPS).";
                }

                $stmt = $ithanhloc->prepare("INSERT INTO site_media_library (file_name, file_path, file_type, file_size, title) VALUES (?, ?, ?, ?, ?)");
                $relPath = $cleanName;
                $stmt->bind_param("sssis", $fileName, $relPath, $type, $size, $fileName);
                $stmt->execute();

                $id = $ithanhloc->insert_id;
                if ($stmt) $stmt->close();

                // URL: bình thường qua to_abs_url (media domain nếu bật). Nếu đẩy lỗi → ép URL gốc.
                $fileUrl = $pushFailed
                    ? rtrim((string)$baseUrl, '/') . '/' . $upFolder . '/' . $relPath
                    : to_abs_url($upFolder . '/' . $relPath, $baseUrl);

                $saved[] = [
                    'id' => $id,
                    'file_name' => $fileName,
                    'url' => $fileUrl,
                    'file_type' => $type,
                    'file_path' => $relPath,
                    'size' => $size,
                    'title' => $fileName,
                    'push_failed' => $pushFailed
                ];
            } else {
                $errors[] = "Không thể lưu tệp: $fileName";
            }
        }

        jOut(['ok' => true, 'saved' => $saved, 'errors' => $errors]);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $alt = $_POST['alt_text'] ?? '';
        $caption = $_POST['caption'] ?? '';
        $desc = $_POST['description'] ?? '';

        if (!$id) {
            jOut(['ok' => false, 'msg' => 'Thiếu ID tệp.']);
        }

        $stmt = $ithanhloc->prepare("UPDATE site_media_library SET title=?, alt_text=?, caption=?, description=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssi", $title, $alt, $caption, $desc, $id);
            $stmt->execute();
            $stmt->close();
        }

        jOut(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            jOut(['ok' => false, 'msg' => 'Thiếu ID tệp.']);
        }

        $stmt = $ithanhloc->prepare("SELECT file_path FROM site_media_library WHERE id = ?");
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Lỗi kết nối CSDL']);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($file) {
            if (function_exists('media_delete_remote')) {
                media_delete_remote($upFolder . '/' . $file['file_path']);
            }
            $fullPath = __DIR__ . '/../../' . $upFolder . '/' . $file['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $stmtDel = $ithanhloc->prepare("DELETE FROM site_media_library WHERE id = ?");
            if ($stmtDel) {
                $stmtDel->bind_param("i", $id);
                $stmtDel->execute();
                $stmtDel->close();
            }
            jOut(['ok' => true]);
        } else {
            jOut(['ok' => false, 'msg' => 'Tệp không tồn tại trong CSDL.']);
        }
        break;

    case 'sync':
        $dir = str_replace('\\', '/', realpath(__DIR__ . '/../../' . $upFolder) . '/');
        $added = 0;
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if ($file->isDir()) continue;
                
                $filePath = str_replace('\\', '/', realpath($file->getPathname()));
                $relPath = ltrim(str_replace($dir, '', $filePath), '/');
                
                // Skip hidden files
                if (strpos(basename($relPath), '.') === 0) continue;

                $stmt = $ithanhloc->prepare("SELECT id FROM site_media_library WHERE file_path = ?");
                if (!$stmt) continue;
                $stmt->bind_param("s", $relPath);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$exists) {
                    $name = $file->getFilename();
                    $size = $file->getSize();
                    $type = @mime_content_type($filePath) ?: 'application/octet-stream';
                    
                    $stmtIns = $ithanhloc->prepare("INSERT INTO site_media_library (file_name, file_path, file_type, file_size, title) VALUES (?, ?, ?, ?, ?)");
                    if ($stmtIns) {
                        $stmtIns->bind_param("sssis", $name, $relPath, $type, $size, $name);
                        $stmtIns->execute();
                        $stmtIns->close();
                        $added++;
                    }
                }
            }
        }
        jOut(['ok' => true, 'added' => $added]);
        break;

    default:
        jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
}
