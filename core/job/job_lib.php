<?php

/**
 * core/job shared helpers
 * - DB table bootstrap
 * - safe uploads
 * - date/week helpers
 */

function job_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize TinyMCE HTML for safe rendering in admin views / print/PDF.
 * - Keeps basic formatting (p/br/lists/tables)
 * - Strips scripts/styles/event handlers
 * - Restricts <img src> to same-origin /uploads/* (dynamically based on $uploadFolder)
 * - Restricts <a href> to relative links or same-origin http(s)
 */
function job_sanitize_mce_html_for_print(string $html, string $baseUrl = ''): string {
    $html = trim($html);
    if ($html === '') return '';

    $host = '';
    if ($baseUrl !== '') {
        $u = @parse_url($baseUrl);
        if (is_array($u) && !empty($u['host'])) {
            $host = strtolower((string)$u['host']);
        }
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    // Wrap in a container to keep fragments valid HTML
    $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body><div id="__root">' . $html . '</div></body></html>';
    $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementById('__root');
    if (!$root) return '';

    $allowedTags = [
        'p' => true, 'br' => true, 'ul' => true, 'ol' => true, 'li' => true,
        'strong' => true, 'b' => true, 'em' => true, 'i' => true, 'u' => true, 's' => true,
        'blockquote' => true, 'code' => true, 'pre' => true, 'hr' => true,
        'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'h5' => true, 'h6' => true,
        'table' => true, 'thead' => true, 'tbody' => true, 'tr' => true, 'th' => true, 'td' => true,
        'a' => true, 'img' => true,
        // TinyMCE sometimes uses spans/divs; keep but strip attributes.
        'span' => true, 'div' => true,
    ];

    $walker = static function(DOMNode $node) use (&$walker, $allowedTags, $host): void {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (!isset($allowedTags[$tag])) {
                // Replace disallowed element with its text content
                $text = $node->ownerDocument->createTextNode($node->textContent ?? '');
                $node->parentNode?->replaceChild($text, $node);
                return;
            }

            // Remove dangerous/unused attributes
            $toRemove = [];
            foreach ($node->attributes ?? [] as $attr) {
                $name = strtolower($attr->name);
                if (str_starts_with($name, 'on')) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                if (in_array($name, ['style', 'class', 'id', 'data-mce-bogus', 'data-mce-style'], true)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
                // Only allow limited attrs for a/img
                if ($tag === 'a') {
                    if (!in_array($name, ['href', 'target', 'rel'], true)) $toRemove[] = $attr->name;
                } elseif ($tag === 'img') {
                    if (!in_array($name, ['src', 'alt'], true)) $toRemove[] = $attr->name;
                } else {
                    // Strip all other element attrs
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $a) {
                $node->removeAttribute($a);
            }

            if ($tag === 'a') {
                $href = trim((string)$node->getAttribute('href'));
                if ($href === '') {
                    $node->removeAttribute('href');
                } else {
                    $ok = false;
                    if ($href[0] === '/' || $href[0] === '#') {
                        $ok = true;
                    } elseif (preg_match('#^https?://#i', $href)) {
                        $u = @parse_url($href);
                        $h = is_array($u) && !empty($u['host']) ? strtolower((string)$u['host']) : '';
                        $ok = ($host !== '' && $h === $host);
                    }
                    if (!$ok) {
                        $node->removeAttribute('href');
                    } else {
                        $node->setAttribute('rel', 'noopener noreferrer');
                        if ($node->getAttribute('target') === '') {
                            $node->setAttribute('target', '_blank');
                        }
                    }
                }
            }

            if ($tag === 'img') {
                $src = trim((string)$node->getAttribute('src'));
                $ok = false;
                if ($src !== '') {
                    global $uploadFolder;
                    $upPrefix = '/' . ($uploadFolder ?? 'uploads') . '/';
                    if ($src[0] === '/' && str_starts_with($src, $upPrefix)) {
                        $ok = true;
                    } elseif (preg_match('#^https?://#i', $src)) {
                        $u = @parse_url($src);
                        $h = is_array($u) && !empty($u['host']) ? strtolower((string)$u['host']) : '';
                        $p = is_array($u) && !empty($u['path']) ? (string)$u['path'] : '';
                        $ok = ($host !== '' && $h === $host && str_starts_with($p, $upPrefix));
                    }
                }
                if (!$ok) {
                    // Drop unsafe images entirely
                    $node->parentNode?->removeChild($node);
                    return;
                }
                if ($node->getAttribute('alt') === '') {
                    $node->setAttribute('alt', '');
                }
            }
        }

        // Iterate children (copy list first because we may mutate)
        $children = [];
        foreach ($node->childNodes ?? [] as $ch) $children[] = $ch;
        foreach ($children as $ch) {
            $walker($ch);
        }
    };

    $walker($root);

    // Extract innerHTML of root
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function job_now(): string {
    return date('Y-m-d H:i:s');
}

function job_department_options(): array {
    return [
        'IT' => 'IT',
        'Marketing' => 'Marketing',
        'Sale' => 'Sale',
        'Hr' => 'Hr',
        'Khac' => 'Khác',
        'AI' => 'AI',
    ];
}

function job_gender_options(): array {
    return [
        'male' => 'Nam',
        'female' => 'Nữ',
        'other' => 'Khác',
    ];
}

function job_task_status_options(): array {
    return [
        'todo' => 'Chưa làm',
        'doing' => 'Đang làm',
        'done' => 'Hoàn thành',
        'blocked' => 'Bị chặn',
        'canceled' => 'Huỷ',
    ];
}

function job_ensure_tables(mysqli $ithanhloc): void {
    // Employees
    $ithanhloc->query("CREATE TABLE IF NOT EXISTS `job_employee` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `avatar_path` VARCHAR(512) NULL,
        `position` VARCHAR(255) NULL,
        `gender` VARCHAR(20) NOT NULL DEFAULT 'other',
        `phone` VARCHAR(50) NULL,
        `department` VARCHAR(50) NOT NULL DEFAULT 'Khac',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_department` (`department`),
        KEY `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tasks
    $ithanhloc->query("CREATE TABLE IF NOT EXISTS `job_task` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `employee_id` INT NOT NULL,
        `work_date` DATE NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description_html` MEDIUMTEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'todo',
        `start_at` DATETIME NULL,
        `end_at` DATETIME NULL,
        `created_by` INT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_emp_date` (`employee_id`, `work_date`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Media
    $ithanhloc->query("CREATE TABLE IF NOT EXISTS `job_task_media` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `task_id` INT NOT NULL,
        `file_path` VARCHAR(512) NOT NULL,
        `file_kind` VARCHAR(20) NOT NULL DEFAULT 'other',
        `original_name` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_task` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function job_dt_from_local(?string $val): ?string {
    $val = trim((string)$val);
    if ($val === '') return null;
    // input type=datetime-local => 2026-04-13T12:30
    $val = str_replace('T', ' ', $val);
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/', $val)) return null;
    if (strlen($val) === 16) $val .= ':00';
    return $val;
}

function job_week_start(string $anyDateYmd): string {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $anyDateYmd, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$d) $d = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    // ISO-8601: 1=Mon..7=Sun
    $dow = (int)$d->format('N');
    $monday = $d->modify('-' . ($dow - 1) . ' days');
    return $monday->format('Y-m-d');
}

function job_week_dates_mon_to_sat(string $mondayYmd): array {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $mondayYmd, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$d) $d = new DateTimeImmutable('monday this week', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $out = [];
    for ($i = 0; $i < 6; $i++) {
        $out[] = $d->modify('+' . $i . ' days')->format('Y-m-d');
    }
    return $out;
}

function job_weekday_label_vi(string $ymd): string {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$d) return $ymd;
    $dow = (int)$d->format('N');
    $map = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
    return ($map[$dow] ?? $ymd) . ' (' . $d->format('d/m') . ')';
}

function job_safe_slug(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[^a-zA-Z0-9_\.-]+/', '-', $name);
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');
    return $name !== '' ? $name : 'file';
}

function job_ensure_dir(string $absDir): bool {
    if (is_dir($absDir)) return true;
    return @mkdir($absDir, 0775, true);
}

function job_upload_single(array $file, string $absDir, string $webPrefix): ?array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return null;

    $orig = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $base = job_safe_slug(pathinfo($orig, PATHINFO_FILENAME));
    $rand = bin2hex(random_bytes(6));
    $fname = $base . '-' . date('YmdHis') . '-' . $rand . ($ext ? ('.' . $ext) : '');

    if (!job_ensure_dir($absDir)) return null;
    $dest = rtrim($absDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fname;
    if (!@move_uploaded_file($tmp, $dest)) return null;

    $mime = (string)($file['type'] ?? '');
    $kind = 'other';
    if (stripos($mime, 'image/') === 0) $kind = 'image';
    if (stripos($mime, 'video/') === 0) $kind = 'video';

    $webPath = rtrim($webPrefix, '/') . '/' . $fname;
    if (function_exists('media_publish_local_file')) {
        media_publish_local_file(ltrim($webPath, '/'));
    }

    return [
        'path' => $webPath,
        'kind' => $kind,
        'original' => $orig,
    ];
}

function job_upload_multiple(array $files, string $absDir, string $webPrefix): array {
    $out = [];
    if (!isset($files['name']) || !is_array($files['name'])) return $out;

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $one = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
        $res = job_upload_single($one, $absDir, $webPrefix);
        if ($res) $out[] = $res;
    }
    return $out;
}

function job_db_list_employees(mysqli $ithanhloc): array {
    $rows = [];
    $res = $ithanhloc->query("SELECT id, name, avatar_path, position, gender, phone, department, is_active FROM job_employee ORDER BY department ASC, name ASC");
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

function job_db_get_employee(mysqli $ithanhloc, int $id): ?array {
    $stmt = $ithanhloc->prepare('SELECT * FROM job_employee WHERE id=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function job_db_list_tasks_for_employee_week(mysqli $ithanhloc, int $employeeId, string $mondayYmd): array {
    $dates = job_week_dates_mon_to_sat($mondayYmd);
    $start = $dates[0] . ' 00:00:00';
    $end = $dates[count($dates) - 1] . ' 23:59:59';

    $stmt = $ithanhloc->prepare("SELECT t.*
        FROM job_task t
        WHERE t.employee_id=? AND t.work_date BETWEEN ? AND ?
        ORDER BY t.work_date ASC, COALESCE(t.start_at, t.created_at) ASC, t.id ASC");
    if (!$stmt) return [];
    $startDate = $dates[0];
    $endDate = $dates[count($dates) - 1];
    $stmt->bind_param('iss', $employeeId, $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    if (!$rows) return [];

    // media mapping
    $taskIds = array_map(static fn($r) => (int)$r['id'], $rows);
    $in = implode(',', array_fill(0, count($taskIds), '?'));
    $types = str_repeat('i', count($taskIds));
    $sql = "SELECT id, task_id, file_path, file_kind, original_name FROM job_task_media WHERE task_id IN ($in) ORDER BY id ASC";
    $stmtM = $ithanhloc->prepare($sql);
    $mediaByTask = [];
    if ($stmtM) {
        $stmtM->bind_param($types, ...$taskIds);
        $stmtM->execute();
        $resM = $stmtM->get_result();
        while ($m = $resM->fetch_assoc()) {
            $tid = (int)($m['task_id'] ?? 0);
            if (!isset($mediaByTask[$tid])) $mediaByTask[$tid] = [];
            $mediaByTask[$tid][] = $m;
        }
        $stmtM->close();
    }

    foreach ($rows as &$r) {
        $tid = (int)$r['id'];
        $r['_media'] = $mediaByTask[$tid] ?? [];
    }
    unset($r);

    return $rows;
}

function job_db_get_task(mysqli $ithanhloc, int $taskId, int $employeeId): ?array {
    $stmt = $ithanhloc->prepare('SELECT * FROM job_task WHERE id=? AND employee_id=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('ii', $taskId, $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function job_db_delete_task(mysqli $ithanhloc, int $taskId, int $employeeId): void {
    // Load media paths to optionally delete files
    $stmt = $ithanhloc->prepare('SELECT file_path FROM job_task_media WHERE task_id=?');
    $paths = [];
    if ($stmt) {
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $paths[] = (string)($r['file_path'] ?? '');
        }
        $stmt->close();
    }

    $stmt = $ithanhloc->prepare('DELETE FROM job_task_media WHERE task_id=?');
    if ($stmt) {
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $ithanhloc->prepare('DELETE FROM job_task WHERE id=? AND employee_id=?');
    if ($stmt) {
        $stmt->bind_param('ii', $taskId, $employeeId);
        $stmt->execute();
        $stmt->close();
    }

    // Best-effort file delete (only under uploads folder)
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    global $uploadFolder;
    $upPrefix = '/' . ($uploadFolder ?? 'uploads') . '/';
    foreach ($paths as $p) {
        $p = trim($p);
        if ($p === '' || stripos($p, $upPrefix) === false) continue;
        $abs = $docRoot . str_replace('/', DIRECTORY_SEPARATOR, $p);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
