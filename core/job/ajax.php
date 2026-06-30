<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/job_lib.php';

header('Content-Type: application/json; charset=utf-8');

$action = (string)($_REQUEST['action'] ?? '');

$out = static function(array $data): void {
    if (ob_get_level() > 0) {
        @ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    $out(['ok' => false, 'msg' => 'Không có kết nối CSDL']);
}

job_ensure_tables($ithanhloc);

$deptOpts = job_department_options();
$genderOpts = job_gender_options();
$statusOpts = job_task_status_options();

if ($action === 'meta') {
    $out([
        'ok' => true,
        'data' => [
            'departments' => $deptOpts,
            'genders' => $genderOpts,
            'statuses' => $statusOpts,
        ],
    ]);
}

if ($action === 'employees_list') {
    $employees = job_db_list_employees($ithanhloc);
    $groups = [];
    foreach (array_keys($deptOpts) as $k) {
        $groups[$k] = [];
    }
    foreach ($employees as $e) {
        $dept = (string)($e['department'] ?? 'Khac');
        if (!isset($groups[$dept])) $groups[$dept] = [];
        $groups[$dept][] = $e;
    }
    $out([
        'ok' => true,
        'data' => [
            'departments' => $deptOpts,
            'groups' => $groups,
        ],
    ]);
}

if ($action === 'employee_get') {
    $id = (int)($_GET['employee_id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) $out(['ok' => false, 'msg' => 'Thiếu employee_id']);
    $emp = job_db_get_employee($ithanhloc, $id);
    if (!$emp) $out(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);
    $out(['ok' => true, 'data' => ['employee' => $emp]]);
}

if ($action === 'employee_save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? 'other'));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department = trim((string)($_POST['department'] ?? 'Khac'));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') $out(['ok' => false, 'msg' => 'Vui lòng nhập tên nhân viên']);
    if (!isset($deptOpts[$department])) $department = 'Khac';
    if (!isset($genderOpts[$gender])) $gender = 'other';

    $avatarPath = null;
    if (isset($_FILES['avatar']) && is_array($_FILES['avatar'])) {
        global $uploadFolder;
        $absDir = dirname(__DIR__, 2) . '/' . ($uploadFolder ?? 'uploads') . '/job/avatars';
        $webPrefix = '/' . ($uploadFolder ?? 'uploads') . '/job/avatars';
        $up = job_upload_single($_FILES['avatar'], $absDir, $webPrefix);
        if ($up) $avatarPath = (string)($up['path'] ?? '');
    }

    if ($id > 0) {
        if ($avatarPath !== null) {
            $stmt = $ithanhloc->prepare('UPDATE job_employee SET name=?, avatar_path=?, position=?, gender=?, phone=?, department=?, is_active=? WHERE id=?');
            if ($stmt) {
                $stmt->bind_param('ssssssii', $name, $avatarPath, $position, $gender, $phone, $department, $isActive, $id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $ithanhloc->prepare('UPDATE job_employee SET name=?, position=?, gender=?, phone=?, department=?, is_active=? WHERE id=?');
            if ($stmt) {
                $stmt->bind_param('sssssii', $name, $position, $gender, $phone, $department, $isActive, $id);
                $stmt->execute();
                $stmt->close();
            }
        }
        $emp = job_db_get_employee($ithanhloc, $id);
        $out(['ok' => true, 'msg' => 'Đã cập nhật nhân viên', 'data' => ['employee' => $emp]]);
    }

    $stmt = $ithanhloc->prepare('INSERT INTO job_employee (name, avatar_path, position, gender, phone, department, is_active) VALUES (?,?,?,?,?,?,?)');
    if (!$stmt) $out(['ok' => false, 'msg' => 'Không thể lưu nhân viên']);
    $ap = (string)($avatarPath ?? '');
    $stmt->bind_param('ssssssi', $name, $ap, $position, $gender, $phone, $department, $isActive);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    $emp = job_db_get_employee($ithanhloc, $newId);
    $out(['ok' => true, 'msg' => 'Đã tạo nhân viên', 'data' => ['employee' => $emp]]);
}

if ($action === 'tasks_week') {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    if ($employeeId <= 0) $out(['ok' => false, 'msg' => 'Thiếu employee_id']);
    $employee = job_db_get_employee($ithanhloc, $employeeId);
    if (!$employee) $out(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);

    $week = job_week_start((string)($_GET['week'] ?? date('Y-m-d')));
    $weekDates = job_week_dates_mon_to_sat($week);
    $day = (string)($_GET['day'] ?? $weekDates[0]);
    if (!in_array($day, $weekDates, true)) $day = $weekDates[0];

    $tasksWeek = job_db_list_tasks_for_employee_week($ithanhloc, $employeeId, $week);
    $tasksByDay = [];
    foreach ($weekDates as $d) $tasksByDay[$d] = [];
    foreach ($tasksWeek as $t) {
        $d = (string)($t['work_date'] ?? '');
        if (!isset($tasksByDay[$d])) $tasksByDay[$d] = [];
        $tasksByDay[$d][] = $t;
    }

    $statusCounts = array_fill_keys(array_keys($statusOpts), 0);
    foreach ($tasksWeek as $t) {
        $sk = (string)($t['status'] ?? 'todo');
        if (!isset($statusCounts[$sk])) $statusCounts[$sk] = 0;
        $statusCounts[$sk]++;
    }

    $labels = [];
    foreach ($weekDates as $d) {
        $labels[$d] = job_weekday_label_vi($d);
    }

    $out([
        'ok' => true,
        'data' => [
            'employee' => $employee,
            'week_monday' => $week,
            'week_dates' => $weekDates,
            'week_labels' => $labels,
            'active_day' => $day,
            'tasks_by_day' => $tasksByDay,
            'tasks_week' => $tasksWeek,
            'status_options' => $statusOpts,
            'status_counts' => $statusCounts,
        ],
    ]);
}

if ($action === 'task_get') {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $taskId = (int)($_GET['task_id'] ?? 0);
    if ($employeeId <= 0 || $taskId <= 0) $out(['ok' => false, 'msg' => 'Thiếu tham số']);
    $t = job_db_get_task($ithanhloc, $taskId, $employeeId);
    if (!$t) $out(['ok' => false, 'msg' => 'Không tìm thấy công việc']);

    $stmt = $ithanhloc->prepare('SELECT id, task_id, file_path, file_kind, original_name FROM job_task_media WHERE task_id=? ORDER BY id ASC');
    $media = [];
    if ($stmt) {
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $media[] = $r;
        $stmt->close();
    }

    $out(['ok' => true, 'data' => ['task' => $t, 'media' => $media]]);
}

if ($action === 'task_save') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $workDate = trim((string)($_POST['work_date'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'todo'));
    $descHtml = (string)($_POST['description_html'] ?? '');
    $startAt = job_dt_from_local($_POST['start_at'] ?? null);
    $endAt = job_dt_from_local($_POST['end_at'] ?? null);

    if ($employeeId <= 0) $out(['ok' => false, 'msg' => 'Thiếu nhân viên']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) $out(['ok' => false, 'msg' => 'Ngày công việc không hợp lệ']);
    if ($title === '') $out(['ok' => false, 'msg' => 'Vui lòng nhập tên công việc']);
    if (!isset($statusOpts[$status])) $status = 'todo';

    $emp = job_db_get_employee($ithanhloc, $employeeId);
    if (!$emp) $out(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);

    if ($taskId > 0) {
        $stmt = $ithanhloc->prepare('UPDATE job_task SET work_date=?, title=?, description_html=?, status=?, start_at=?, end_at=?, updated_at=NOW() WHERE id=? AND employee_id=?');
        if ($stmt) {
            $stmt->bind_param('ssssssii', $workDate, $title, $descHtml, $status, $startAt, $endAt, $taskId, $employeeId);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $createdBy = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $ithanhloc->prepare('INSERT INTO job_task (employee_id, work_date, title, description_html, status, start_at, end_at, created_by) VALUES (?,?,?,?,?,?,?,?)');
        if ($stmt) {
            $stmt->bind_param('issssssi', $employeeId, $workDate, $title, $descHtml, $status, $startAt, $endAt, $createdBy);
            $stmt->execute();
            $taskId = (int)$stmt->insert_id;
            $stmt->close();
        }
    }

    if ($taskId > 0 && isset($_FILES['attachments'])) {
        global $uploadFolder;
        $absDir = dirname(__DIR__, 2) . '/' . ($uploadFolder ?? 'uploads') . '/job/tasks';
        $webPrefix = '/' . ($uploadFolder ?? 'uploads') . '/job/tasks';
        $ups = job_upload_multiple($_FILES['attachments'], $absDir, $webPrefix);
        foreach ($ups as $u) {
            $p = (string)($u['path'] ?? '');
            if ($p === '') continue;
            $kind = (string)($u['kind'] ?? 'other');
            $orig = (string)($u['original'] ?? '');
            $stmt = $ithanhloc->prepare('INSERT INTO job_task_media (task_id, file_path, file_kind, original_name) VALUES (?,?,?,?)');
            if ($stmt) {
                $stmt->bind_param('isss', $taskId, $p, $kind, $orig);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $week = job_week_start($workDate);
    $out([
        'ok' => true,
        'msg' => 'Đã lưu công việc',
        'data' => [
            'employee_id' => $employeeId,
            'task_id' => $taskId,
            'week_monday' => $week,
            'active_day' => $workDate,
        ],
    ]);
}

if ($action === 'task_media_delete') {
    $mediaId = (int)($_POST['media_id'] ?? 0);
    if ($mediaId <= 0) $out(['ok' => false, 'msg' => 'Thiếu media_id']);

    $sql = 'SELECT m.id, m.file_path, t.employee_id FROM job_task_media m JOIN job_task t ON m.task_id = t.id WHERE m.id=? LIMIT 1';
    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) $out(['ok' => false, 'msg' => 'Không xoá được file']);
    $stmt->bind_param('i', $mediaId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) $out(['ok' => false, 'msg' => 'Không tìm thấy file']);

    $path = (string)($row['file_path'] ?? '');
    if ($path !== '') {
        if (function_exists('media_delete_remote')) {
            media_delete_remote(ltrim($path, '/'));
        }
        $abs = rtrim(dirname(__DIR__, 2), '/') . '/' . ltrim($path, '/');
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    $del = $ithanhloc->prepare('DELETE FROM job_task_media WHERE id=?');
    if ($del) {
        $del->bind_param('i', $mediaId);
        $del->execute();
        $del->close();
    }

    $out(['ok' => true, 'msg' => 'Đã xoá file đính kèm']);
}

if ($action === 'task_delete') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $workDate = trim((string)($_POST['work_date'] ?? ''));

    if ($employeeId <= 0 || $taskId <= 0) $out(['ok' => false, 'msg' => 'Thiếu tham số']);

    job_db_delete_task($ithanhloc, $taskId, $employeeId);
    $week = $workDate !== '' ? job_week_start($workDate) : job_week_start(date('Y-m-d'));

    $out([
        'ok' => true,
        'msg' => 'Đã xoá công việc',
        'data' => [
            'employee_id' => $employeeId,
            'week_monday' => $week,
        ],
    ]);
}

$out(['ok' => false, 'msg' => 'Action không hợp lệ']);
