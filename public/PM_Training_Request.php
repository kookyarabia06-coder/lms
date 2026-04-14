<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];

$success_message = '';
$error_message = '';

// Handle AJAX Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_training_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Check permission
        $stmt = $pdo->prepare("SELECT requester_id FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if (!is_admin() && !is_superadmin() && $request['requester_id'] != $current_user_id) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this request']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Add Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $training_type = 'External';
        $title = trim($_POST['title'] ?? '');
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        
        $errors = [];
        if (empty($title)) $errors[] = "Title is required";
        if (empty($date_start)) $errors[] = "Start date is required";
        if (empty($date_end)) $errors[] = "End date is required";
        
        if (!empty($date_start) && !empty($date_end)) {
            if (strtotime($date_end) < strtotime($date_start)) {
                $errors[] = "End date cannot be earlier than start date";
            }
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        $requester_id = $current_user_id;
        
        // Get requester name
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname, username FROM users WHERE id = ?");
        $stmt->execute([$requester_id]);
        $user = $stmt->fetch();
        $requester_name = $user['fullname'] ?: ($user['username'] ?? 'Unknown');
        
        // Insert training request
        $stmt = $pdo->prepare("INSERT INTO training_requests (
            training_type, title, date_start, date_end, location_type,
            hospital_order_no, amount, late_filing, official_business, 
            remarks, requester_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        $stmt->execute([
            $training_type,
            $title,
            $date_start,
            $date_end,
            $location_type,
            $hospital_order_no,
            $amount,
            $late_filing,
            $official_business,
            $remarks,
            $requester_id
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Training request submitted successfully!',
            'request' => [
                'id' => $new_id,
                'title' => $title,
                'training_type' => $training_type,
                'date_start' => $date_start,
                'date_end' => $date_end,
                'requester_name' => $requester_name,
                'hospital_order_no' => $hospital_order_no,
                'amount' => $amount,
                'official_business' => $official_business,
                'remarks' => $remarks,
                'status' => 'pending'
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_training_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Check if request is completed - prevent editing
        $stmt = $pdo->prepare("SELECT status, date_end FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $checkRequest = $stmt->fetch();
        if ($checkRequest && $checkRequest['status'] === 'complete') {
            echo json_encode(['success' => false, 'message' => 'Completed requests cannot be edited.']);
            exit;
        }
        
        $title = trim($_POST['title'] ?? '');
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Check if training has ended to allow file uploads
        $training_end_date = new DateTime($checkRequest['date_end']);
        $today = new DateTime();
        $can_upload_files = $today > $training_end_date;
        
        // Handle file uploads
        $upload_dir = __DIR__ . '/../uploads/training/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        function uploadTrainingFile($field_name) {
            if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                return null;
            }
            $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (!in_array($ext, $allowed)) {
                return null;
            }
            $filename = 'training_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = __DIR__ . '/../uploads/training/';
            if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $upload_dir . $filename)) {
                return $filename;
            }
            return null;
        }
        
        $ptr_file = $can_upload_files ? uploadTrainingFile('ptr_file') : null;
        $coc_file = $can_upload_files ? uploadTrainingFile('coc_file') : null;
        $coa_file = $can_upload_files ? uploadTrainingFile('coa_file') : null;
        $mom_file = $can_upload_files ? uploadTrainingFile('mom_file') : null;
        
        // Build update query
        $sql = "UPDATE training_requests SET";
        $params = [];
        $updates = [];
        
        if (!empty($title)) {
            $updates[] = "title = ?";
            $params[] = $title;
        }
        
        if (!empty($location_type)) {
            $updates[] = "location_type = ?";
            $params[] = $location_type;
        }
        
        $updates[] = "hospital_order_no = ?";
        $params[] = $hospital_order_no;
        
        $updates[] = "amount = ?";
        $params[] = $amount;
        
        $updates[] = "late_filing = ?";
        $params[] = $late_filing;
        
        $updates[] = "official_business = ?";
        $params[] = $official_business;
        
        $updates[] = "remarks = ?";
        $params[] = $remarks;
        
        if ($ptr_file) {
            $updates[] = "ptr_file = ?";
            $params[] = $ptr_file;
        }
        
        if ($coc_file) {
            $updates[] = "coc_file = ?";
            $params[] = $coc_file;
        }
        
        if ($coa_file) {
            $updates[] = "coa_file = ?";
            $params[] = $coa_file;
        }
        
        if ($mom_file) {
            $updates[] = "mom_file = ?";
            $params[] = $mom_file;
        }
        
        // Approve status (admin only)
        if (isset($_POST['approve_status']) && (is_admin() || is_superadmin())) {
            $updates[] = "status = 'approved'";
        }
        
        $updates[] = "updated_at = NOW()";
        
        $sql .= " " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Training request updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Mark Training as Complete (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_training_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can complete training requests']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        
        // Check if request exists and is approved
        $stmt = $pdo->prepare("SELECT status, ptr_file, coc_file, coa_file, mom_file FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if ($request['status'] !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Only approved requests can be marked as complete']);
            exit;
        }
        
        // Check if at least one file is attached
        $has_files = !empty($request['ptr_file']) || !empty($request['coc_file']) || !empty($request['coa_file']) || !empty($request['mom_file']);
        if (!$has_files) {
            echo json_encode(['success' => false, 'message' => 'At least one file (PTR, COC, COA, or MOM) must be attached before marking as complete']);
            exit;
        }
        
        // Update status to complete
        $stmt = $pdo->prepare("UPDATE training_requests SET status = 'complete', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training marked as complete successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Request Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_training_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        // Get the request
        $stmt = $pdo->prepare("SELECT * FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        // Check permission
        $currentUserId = $_SESSION['user']['id'];
        $userRole = $_SESSION['user']['role'] ?? '';
        
        if ($userRole !== 'admin' && $userRole !== 'superadmin' && $request['requester_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to view this request']);
            exit;
        }
        
        echo json_encode(['success' => true, 'request' => $request]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Reschedule Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_training_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $resched_reason = trim($_POST['resched_reason'] ?? '');
        
        $errors = [];
        if (empty($date_start)) $errors[] = "New start date is required";
        if (empty($date_end)) $errors[] = "New end date is required";
        
        if (!empty($date_start) && !empty($date_end)) {
            if (strtotime($date_end) < strtotime($date_start)) {
                $errors[] = "End date cannot be earlier than start date";
            }
        }
        
        if (empty($resched_reason)) $errors[] = "Reschedule reason is required";
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        // Check permission
        $stmt = $pdo->prepare("SELECT requester_id FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if (!is_admin() && !is_superadmin() && $request['requester_id'] != $current_user_id) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to reschedule this request']);
            exit;
        }
        
        // Update dates and store reason
        $stmt = $pdo->prepare("UPDATE training_requests SET
            date_start = ?, date_end = ?, resched_reason = ?, status = 'pending', updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$date_start, $date_end, $resched_reason, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request rescheduled successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Report Data (Admin Only) - Only show COMPLETED status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_training_report_data'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can view reports']);
            exit;
        }
        
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        $division_id = isset($_GET['division_id']) && !empty($_GET['division_id']) ? (int)$_GET['division_id'] : null;
        $dept_id = isset($_GET['dept_id']) && !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        
        // Only show COMPLETED status and External training
        $where_clauses = ["tr.status = 'complete'", "tr.training_type = 'External'"];
        $params = [];
        
        if ($year) {
            $where_clauses[] = "YEAR(tr.date_start) = ?";
            $params[] = $year;
        }
        
        if ($month) {
            $where_clauses[] = "MONTH(tr.date_start) = ?";
            $params[] = $month;
        }
        
        if ($division_id) {
            $where_clauses[] = "d.id = ?";
            $params[] = $division_id;
        }
        
        if ($dept_id) {
            $where_clauses[] = "dept.id = ?";
            $params[] = $dept_id;
        }
        
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        $query = "
            SELECT 
                tr.id,
                tr.title,
                tr.location_type as venue,
                DATE_FORMAT(tr.date_start, '%M %d, %Y') as date_start,
                DATE_FORMAT(tr.date_end, '%M %d, %Y') as date_end,
                tr.date_end as date_end_raw,
                CONCAT(u.fname, ' ', u.lname) as requester_name,
                u.username,
                d.name as division_name,
                dept.name as department_name,
                tr.hospital_order_no,
                tr.amount,
                tr.status,
                tr.ptr_file,
                tr.coc_file,
                tr.coa_file,
                tr.mom_file
            FROM training_requests tr
            LEFT JOIN users u ON tr.requester_id = u.id
            LEFT JOIN user_departments ud ON ud.user_id = u.id
            LEFT JOIN depts dept ON ud.dept_id = dept.id
            LEFT JOIN departments d ON dept.department_id = d.id
            $where_sql
            GROUP BY tr.id
            ORDER BY tr.date_start DESC, tr.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'reports' => $reports]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Report Filter Options (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_training_filter_options'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can view reports']);
            exit;
        }
        
        // Get available years from completed external trainings
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM training_requests WHERE status = 'complete' AND training_type = 'External' ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get all divisions
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all departments
        $stmt = $pdo->query("SELECT id, name, department_id FROM depts ORDER BY name");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'years' => $years,
            'divisions' => $divisions,
            'departments' => $departments
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch all divisions and departments for filters
$stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, name, department_id FROM depts ORDER BY name");
$all_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch training requests with filters
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : '';
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$filter_division = isset($_GET['filter_division']) && !empty($_GET['filter_division']) ? (int)$_GET['filter_division'] : '';
$filter_dept = isset($_GET['filter_dept']) && !empty($_GET['filter_dept']) ? (int)$_GET['filter_dept'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clause = [];
$params = [];

// Always filter for External training only
$where_clause[] = "tr.training_type = 'External'";

if (!is_admin() && !is_superadmin()) {
    $where_clause[] = "tr.requester_id = ?";
    $params[] = $current_user_id;
}

if (!empty($filter_status)) {
    $where_clause[] = "tr.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_year)) {
    $where_clause[] = "YEAR(tr.date_start) = ?";
    $params[] = $filter_year;
}

if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $where_clause[] = "MONTH(tr.date_start) = ?";
    $params[] = $filter_month;
}

if (!empty($filter_division)) {
    $where_clause[] = "d.id = ?";
    $params[] = $filter_division;
}

if (!empty($filter_dept)) {
    $where_clause[] = "dept.id = ?";
    $params[] = $filter_dept;
}

if (!empty($search)) {
    $where_clause[] = "(tr.title LIKE ? OR tr.location_type LIKE ? OR tr.hospital_order_no LIKE ? OR tr.remarks LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";

$query = "SELECT 
    tr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    GROUP_CONCAT(DISTINCT d.name) as division_name,
    GROUP_CONCAT(DISTINCT dept.name) as department_name
    FROM training_requests tr
    LEFT JOIN users u ON tr.requester_id = u.id
    LEFT JOIN user_departments ud ON ud.user_id = u.id
    LEFT JOIN depts dept ON ud.dept_id = dept.id
    LEFT JOIN departments d ON dept.department_id = d.id
    $where_sql
    GROUP BY tr.id
    ORDER BY tr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$training_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Training Request Management - LMS</title>
    <link href="<?= BASE_URL ?>/assets/css/training.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .btn-action.btn-view {
            background-color: #6c757d;
            color: white;
        }
        .btn-action.btn-view:hover {
            background-color: #5a6268;
            color: white;
        }
        .btn-action.btn-view-attachment {
            background-color: #17a2b8;
            color: white;
        }
        .btn-action.btn-view-attachment:hover {
            background-color: #138496;
            color: white;
        }
        .current-file a {
            font-size: 0.85rem;
            color: #0d6efd;
            text-decoration: none;
        }
        .current-file a:hover {
            text-decoration: underline;
        }
        .attachment-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .attachment-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            border-radius: 8px;
        }
        .attachment-info {
            flex: 1;
        }
        .attachment-title {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .attachment-filename {
            margin: 0;
            font-size: 0.75rem;
            color: #6c757d;
        }
        .attachment-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #dee2e6;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0"><i class="fas fa-chalkboard-user me-2"></i>External Training Request Management</h3>
                <p class="text-muted mb-0 mt-1">Manage external training requests, upload certificates, and track completions</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrainingModal">
                <i class="fas fa-plus me-2"></i>New Training Request
            </button>
        </div>

        <!-- Filter Section -->
        <div class="filter-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 24px; margin-bottom: 32px; margin-top: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <form method="GET" action="" id="filterForm">
                <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
                    <!-- Year Filter -->
                    <div style="flex: 1; min-width: 130px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">YEAR</label>
                        <select name="filter_year" class="form-select" id="filterYear" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px; background-color: #fff;">
                            <option value="">All Years</option>
                            <?php 
                            $current_year = (int)date('Y');
                            for ($i = $current_year; $i >= $current_year - 5; $i--): 
                            ?>
                                <option value="<?= $i ?>" <?= ($filter_year == $i) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Month Filter -->
                    <div style="flex: 1; min-width: 140px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">MONTH</label>
                        <select name="filter_month" class="form-select" id="filterMonth" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px; background-color: #fff;">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div style="flex: 1; min-width: 130px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">STATUS</label>
                        <select name="filter_status" class="form-select" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px; background-color: #fff;">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="complete" <?= ($filter_status == 'complete') ? 'selected' : '' ?>>Complete</option>
                            <option value="rejected" <?= ($filter_status == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <!-- Division Filter -->
                    <div style="flex: 1; min-width: 140px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">DIVISION</label>
                        <select name="filter_division" class="form-select" id="filterDivision" onchange="updateDepartmentFilter()" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px; background-color: #fff;">
                            <option value="">All Divisions</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>" <?= ($filter_division == $div['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div style="flex: 1; min-width: 150px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">DEPARTMENT</label>
                        <select name="filter_dept" class="form-select" id="filterDept" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px; background-color: #fff;">
                            <option value="">All Departments</option>
                            <?php foreach ($all_departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" 
                                        data-division-id="<?= $dept['department_id'] ?>"
                                        <?= ($filter_dept == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search -->
                    <div style="flex: 1.5; min-width: 200px;">
                        <label class="form-label" style="font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">SEARCH</label>
                        <input type="text" name="search" class="form-control" placeholder="Search title, location, order no..." 
                            value="<?= htmlspecialchars($search) ?>" style="border-color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; padding: 10px 12px;">
                    </div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="background: #0d6efd; border: none; padding: 10px 28px; border-radius: 8px; font-size: 0.85rem; font-weight: 500;">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary" style="border-color: #cbd5e1; color: #475569; padding: 10px 28px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none;">
                            <i class="fas fa-undo-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-row" style="display: flex; gap: 20px; margin-bottom: 24px; flex-wrap: wrap;">
            <div class="stat-item" style="background: #fff; border-radius: 12px; padding: 16px 24px; flex: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                <span class="stat-label" style="font-size: 0.75rem; color: #64748b;">Total Training Requests</span>
                <span class="stat-number" id="totalCount" style="font-size: 1.75rem; font-weight: 700; color: #0d6efd; display: block;"><?= number_format(count($training_requests)) ?></span>
            </div>
            <div class="stat-item" style="background: #fff; border-radius: 12px; padding: 16px 24px; flex: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                <span class="stat-label" style="font-size: 0.75rem; color: #64748b;">Total Amount</span>
                <span class="stat-number" id="totalAmount" style="font-size: 1.75rem; font-weight: 700; color: #198754; display: block;">₱<?= number_format(array_sum(array_column($training_requests, 'amount')), 2) ?></span>
            </div>
        </div>

        <!-- Training Requests Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-list"></i> External Training Requests List</h4>
                    <?php if (is_admin() || is_superadmin()): ?>
                    <button class="btn btn-success" id="generateReportBtn" data-bs-toggle="modal" data-bs-target="#trainingReportModal">
                        <i class="fas fa-chart-line me-2"></i>Generate Report
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Location Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Requester</th>
                            <th>HO No.</th>
                            <th>Amount</th>
                            <th>Is OB</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if (!empty($training_requests)): ?>
                            <?php foreach ($training_requests as $request): ?>
                                <?php 
                                $has_attachments = !empty($request['ptr_file']) || !empty($request['coc_file']) || !empty($request['coa_file']) || !empty($request['mom_file']);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if ($has_attachments): ?>
                                            <br><span class="badge bg-info"><i class="fas fa-paperclip me-1"></i>Has Attachments</span>
                                        <?php endif; ?>
                                    </div>
                                    <td><?= htmlspecialchars($request['location_type'] ?? '-') ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></div>
                                    <td><?= htmlspecialchars($request['requester_name']) ?></div>
                                    <td><?= htmlspecialchars($request['hospital_order_no'] ?? '-') ?></div>
                                    <td>₱<?= number_format($request['amount'], 2) ?></div>
                                    <td>
                                        <?= $request['official_business'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                    </div>
                                    <td>
                                        <span title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                            <?php 
                                            $remarks = $request['remarks'] ?? '';
                                            echo htmlspecialchars(strlen($remarks) > 30 ? substr($remarks, 0, 30) . '...' : $remarks);
                                            ?>
                                        </span>
                                    </div>
                                    <td>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </div>
                                    <td>
                                        <div style="display: flex; flex-direction: row; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <?php if ($request['status'] === 'complete'): ?>
                                                <button class="btn-action btn-view" onclick="openViewTrainingModal(<?= $request['id'] ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-edit" onclick="openEditTrainingModal(<?= $request['id'] ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-reschedule" onclick="openRescheduleTrainingModal(<?= $request['id'] ?>)" title="Reschedule">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            <?php if ($has_attachments): ?>
                                                <button class="btn-action btn-view-attachment" onclick="openAttachmentsModal(<?= $request['id'] ?>)" title="View Attachments">
                                                    <i class="fas fa-paperclip"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete" onclick="deleteTrainingRequest(<?= $request['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                    <p class="text-muted mb-0">No external training requests found</p>
                                </div>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Training Request Modal -->
<div class="modal fade" id="addTrainingModal" tabindex="-1" aria-labelledby="addTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addTrainingLabel">
                    <i class="fas fa-plus-circle me-2"></i>New External Training Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTrainingForm">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Training Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required placeholder="Enter training title">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_start" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location Type</label>
                            <select name="location_type" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="Local">Local</option>
                                <option value="International">International</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_order_no" placeholder="e.g., HO-2024-001">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" placeholder="0.00">
                        </div>

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-md-12">
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="late_filing" name="late_filing" value="1">
                                    <label class="form-check-label" for="late_filing">Late Filing</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="official_business" name="official_business" value="1">
                                    <label class="form-check-label" for="official_business">Official Business</label>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="late_filing" value="0">
                        <input type="hidden" name="official_business" value="0">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Additional remarks..."></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="addTrainingBtn">
                            <i class="fas fa-save me-1"></i>Submit Request
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Training Request Modal -->
<div class="modal fade" id="editTrainingModal" tabindex="-1" aria-labelledby="editTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editTrainingLabel">
                    <i class="fas fa-edit me-2"></i>Edit External Training Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTrainingForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Training Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date Start</label>
                            <input type="date" class="form-control" name="date_start" id="edit_date_start" disabled style="background-color: #e9ecef;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date End</label>
                            <input type="date" class="form-control" name="date_end" id="edit_date_end" disabled style="background-color: #e9ecef;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location Type</label>
                            <select name="location_type" id="edit_location_type" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="Local">Local</option>
                                <option value="International">International</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_order_no" id="edit_hospital_order_no">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" id="edit_amount" step="0.01">
                        </div>

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-md-12">
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_late_filing" name="late_filing" value="1">
                                    <label class="form-check-label" for="edit_late_filing">Late Filing</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_official_business" name="official_business" value="1">
                                    <label class="form-check-label" for="edit_official_business">Official Business</label>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="late_filing" value="0">
                        <input type="hidden" name="official_business" value="0">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="2"></textarea>
                        </div>

                        <!-- Attachments Section -->
                        <div class="col-12" id="trainingAttachmentsSection">
                            <h6 class="mt-3 mb-3"><i class="fas fa-paperclip me-2"></i>Attachments (Upload after training ends)</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">PTR (Post Training Report)</label>
                                    <input type="file" class="form-control" name="ptr_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_ptr" class="current-file mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">COC (Certificate of Completion)</label>
                                    <input type="file" class="form-control" name="coc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_coc" class="current-file mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">COA (Certificate of Attendance)</label>
                                    <input type="file" class="form-control" name="coa_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_coa" class="current-file mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">MOM (Minutes of the Meeting)</label>
                                    <input type="file" class="form-control" name="mom_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_mom" class="current-file mt-1"></div>
                                </div>
                            </div>
                            <div id="uploadInfoMessage" class="alert alert-info mt-3 d-none">
                                <i class="fas fa-info-circle me-2"></i>Files can only be uploaded after the training end date.
                            </div>
                        </div>

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-12" id="approvalSection">
                            <div id="approveCheckboxSection">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_approve_status" name="approve_status" value="1">
                                    <label class="form-check-label" for="edit_approve_status">
                                        <strong>Approve this request</strong>
                                    </label>
                                </div>
                            </div>
                            <div id="completeButtonSection" style="display: none;">
                                <p class="mb-2"><strong>Status: APPROVED</strong></p>
                                <p class="mb-3 text-muted">User must upload at least one file (PTR, COC, COA, or MOM) before you can mark as complete.</p>
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="fas fa-check-circle me-1"></i>Mark as Complete
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="editTrainingBtn">
                            <i class="fas fa-save me-1"></i>Update Request
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Training Request Modal (Read-only for completed requests) -->
<div class="modal fade" id="viewTrainingModal" tabindex="-1" aria-labelledby="viewTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewTrainingLabel">
                    <i class="fas fa-eye me-2"></i>View External Training Request (Completed)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Title</label>
                        <p class="form-control-static" id="view_title">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Start</label>
                        <p class="form-control-static" id="view_date_start">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date End</label>
                        <p class="form-control-static" id="view_date_end">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Location Type</label>
                        <p class="form-control-static" id="view_location_type">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hospital Order No.</label>
                        <p class="form-control-static" id="view_hospital_order_no">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Amount (PHP)</label>
                        <p class="form-control-static" id="view_amount">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Official Business</label>
                        <p class="form-control-static" id="view_official_business">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Remarks</label>
                        <p class="form-control-static" id="view_remarks">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Status</label>
                        <p class="form-control-static" id="view_status">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Attachments</label>
                        <div id="view_attachments" style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
                            <!-- Loaded dynamically -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Training Request Modal -->
<div class="modal fade" id="rescheduleTrainingModal" tabindex="-1" aria-labelledby="rescheduleTrainingLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="rescheduleTrainingLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Reschedule Training
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rescheduleTrainingForm">
                    <input type="hidden" name="id" id="reschedule_id">
                   
                    <div class="mb-3">
                        <label class="form-label">New Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_start" id="reschedule_date_start" required>
                    </div>
                   
                    <div class="mb-3">
                        <label class="form-label">New End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_end" id="reschedule_date_end" required>
                    </div>
                   
                    <div class="mb-3">
                        <label class="form-label">Reschedule Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="resched_reason" id="reschedule_reason" rows="3" required placeholder="Please provide reason for rescheduling..."></textarea>
                    </div>
                   
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="rescheduleTrainingBtn">
                            <i class="fas fa-calendar-check me-1"></i> Submit Reschedule
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Attachments Modal -->
<div class="modal fade" id="viewAttachmentsModal" tabindex="-1" aria-labelledby="viewAttachmentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewAttachmentsLabel">
                    <i class="fas fa-paperclip me-2"></i>Training Attachments
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="attachmentsList" class="attachments-list">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p>Loading attachments...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Generate Report Modal -->
<?php if (is_admin() || is_superadmin()): ?>
<div class="modal fade" id="trainingReportModal" tabindex="-1" aria-labelledby="trainingReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="trainingReportLabel">
                    <i class="fas fa-chart-line me-2"></i>Completed External Trainings Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This report only shows external trainings with <strong>COMPLETED</strong> status.
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select id="reportYear" class="form-select">
                            <option value="">All Years</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select id="reportMonth" class="form-select">
                            <option value="">All Months</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division</label>
                        <select id="reportDivision" class="form-select">
                            <option value="">All Divisions</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select id="reportDepartment" class="form-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="exportReportBtn" class="btn btn-info w-100">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
               
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Location Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Requester</th>
                                <th>Division</th>
                                <th>Department</th>
                                <th>Hospital Order No.</th>
                                <th>Amount</th>
                                <th>Attachments</th>
                            </thead>
                        <tbody id="reportTableBody">
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                    <p>Loading data...</p>
                                </div>
                            </tr>
                        </tbody>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="toastContainer" class="toast-notification"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentTrainingId = null;

    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // Escape HTML function
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Format date function
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // Add Training Form Submit
    document.getElementById('addTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const startDate = document.querySelector('[name="date_start"]').value;
        const endDate = document.querySelector('[name="date_end"]').value;

        if (startDate && endDate) {
            if (new Date(endDate) < new Date(startDate)) {
                showToast('End date cannot be earlier than start date', 'danger');
                return false;
            }
        }

        const formData = new FormData(this);
        formData.append('add_training_request_ajax', '1');

        const btn = document.getElementById('addTrainingBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Submit Request';
        });
    });

    // Open Edit Modal
    function openEditTrainingModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_division');
        url.searchParams.delete('filter_dept');
        url.searchParams.delete('search');
        url.searchParams.set('get_training_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    
                    if (req.status === 'complete') {
                        showToast('Completed requests cannot be edited.', 'warning');
                        return;
                    }
                    
                    document.getElementById('edit_id').value = req.id;
                    document.getElementById('edit_title').value = req.title;
                    document.getElementById('edit_date_start').value = req.date_start;
                    document.getElementById('edit_date_end').value = req.date_end;
                    document.getElementById('edit_location_type').value = req.location_type || '';
                    document.getElementById('edit_hospital_order_no').value = req.hospital_order_no || '';
                    document.getElementById('edit_amount').value = req.amount || 0;
                    
                    const lateFilingCheckbox = document.getElementById('edit_late_filing');
                    if (lateFilingCheckbox) {
                        lateFilingCheckbox.checked = req.late_filing == 1;
                    }
                    
                    const officialBusinessCheckbox = document.getElementById('edit_official_business');
                    if (officialBusinessCheckbox) {
                        officialBusinessCheckbox.checked = req.official_business == 1;
                    }
                    
                    document.getElementById('edit_remarks').value = req.remarks || '';

                    // Show current files
                    document.getElementById('current_ptr').innerHTML = req.ptr_file ? `<a href="<?= BASE_URL ?>/uploads/training/${req.ptr_file}" target="_blank"><i class="fas fa-file me-1"></i>Current PTR</a>` : '';
                    document.getElementById('current_coc').innerHTML = req.coc_file ? `<a href="<?= BASE_URL ?>/uploads/training/${req.coc_file}" target="_blank"><i class="fas fa-file me-1"></i>Current COC</a>` : '';
                    document.getElementById('current_coa').innerHTML = req.coa_file ? `<a href="<?= BASE_URL ?>/uploads/training/${req.coa_file}" target="_blank"><i class="fas fa-file me-1"></i>Current COA</a>` : '';
                    document.getElementById('current_mom').innerHTML = req.mom_file ? `<a href="<?= BASE_URL ?>/uploads/training/${req.mom_file}" target="_blank"><i class="fas fa-file me-1"></i>Current MOM</a>` : '';

                    // Check if training has ended to allow file uploads
                    const endDate = new Date(req.date_end);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    endDate.setHours(0, 0, 0, 0);
                    const canUploadFiles = today > endDate;
                    
                    const fileInputs = document.querySelectorAll('#editTrainingForm input[type="file"]');
                    const uploadInfoMsg = document.getElementById('uploadInfoMessage');
                    
                    if (canUploadFiles) {
                        fileInputs.forEach(input => input.disabled = false);
                        if (uploadInfoMsg) uploadInfoMsg.classList.add('d-none');
                    } else {
                        fileInputs.forEach(input => input.disabled = true);
                        if (uploadInfoMsg) {
                            uploadInfoMsg.classList.remove('d-none');
                            uploadInfoMsg.innerHTML = `<i class="fas fa-info-circle me-2"></i>Files can only be uploaded after the training end date (${formatDate(req.date_end)}).`;
                        }
                    }

                    // Handle approval section for admins
                    <?php if (is_admin() || is_superadmin()): ?>
                    const isApproved = req.status === 'approved';
                    const approveSection = document.getElementById('approveCheckboxSection');
                    const completeSection = document.getElementById('completeButtonSection');
                    
                    if (isApproved) {
                        if (approveSection) approveSection.style.display = 'none';
                        if (completeSection) completeSection.style.display = 'block';
                        
                        // Check if files are attached for complete button
                        const hasFiles = req.ptr_file || req.coc_file || req.coa_file || req.mom_file;
                        const markCompleteBtn = document.getElementById('markCompleteBtn');
                        if (markCompleteBtn) {
                            if (hasFiles) {
                                markCompleteBtn.disabled = false;
                                markCompleteBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Mark as Complete';
                            } else {
                                markCompleteBtn.disabled = true;
                                markCompleteBtn.innerHTML = '<i class="fas fa-lock me-1"></i>Waiting for Files...';
                            }
                        }
                    } else {
                        if (approveSection) approveSection.style.display = 'block';
                        if (completeSection) completeSection.style.display = 'none';
                        const approveCheckbox = document.getElementById('edit_approve_status');
                        if (approveCheckbox) approveCheckbox.checked = false;
                    }
                    <?php endif; ?>

                    const modal = new bootstrap.Modal(document.getElementById('editTrainingModal'));
                    modal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast('Error: ' + error.message, 'danger');
            });
    }

    // Open View Modal (Read-only for completed requests)
    function openViewTrainingModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.set('get_training_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    
                    document.getElementById('view_title').innerHTML = escapeHtml(req.title);
                    document.getElementById('view_date_start').innerHTML = formatDate(req.date_start);
                    document.getElementById('view_date_end').innerHTML = formatDate(req.date_end);
                    document.getElementById('view_location_type').innerHTML = escapeHtml(req.location_type || '-');
                    document.getElementById('view_hospital_order_no').innerHTML = escapeHtml(req.hospital_order_no || '-');
                    document.getElementById('view_amount').innerHTML = '₱' + parseFloat(req.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    document.getElementById('view_official_business').innerHTML = req.official_business ? 'Yes' : 'No';
                    document.getElementById('view_remarks').innerHTML = escapeHtml(req.remarks || '-');
                    
                    const statusBadge = `<span class="status-badge status-${req.status}">${req.status.charAt(0).toUpperCase() + req.status.slice(1)}</span>`;
                    document.getElementById('view_status').innerHTML = statusBadge;
                    
                    // Build attachments list
                    const attachmentsDiv = document.getElementById('view_attachments');
                    let attachmentsHtml = '<div class="row g-3">';
                    let hasFiles = false;
                    
                    const files = [
                        { name: 'PTR (Post Training Report)', file: req.ptr_file },
                        { name: 'COC (Certificate of Completion)', file: req.coc_file },
                        { name: 'COA (Certificate of Attendance)', file: req.coa_file },
                        { name: 'MOM (Minutes of the Meeting)', file: req.mom_file }
                    ];
                    
                    files.forEach(file => {
                        if (file.file) {
                            hasFiles = true;
                            attachmentsHtml += `
                                <div class="col-md-6">
                                    <div class="attachment-card">
                                        <div class="attachment-icon">
                                            <i class="fas fa-file-alt fa-2x"></i>
                                        </div>
                                        <div class="attachment-info">
                                            <h6 class="attachment-title">${file.name}</h6>
                                            <p class="attachment-filename">${escapeHtml(file.file)}</p>
                                        </div>
                                        <a href="<?= BASE_URL ?>/uploads/training/${file.file}" class="btn btn-sm btn-primary" target="_blank" download>
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            `;
                        }
                    });
                    
                    attachmentsHtml += '</div>';
                    
                    if (!hasFiles) {
                        attachmentsDiv.innerHTML = '<em class="text-muted">No attachments uploaded.</em>';
                    } else {
                        attachmentsDiv.innerHTML = attachmentsHtml;
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('viewTrainingModal'));
                    modal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }

    // Open Attachments Modal
    function openAttachmentsModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.set('get_training_request', '1');
        url.searchParams.set('id', id);
        
        const attachmentsList = document.getElementById('attachmentsList');
        attachmentsList.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p>Loading attachments...</p>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('viewAttachmentsModal'));
        modal.show();
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    let attachmentsHtml = '<div class="row g-3">';
                    let hasFiles = false;
                    
                    const files = [
                        { name: 'PTR (Post Training Report)', file: req.ptr_file, icon: 'fa-file-alt' },
                        { name: 'COC (Certificate of Completion)', file: req.coc_file, icon: 'fa-file-pdf' },
                        { name: 'COA (Certificate of Attendance)', file: req.coa_file, icon: 'fa-file-image' },
                        { name: 'MOM (Minutes of the Meeting)', file: req.mom_file, icon: 'fa-file-word' }
                    ];
                    
                    files.forEach(file => {
                        if (file.file) {
                            hasFiles = true;
                            const fileExt = file.file.split('.').pop().toUpperCase();
                            attachmentsHtml += `
                                <div class="col-md-6">
                                    <div class="attachment-card">
                                        <div class="attachment-icon">
                                            <i class="fas ${file.icon} fa-2x"></i>
                                        </div>
                                        <div class="attachment-info">
                                            <h6 class="attachment-title">${file.name}</h6>
                                            <p class="attachment-filename">${escapeHtml(file.file)}</p>
                                            <span class="attachment-badge">${fileExt}</span>
                                        </div>
                                        <a href="<?= BASE_URL ?>/uploads/training/${file.file}" class="btn btn-sm btn-primary" target="_blank" download>
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            `;
                        }
                    });
                    
                    attachmentsHtml += '</div>';
                    
                    if (!hasFiles) {
                        attachmentsList.innerHTML = `
                            <div class="text-center py-5">
                                <i class="fas fa-paperclip fa-3x mb-3" style="color: #dee2e6;"></i>
                                <p class="text-muted">No attachments found for this training request.</p>
                            </div>
                        `;
                    } else {
                        attachmentsList.innerHTML = attachmentsHtml;
                    }
                } else {
                    attachmentsList.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'Error loading attachments'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                attachmentsList.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading attachments. Please try again.
                    </div>
                `;
            });
    }

    // Edit Form Submit
    document.getElementById('editTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('edit_training_request_ajax', '1');

        const btn = document.getElementById('editTrainingBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Update Request';
        });
    });

    // Mark Training as Complete (Admin only)
    document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
        if (confirm('Mark this training as complete? The requester will be able to submit new training requests.')) {
            const id = document.getElementById('edit_id').value;
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            const formData = new FormData();
            formData.append('complete_training_request_ajax', '1');
            formData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editTrainingModal'));
                    modal.hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'danger');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Mark as Complete';
            });
        }
    });

    // Open Reschedule Modal
    function openRescheduleTrainingModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.set('get_training_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    document.getElementById('reschedule_id').value = req.id;
                    document.getElementById('reschedule_date_start').value = req.date_start;
                    document.getElementById('reschedule_date_end').value = req.date_end;
                    document.getElementById('reschedule_reason').value = '';

                    const modal = new bootstrap.Modal(document.getElementById('rescheduleTrainingModal'));
                    modal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }

    // Reschedule Form Submit
    document.getElementById('rescheduleTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const startDate = document.getElementById('reschedule_date_start').value;
        const endDate = document.getElementById('reschedule_date_end').value;

        if (startDate && endDate) {
            if (new Date(endDate) < new Date(startDate)) {
                showToast('End date cannot be earlier than start date', 'danger');
                return false;
            }
        }

        const formData = new FormData(this);
        formData.append('reschedule_training_request_ajax', '1');

        const btn = document.getElementById('rescheduleTrainingBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Rescheduling...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('rescheduleTrainingModal'));
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-calendar-check me-1"></i> Submit Reschedule';
        });
    });

    // Delete Training Request
    function deleteTrainingRequest(id) {
        if (confirm('Are you sure you want to delete this training request?')) {
            const formData = new FormData();
            formData.append('delete_training_request', '1');
            formData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'danger');
            });
        }
    }

    // Department filter update
    function updateDepartmentFilter() {
        const divisionId = document.getElementById('filterDivision').value;
        const deptSelect = document.getElementById('filterDept');
        const currentDeptValue = deptSelect.value;
        
        const allDepts = document.querySelectorAll('#filterDept option[data-division-id]');
        
        let selectedOption = null;
        if (currentDeptValue) {
            selectedOption = Array.from(allDepts).find(opt => opt.value === currentDeptValue);
        }
        
        const allOption = deptSelect.querySelector('option:not([data-division-id])');
        deptSelect.innerHTML = '';
        deptSelect.appendChild(allOption);
        
        if (!divisionId) {
            allDepts.forEach(option => {
                deptSelect.appendChild(option.cloneNode(true));
            });
            if (selectedOption) {
                deptSelect.value = currentDeptValue;
            }
        } else {
            allDepts.forEach(option => {
                if (option.getAttribute('data-division-id') === divisionId) {
                    deptSelect.appendChild(option.cloneNode(true));
                }
            });
            if (selectedOption && selectedOption.getAttribute('data-division-id') === divisionId) {
                deptSelect.value = currentDeptValue;
            } else {
                deptSelect.value = '';
            }
        }
    }

    <?php if (is_admin() || is_superadmin()): ?>
    // Report Modal Functions
    let allDepartments = [];
    
    function loadTrainingFilterOptions() {
        fetch(`${window.location.pathname}?get_training_filter_options=1`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const yearSelect = document.getElementById('reportYear');
                    yearSelect.innerHTML = '<option value="">All Years</option>';
                    data.years.forEach(year => {
                        yearSelect.innerHTML += `<option value="${year}">${year}</option>`;
                    });
                    
                    const divisionSelect = document.getElementById('reportDivision');
                    divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                    data.divisions.forEach(division => {
                        divisionSelect.innerHTML += `<option value="${division.id}">${escapeHtml(division.name)}</option>`;
                    });
                    
                    allDepartments = data.departments;
                }
            })
            .catch(error => console.error('Error loading filter options:', error));
    }
    
    function updateReportDepartments() {
        const divisionId = document.getElementById('reportDivision').value;
        const deptSelect = document.getElementById('reportDepartment');
        
        if (!divisionId) {
            deptSelect.innerHTML = '<option value="">All Departments</option>';
            return;
        }
        
        const filteredDepts = allDepartments.filter(dept => dept.department_id == divisionId);
        deptSelect.innerHTML = '<option value="">All Departments</option>';
        filteredDepts.forEach(dept => {
            deptSelect.innerHTML += `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`;
        });
    }
    
    function loadTrainingReportData() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
        const division_id = document.getElementById('reportDivision').value;
        const dept_id = document.getElementById('reportDepartment').value;
        
        let url = `${window.location.pathname}?get_training_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        if (division_id) url += `&division_id=${division_id}`;
        if (dept_id) url += `&dept_id=${dept_id}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('reportTableBody');
                if (data.success && data.reports.length > 0) {
                    tbody.innerHTML = '';
                    data.reports.forEach(report => {
                        const attachments = [];
                        if (report.ptr_file) attachments.push('PTR');
                        if (report.coc_file) attachments.push('COC');
                        if (report.coa_file) attachments.push('COA');
                        if (report.mom_file) attachments.push('MOM');
                        const attachmentsText = attachments.length > 0 ? attachments.join(', ') : '—';
                        
                        tbody.innerHTML += `
                            <tr>
                                <td><strong>${escapeHtml(report.title)}</strong></div>
                                <td>${escapeHtml(report.venue || '-')}</div>
                                <td>${escapeHtml(report.date_start)}</div>
                                <td>${escapeHtml(report.date_end)}</div>
                                <td>${escapeHtml(report.requester_name)}</div>
                                <td>${escapeHtml(report.division_name || '—')}</div>
                                <td>${escapeHtml(report.department_name || '—')}</div>
                                <td>${escapeHtml(report.hospital_order_no || '—')}</div>
                                <td>₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                <td>${attachmentsText}</div>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                <p class="text-muted mb-0">No completed external training requests found</p>
                            </div>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading report data:', error);
                document.getElementById('reportTableBody').innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <p>Error loading data. Please try again.</p>
                        </div>
                    </tr>
                `;
            });
    }
    
    function exportTrainingReportToCSV() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
        const division_id = document.getElementById('reportDivision').value;
        const dept_id = document.getElementById('reportDepartment').value;
        
        let url = `${window.location.pathname}?get_training_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        if (division_id) url += `&division_id=${division_id}`;
        if (dept_id) url += `&dept_id=${dept_id}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.reports.length > 0) {
                    let csvContent = "Title,Location Type,From,To,Requester,Division,Department,Hospital Order No.,Amount,Attachments\n";
                    
                    data.reports.forEach(report => {
                        const attachments = [];
                        if (report.ptr_file) attachments.push('PTR');
                        if (report.coc_file) attachments.push('COC');
                        if (report.coa_file) attachments.push('COA');
                        if (report.mom_file) attachments.push('MOM');
                        const attachmentsText = attachments.length > 0 ? attachments.join(', ') : '—';
                        
                        csvContent += `"${escapeCsv(report.title)}","${escapeCsv(report.venue || '-')}","${report.date_start}","${report.date_end}","${escapeCsv(report.requester_name)}","${escapeCsv(report.division_name || '—')}","${escapeCsv(report.department_name || '—')}","${escapeCsv(report.hospital_order_no || '—')}","${report.amount}","${attachmentsText}"\n`;
                    });
                    
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `external_completed_trainings_${new Date().toISOString().slice(0,10)}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    showToast('Report exported successfully!', 'success');
                } else {
                    showToast('No data to export', 'warning');
                }
            })
            .catch(error => {
                console.error('Error exporting report:', error);
                showToast('Error exporting report', 'danger');
            });
    }
    
    function escapeCsv(str) {
        if (!str) return '';
        return str.replace(/"/g, '""');
    }
    
    document.getElementById('reportYear')?.addEventListener('change', loadTrainingReportData);
    document.getElementById('reportMonth')?.addEventListener('change', loadTrainingReportData);
    document.getElementById('reportDivision')?.addEventListener('change', function() {
        updateReportDepartments();
        loadTrainingReportData();
    });
    document.getElementById('reportDepartment')?.addEventListener('change', loadTrainingReportData);
    document.getElementById('exportReportBtn')?.addEventListener('click', exportTrainingReportToCSV);
    
    document.getElementById('trainingReportModal')?.addEventListener('show.bs.modal', function() {
        loadTrainingFilterOptions();
        setTimeout(() => {
            updateReportDepartments();
            loadTrainingReportData();
        }, 100);
    });
    <?php endif; ?>
    
    // Initialize department filter
    document.addEventListener('DOMContentLoaded', function() {
        const filterDivision = document.getElementById('filterDivision');
        if (filterDivision) {
            updateDepartmentFilter();
            filterDivision.addEventListener('change', updateDepartmentFilter);
        }
    });
</script>

</body>
</html>