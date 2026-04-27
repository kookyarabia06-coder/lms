<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Use PDO for consistency with your LMS
$pdo = $pdo; // Use the existing PDO connection

$current_user_id = $_SESSION['user']['id'];

// Initialize variables
$success_message = '';
$error_message = '';
$filter_month = '';

// Handle AJAX Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Check if user has permission to delete (only admin/superadmin or the requester)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        // Force training type to External - this page only handles external training
        $training_type = 'External';
        $title = trim($_POST['title'] ?? '');
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_id = trim($_POST['hospital_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        
        $errors = [];
        if (empty($title)) $errors[] = "Title is required";
        
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
            $hospital_id,
            $amount,
            $late_filing,
            $official_business,
            $remarks,
            $requester_id
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        // Return the new request data to append to table
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
                'hospital_order_no' => $hospital_id,
                'amount' => $amount,
                'official_business' => $official_business,
                'late_filing' => $late_filing,
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        // Force training type to External
        $training_type = 'External';
        $title = trim($_POST['title'] ?? '');
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_id = trim($_POST['hospital_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        $approve_status = isset($_POST['approve_status']) ? 1 : 0;
        
        $errors = [];
        if (empty($title)) $errors[] = "Title is required";
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        // Check if training has ended before allowing file uploads
        $stmt = $pdo->prepare("SELECT date_end, status FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $training_data = $stmt->fetch();
        $current_date = new DateTime();
        $end_date = new DateTime($training_data['date_end']);
        $has_training_ended = $current_date >= $end_date;
        
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
        
        $ptr_file = null;
        $coc_file = null;
        $mom_file = null;
        
        // Only allow file uploads if training has ended
        if ($has_training_ended) {
            $ptr_file = uploadTrainingFile('ptr_file');
            $coc_file = uploadTrainingFile('coc_file');
            $mom_file = uploadTrainingFile('mom_file');
        }
        
        // Check if any file was uploaded
        $has_new_attachments = ($ptr_file !== null || $coc_file !== null || $mom_file !== null);
        
        // Start building the update query
        $sql = "UPDATE training_requests SET 
            training_type = ?, title = ?, 
            location_type = ?, hospital_order_no = ?, amount = ?, 
            late_filing = ?, official_business = ?, remarks = ?";
        $params = [$training_type, $title, $location_type, $hospital_id, $amount, $late_filing, $official_business, $remarks];
        
        if ($ptr_file) {
            $sql .= ", ptr_file = ?";
            $params[] = $ptr_file;
        }
        if ($coc_file) {
            $sql .= ", coc_file = ?";
            $params[] = $coc_file;
        }
        if ($mom_file) {
            $sql .= ", mom_file = ?";
            $params[] = $mom_file;
        }
        
        // If files were uploaded AND status is 'pending', change to 'submitted'
        if ($has_new_attachments) {
            // First check current status
            $check_stmt = $pdo->prepare("SELECT status FROM training_requests WHERE id = ?");
            $check_stmt->execute([$id]);
            $current_status = $check_stmt->fetchColumn();
            
            // If current status is 'pending' and user uploaded files, change to 'submitted'
            if ($current_status === 'pending') {
                $sql .= ", status = 'submitted'";
            }
        }
        
        // Update status if approved (only admin/superadmin can approve)
        if ($approve_status && (is_admin() || is_superadmin())) {
            $sql .= ", status = 'approved'";
        }
        
        $sql .= ", updated_at = NOW() WHERE id = ?";
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

// Handle AJAX Reschedule Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_request_ajax'])) {
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
        
        // Update dates directly (overwrite original) and store reason
        $stmt = $pdo->prepare("UPDATE training_requests SET 
            date_start = ?, date_end = ?, resched_reason = ?, status = 'pending' 
            WHERE id = ?");
        $stmt->execute([$date_start, $date_end, $resched_reason, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request rescheduled successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Mark Request as Complete (requires BOTH PTR and COC)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Only admin/superadmin can mark as complete
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to mark requests as complete']);
            exit;
        }
        
        // Check if request exists, has attachments, and has valid status
        $stmt = $pdo->prepare("SELECT status, ptr_file, coc_file, mom_file, date_end FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        // Check if training end date has passed
        $current_date = new DateTime();
        $end_date = new DateTime($request['date_end']);
        
        if ($current_date < $end_date) {
            echo json_encode(['success' => false, 'message' => 'Cannot mark as complete: Training end date has not yet passed.']);
            exit;
        }
        
        // Check if request is submitted or approved
        if ($request['status'] !== 'approved' && $request['status'] !== 'submitted') {
            echo json_encode(['success' => false, 'message' => 'Only approved or submitted requests can be marked as complete']);
            exit;
        }
        
        // BOTH PTR and COC must be uploaded
        if (empty($request['ptr_file'])) {
            echo json_encode(['success' => false, 'message' => 'PTR (Post Training Report) is required. Please upload the PTR file before marking as complete.']);
            exit;
        }
        
        if (empty($request['coc_file'])) {
            echo json_encode(['success' => false, 'message' => 'COC (Certificate of Completion) is required. Please upload the COC file before marking as complete.']);
            exit;
        }
        
        // Update status to complete
        $stmt = $pdo->prepare("UPDATE training_requests SET status = 'complete', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request marked as complete successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Request Data for Edit
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'request' => $request]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Filtered Report Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_report_data'])) {
    header('Content-Type: application/json');
    try {
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        $division_id = isset($_GET['division_id']) && !empty($_GET['division_id']) ? (int)$_GET['division_id'] : null;
        $dept_id = isset($_GET['dept_id']) && !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $type = isset($_GET['type']) && !empty($_GET['type']) ? $_GET['type'] : null;
        
        $where_clauses = ["tr.status = 'approved'", "tr.training_type = 'external'"];
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
        
        $where_sql = implode(" AND ", $where_clauses);
        
        $query = "
            SELECT 
                tr.id,
                tr.title,
                tr.training_type,
                DATE_FORMAT(tr.date_start, '%M %d, %Y') as date_start,
                DATE_FORMAT(tr.date_end, '%M %d, %Y') as date_end,
                CONCAT(u.fname, ' ', u.lname) as requester_name,
                u.username,
                d.name as division_name,
                dept.name as department_name,
                tr.hospital_order_no,
                tr.amount,
                tr.status
            FROM training_requests tr
            LEFT JOIN users u ON tr.requester_id = u.id
            LEFT JOIN user_departments ud ON ud.user_id = u.id
            LEFT JOIN depts dept ON ud.dept_id = dept.id
            LEFT JOIN departments d ON dept.department_id = d.id
            WHERE $where_sql
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

// Handle AJAX Get Filter Options
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_filter_options'])) {
    header('Content-Type: application/json');
    try {
        // Get available years from approved requests
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM training_requests WHERE status = 'approved' ORDER BY year DESC");
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

// Handle filter
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : '';
$filter_type = isset($_GET['filter_type']) && !empty($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_status = isset($_GET['filter_status']) && !empty($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_division = isset($_GET['filter_division']) && !empty($_GET['filter_division']) ? (int)$_GET['filter_division'] : '';
$filter_dept = isset($_GET['filter_dept']) && !empty($_GET['filter_dept']) ? (int)$_GET['filter_dept'] : '';

$where_clause = [];
$params = [];

// Always filter for external training only
$where_clause[] = "tr.training_type = 'external'";

// Add user filter - regular users see only their requests, admins see all
if (!is_admin() && !is_superadmin()) {
    $where_clause[] = "tr.requester_id = ?";
    $params[] = $current_user_id;
}

// Apply additional filters
if (!empty($filter_year)) {
    $where_clause[] = "YEAR(tr.date_start) = ?";
    $params[] = $filter_year;
}

if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $where_clause[] = "MONTH(tr.date_start) = ?";
    $params[] = $filter_month;
}

if (!empty($filter_status)) {
    $where_clause[] = "tr.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_division)) {
    $where_clause[] = "d.id = ?";
    $params[] = $filter_division;
}

if (!empty($filter_dept)) {
    $where_clause[] = "dept.id = ?";
    $params[] = $filter_dept;
}

$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";

// Fetch training requests with division/department joins
$query = "SELECT 
    tr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    d.name as division_name,
    dept.name as department_name
    FROM training_requests tr
    LEFT JOIN users u ON tr.requester_id = u.id
    LEFT JOIN user_departments ud ON ud.user_id = u.id
    LEFT JOIN depts dept ON ud.dept_id = dept.id
    LEFT JOIN departments d ON dept.department_id = d.id
    $where_sql
    ORDER BY tr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$training_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_where_clause = [];
$stats_params = [];

if (!is_admin() && !is_superadmin()) {
    $stats_where_clause[] = "requester_id = ?";
    $stats_params[] = $current_user_id;
}

if (!empty($filter_year)) {
    $stats_where_clause[] = "YEAR(date_start) = ?";
    $stats_params[] = $filter_year;
} else {
    $stats_where_clause[] = "YEAR(date_start) = YEAR(CURDATE())";
}

if (!empty($filter_month)) {
    $stats_where_clause[] = "MONTH(date_start) = ?";
    $stats_params[] = $filter_month;
}

if (!empty($filter_type)) {
    $stats_where_clause[] = "training_type = ?";
    $stats_params[] = $filter_type;
}

if (!empty($filter_status)) {
    $stats_where_clause[] = "status = ?";
    $stats_params[] = $filter_status;
}

$stats_where_sql = !empty($stats_where_clause) ? "WHERE " . implode(" AND ", $stats_where_clause) : "";

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN training_type = 'External' THEN 1 ELSE 0 END) as external_count,
    COALESCE(SUM(amount), 0) as total_amount  
    FROM training_requests tr 
    LEFT JOIN user_departments ud ON ud.user_id = tr.requester_id
    LEFT JOIN depts dept ON ud.dept_id = dept.id
    LEFT JOIN departments d ON dept.department_id = d.id
    $stats_where_sql";

$stmt = $pdo->prepare($stats_query);
$stmt->execute($stats_params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stats) {
    $stats = ['total' => 0, 'internal_count' => 0, 'external_count' => 0, 'total_amount' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Request Management - LMS</title>
    <link href="<?= BASE_URL ?>/assets/css/training.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .btn-view {
            background-color: #049925;
            color: white;
        }
        .btn-view:hover {
            background-color: #026818;
            color: white;
        }
        .view-details-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .view-details-card h6 {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .view-details-card p, .view-details-card div {
            font-size: 1rem;
            margin-bottom: 15px;
            word-break: break-word;
        }
        .attachment-list-view {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .attachment-item-view {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
        }
        .attachment-item-view:hover {
            background: #f8f9fa;
            border-color: #cbd5e0;
        }
        .attachment-item-view i {
            font-size: 1.5rem;
            color: #17a2b8;
        }
        .attachment-item-view .file-info {
            flex: 1;
        }
        .attachment-item-view .file-name {
            font-weight: 500;
            margin-bottom: 0;
        }
        .attachment-item-view .file-size {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 0;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content">
    <div class="container-fluid">
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header with Request Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0">Training Request Management</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#trainingRequestModal">
                <i class="fas fa-plus me-2"></i>Request Training
            </button>
        </div>
        
        <!-- Filter Section with Search -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-row" id="filterForm">
                <div class="filter-group">
                    <label class="form-label">Year</label>
                    <select name="filter_year" class="form-select" id="filterYear">
                        <option value="">All Years</option>
                        <?php 
                        $current_year = (int)date('Y');
                        for ($i = $current_year; $i >= $current_year - 5; $i--): 
                        ?>
                            <option value="<?= $i ?>" <?= ($filter_year == $i) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Month</label>
                    <select name="filter_month" class="form-select" id="filterMonth">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Status</label>
                    <select name="filter_status" class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="submitted" <?= ($filter_status == 'submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="complete" <?= ($filter_status == 'complete') ? 'selected' : '' ?>>Complete</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Division</label>
                    <select name="filter_division" class="form-select" id="filterDivision" onchange="updateDepartmentFilter()">
                        <option value="">All Divisions</option>
                        <?php 
                        try {
                            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                            $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($divisions as $div): 
                        ?>
                                <option value="<?= $div['id'] ?>" <?= ($filter_division == $div['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div['name']) ?>
                                </option>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {}
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Department</label>
                    <select name="filter_dept" class="form-select" id="filterDept">
                        <option value="">All Departments</option>
                        <?php 
                        try {
                            $stmt = $pdo->query("SELECT id, name, department_id FROM depts ORDER BY name");
                            $all_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($all_departments as $dept): 
                        ?>
                                <option value="<?= $dept['id'] ?>" 
                                        data-division-id="<?= $dept['department_id'] ?>"
                                        <?= ($filter_dept == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {}
                        ?>
                    </select>
                </div>

                <div class="search-group">
                    <label class="form-label">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by title, type, order no., remarks...">
                </div>

                <div class="filter-group">
                    <label class="form-label">PTR STATUS</label>
                    <select class="form-select" id="nonSubmissionFilter">
                        <option value="all">All</option>
                        <option value="none">Submitted (No Alerts)</option>
                        <option value="orange"><i class="fas fa-circle" style="color: #ffc107;"></i> Non Submission - Warning (45+ days)</option>
                        <option value="red"><i class="fas fa-circle" style="color: #dc3545;"></i> Non Submission - Urgent (60+ days)</option>
                        <option value="any">Non Submission - Any Alert</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-label">Total Training Requests:</span>
                <span class="stat-number" id="totalCount"><?= number_format($stats['total'] ?? 0) ?></span>
            </div>
           
            <div class="stat-item">
                <span class="stat-label">External Training:</span>
                <span class="stat-number"><?= number_format($stats['external_count'] ?? 0) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Total Amount:</span>
                <span class="stat-number">₱<?= number_format($stats['total_amount'] ?? 0, 2) ?></span>
            </div>
        </div>
        
        <!-- Training Requests List -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-list"></i> Training Requests List</h4>
                    <?php if (is_admin() || is_superadmin()): ?>
                    <button class="btn btn-success" id="generateReportBtn" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="fas fa-chart-line me-2"></i>Generate Report
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="trainingTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Requester</th>
                            <th>Hospital Order No.</th>
                            <th>Amount</th>
                            <th>Is OB</th>
                            <th>Late Filing</th>
                            <th>Remarks</th>
                            <th>Resched Reason</th>
                            <th>PTR Status</th>
                            <th>Status</th>
                            <th>Actions</th>
                         </thead>
                    <tbody id="trainingTableBody">
                        <?php if (!empty($training_requests)): ?>
                            <?php foreach ($training_requests as $request): ?>
                                <?php 
                                // Check if attachments exist
                                $has_ptr = !empty($request['ptr_file']);
                                $has_coc = !empty($request['coc_file']);
                                $has_mom = !empty($request['mom_file']);
                                $has_attachments = $has_ptr || $has_coc || $has_mom;
                                $has_both_ptr_coc = $has_ptr && $has_coc;
                                $is_completed = $request['status'] === 'complete';
                                
                                $end_date = new DateTime($request['date_end']);
                                $current_date = new DateTime();
                                $days_elapsed = $current_date->diff($end_date)->days;
                                
                                // Check if attachments are reviewed/complete
                                $attachments_reviewed = ($request['status'] === 'approved' && $has_attachments) || $is_completed;
                                
                                $reminder_level = 'none';
                                $reminder_text = '';
                                $bg_color = '';
                                $border_color = '';
                                $badge_class = '';
                                
                                if (!$has_attachments && !$attachments_reviewed && !$is_completed) {
                                    if ($days_elapsed >= 60) {
                                        $reminder_level = 'red';
                                        $reminder_text = "Warning: No attachments ({$days_elapsed}+ days)";
                                        $bg_color = '#ffe6e6';
                                        $border_color = '#dc3545';
                                        $badge_class = 'badge-secondary';
                                    } elseif ($days_elapsed >= 45) {
                                        $reminder_level = 'orange';
                                        $reminder_text = "Warning: No attachments ({$days_elapsed}+ days)";
                                        $bg_color = '#fff3cd';
                                        $border_color = '#ffc107';
                                        $badge_class = 'badge-secondary';
                                    }
                                }
                                ?>
                                <tr data-id="<?= $request['id'] ?>"
                                    data-title="<?= strtolower(htmlspecialchars($request['title'])) ?>" 
                                    data-type="<?= strtolower(htmlspecialchars($request['training_type'])) ?>"
                                    data-order="<?= strtolower(htmlspecialchars($request['hospital_order_no'])) ?>"
                                    data-remarks="<?= strtolower(htmlspecialchars($request['remarks'] ?? '')) ?>"
                                    data-resched="<?= strtolower(htmlspecialchars($request['resched_reason'] ?? '')) ?>"
                                    data-has-notification="<?= $reminder_level !== 'none' ? '1' : '0' ?>"
                                    data-reminder-level="<?= $reminder_level ?>"
                                    data-end-date="<?= $request['date_end'] ?>"
                                    data-has-attachments="<?= $has_attachments ? '1' : '0' ?>"
                                    data-has-ptr="<?= $has_ptr ? '1' : '0' ?>"
                                    data-has-coc="<?= $has_coc ? '1' : '0' ?>"
                                    data-status="<?= $request['status'] ?>">
                                   
                                    <td <?= $reminder_level !== 'none' ? "style=\"background-color: $bg_color; border-left: 4px solid $border_color;\"" : '' ?>>
                                        <strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if ($reminder_level !== 'none'): ?>
                                        <br><span class="badge <?= $badge_class ?> training-warning-badge" title="<?= $reminder_text ?>"><i class="fas fa-exclamation-circle me-1"></i><?= $reminder_text ?></span>
                                        <?php endif; ?>
                                     </div>
                                    <td>
                                        <span class="badge <?= $request['training_type'] == 'Internal' ? 'badge-info' : 'badge-warning' ?>">
                                            <?= htmlspecialchars($request['training_type']) ?>
                                        </span>
                                      </div>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></div>
                                    <td><?= htmlspecialchars($request['requester_name'] ?? 'N/A') ?></div>
                                    <td><?= htmlspecialchars($request['hospital_order_no']) ?></div>
                                    <td>₱<?= number_format($request['amount'], 2) ?></div>
                                    <td>
                                        <?= $request['official_business'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?>
                                      </div>
                                    <td>
                                        <?php if ($request['late_filing']): ?>
                                            <span class="badge" style="background-color: #ff69b4; color: white; border-radius: 4px; padding: 3px 5px;">
                                                Yes
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background-color: #6c757d; color: white; border-radius: 4px; padding: 3px 5px;">
                                                No
                                            </span>
                                        <?php endif; ?>
                                      </div>
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                        <?php 
                                        $remarks = $request['remarks'] ?? '';
                                        echo htmlspecialchars(strlen($remarks) > 30 ? substr($remarks, 0, 30) . '...' : $remarks);
                                        ?>
                                      </div>
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['resched_reason'] ?? '') ?>">
                                        <?php 
                                        $resched_reason = $request['resched_reason'] ?? '';
                                        echo htmlspecialchars(strlen($resched_reason) > 30 ? substr($resched_reason, 0, 30) . '...' : $resched_reason);
                                        ?>
                                      </div>
                                    <td>
                                        <?php
                                        // Determine PTR status based on attachments
                                        if ($is_completed) {
                                            $ptr_status = 'Completed';
                                            $ptr_badge_class = 'badge-success';
                                            $ptr_icon = 'fa-check-circle';
                                        } elseif ($has_ptr && $has_coc) {
                                            $ptr_status = 'PTR/COC';
                                            $ptr_badge_class = 'badge-success';
                                            $ptr_icon = 'fa-check-circle';
                                        } elseif ($has_ptr) {
                                            $ptr_status = 'PTR Only';
                                            $ptr_badge_class = 'badge-info';
                                            $ptr_icon = 'fa-upload';
                                        } elseif ($has_coc) {
                                            $ptr_status = 'COC Only';
                                            $ptr_badge_class = 'badge-info';
                                            $ptr_icon = 'fa-upload';
                                        } elseif ($request['status'] === 'rejected') {
                                            $ptr_status = 'Rejected';
                                            $ptr_badge_class = 'badge-danger';
                                            $ptr_icon = 'fa-times-circle';
                                        } else {
                                            $ptr_status = 'Pending';
                                            $ptr_badge_class = 'badge-warning';
                                            $ptr_icon = 'fa-hourglass-half';
                                        }
                                        ?>
                                        <span class="badge <?= $ptr_badge_class ?>">
                                            <i class="fas <?= $ptr_icon ?> me-1"></i><?= $ptr_status ?>
                                        </span>
                                      </div>
                                    <td>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                      </div>
                                    <td class="action-buttons" style="position: relative; z-index: 10; pointer-events: auto;">
                                        <?php if ($is_completed): ?>
                                            <button class="btn-action btn-view" onclick="openViewModal(<?= $request['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                                <span>View</span>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-edit" onclick="openEditModal(<?= $request['id'] ?>)" title="Edit Request">
                                                <i class="fas fa-edit"></i>
                                                <span>Edit</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if((is_admin() || is_superadmin()) && !$is_completed): ?>
                                        <button class="btn-action btn-reschedule" onclick="openRescheduleModal(<?= $request['id'] ?>)" title="Reschedule Request">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Reschedule</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($has_attachments): ?>
                                        <button class="btn-action btn-view-attachment" onclick="openAttachmentModal(<?= $request['id'] ?>)" title="View Attachments">
                                            <i class="fas fa-paperclip"></i>
                                            <span>View Attachments</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (!$is_completed): ?>
                                        <button class="btn-action btn-delete" onclick="deleteRequest(<?= $request['id'] ?>, this)" title="Delete Request">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
                                        </button>
                                        <?php endif; ?>
                                      </div>
                                  </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyStateRow">
                                <td colspan="14" class="text-center py-5">
                                    <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                    <p class="text-muted mb-0">No training requests found</p>
                                  </div>
                              </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Training Request Modal -->
<div class="modal fade" id="trainingRequestModal" tabindex="-1" aria-labelledby="trainingRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trainingRequestModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Training Request Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="trainingFormModal">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Type <span class="text-danger">*</span></label>
                            <select name="training_type" class="form-select" required disabled>
                                <option value="External" selected>External Training Only</option>
                            </select>
                            <small class="text-muted">This form is for external training requests only</small>
                        </div>
                        
                        <div class="col-md-6">
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
                                <option value="">--Select One--</option>
                                <option value="local">Local</option>
                                <option value="international">International</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_id" placeholder="e.g., HOSP-2024-001">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" placeholder="0.00">
                        </div>
                        <?php if (is_admin() || is_superadmin()): ?>  
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-4">
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
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" placeholder="Additional remarks..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="submitRequestBtn">
                            <i class="fas fa-paper-plane me-1"></i> Submit Request
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

<!-- Edit Training Request Modal -->
<div class="modal fade" id="editRequestModal" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRequestModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Training Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editFormModal" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" id="edit_created_at">
                    
                    <!-- Attachment Reminder Alert -->
                    <div id="attachmentReminderAlert" class="alert alert-dismissible fade show d-none" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Type <span class="text-danger">*</span></label>
                            <select name="training_type" id="edit_training_type" class="form-select" required disabled>
                                <option value="External" selected>External Training Only</option>
                            </select>
                            <small class="text-muted">This form is for external training requests only</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Training Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_start" id="edit_date_start" required disabled style="background-color: #babdc1;">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" id="edit_date_end" required disabled style="background-color: #babdc1;">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Location Type</label>
                            <select name="location_type" id="edit_location_type" class="form-select">
                                <option value="">--Select One--</option>
                                <option value="local">Local</option>
                                <option value="international">International</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_id" id="edit_hospital_id">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" id="edit_amount" step="0.01">
                        </div>

                        <!-- Approve Section (Admin Only) -->
                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-4">
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

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-12">
                            <div class="card bg-light p-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_approve_status" name="approve_status" value="1">
                                    <label class="form-check-label fw-bold" for="edit_approve_status">Approve this request</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mark as Complete Button (only visible when approved or submitted AND training has ended AND both PTR and COC exist) -->
                        <div class="col-12" id="markCompleteContainer" style="display: none;">
                            <div class="card bg-success bg-opacity-10 border-success p-3">
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="fas fa-check-circle me-2"></i> Mark as Complete
                                </button>
                                <small class="d-block mt-2 text-muted completion-help-text"></small>
                            </div>
                        </div>
                        <?php endif; ?>

                         <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        
                        <!-- Attachments Section -->
                        <div class="col-12" id="attachmentsSection">
                            <h6 class="mt-3 mb-3"><i class="fas fa-paperclip me-2"></i>Attachments</h6>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Both PTR (Post Training Report) and COC (Certificate of Completion) are required to mark this training as complete.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">PTR (Post Training Report) <span class="text-danger">*Required for completion</span></label>
                                    <input type="file" class="form-control attachment-input" name="ptr_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_ptr" class="current-file"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Certificates (Attendance/Completion) <span class="text-danger">*Required for completion</span></label>
                                    <input type="file" class="form-control attachment-input" name="coc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_coc" class="current-file"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">MOM (Minutes of the Meeting) <span class="text-muted">(Optional)</span></label>
                                    <input type="file" class="form-control attachment-input" name="mom_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_mom" class="current-file"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="updateRequestBtn">
                            <i class="fas fa-save me-1"></i> Update Request
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

<!-- View Training Request Modal (Read-Only) -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewRequestModalLabel">
                    <i class="fas fa-eye me-2"></i>Training Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Training Request Modal -->
<div class="modal fade" id="rescheduleRequestModal" tabindex="-1" aria-labelledby="rescheduleRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rescheduleRequestModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Reschedule Training Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rescheduleFormModal">
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
                        <button type="submit" class="btn btn-primary" id="rescheduleRequestBtn">
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
<div class="modal fade" id="viewAttachmentsModal" tabindex="-1" aria-labelledby="viewAttachmentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAttachmentsModalLabel">
                    <i class="fas fa-paperclip me-2"></i>Attachments
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="reportModalLabel">
                    <i class="fas fa-chart-line me-2"></i>Approved Training Requests Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Filter Section -->
                <div class="row g-3 mb-4">
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select id="reportYear" class="form-select">
                            <option value="">All Years</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select id="reportMonth" class="form-select">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
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
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select id="reportType" class="form-select">
                            <option value="">All Types</option>
                            <option value="Internal">Internal</option>
                            <option value="External">External</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="exportReportBtn" class="btn btn-success w-100">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
                
                <!-- Results Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Requester</th>
                                <th>Division</th>
                                <th>Department</th>
                                <th>Hospital Order No.</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </thead>
                            <tbody id="reportTableBody">
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                        <p>Loading data...</p>
                                    </td>
                                  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="toastContainer" class="toast-notification"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.getElementById('toastContainer');
        container.innerHTML = '';
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    // Update Department filter based on selected Division
    function updateDepartmentFilter() {
        const divisionId = document.getElementById('filterDivision').value;
        const deptSelect = document.getElementById('filterDept');
        const currentDeptValue = deptSelect.value;
        
        // Get all departments from data attributes
        const allDepts = document.querySelectorAll('#filterDept option[data-division-id]');
        
        // Store currently selected value to restore if valid
        let selectedOption = null;
        if (currentDeptValue) {
            selectedOption = Array.from(allDepts).find(opt => opt.value === currentDeptValue);
        }
        
        // Clear current options (keep the "All Departments" option)
        const allOption = deptSelect.querySelector('option:not([data-division-id])');
        deptSelect.innerHTML = '';
        deptSelect.appendChild(allOption);
        
        if (!divisionId) {
            // Show all departments
            allDepts.forEach(option => {
                deptSelect.appendChild(option.cloneNode(true));
            });
            // Restore selection if it exists
            if (selectedOption) {
                deptSelect.value = currentDeptValue;
            }
        } else {
            // Show only departments for selected division
            allDepts.forEach(option => {
                if (option.getAttribute('data-division-id') === divisionId) {
                    deptSelect.appendChild(option.cloneNode(true));
                }
            });
            // Restore selection only if it matches the new division filter
            if (selectedOption && selectedOption.getAttribute('data-division-id') === divisionId) {
                deptSelect.value = currentDeptValue;
            } else {
                deptSelect.value = '';
            }
        }
    }
    
    // Initialize department filter on page load
    document.addEventListener('DOMContentLoaded', function() {
        const filterDivision = document.getElementById('filterDivision');
        if (filterDivision) {
            updateDepartmentFilter();
            filterDivision.addEventListener('change', updateDepartmentFilter);
        }
    });
    
    // Open View Modal (Read-Only for Completed Requests)
    function openViewModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
        const modalBody = document.getElementById('viewModalBody');
        
        modalBody.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p class="mt-2">Loading details...</p>
            </div>
        `;
        
        modal.show();
        
        fetch(`${window.location.href}?get_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const r = data.request;
                    const hasPtr = !!r.ptr_file;
                    const hasCoc = !!r.coc_file;
                    const hasMom = !!r.mom_file;
                    
                    let attachmentsHtml = '';
                    if (hasPtr || hasCoc || hasMom) {
                        attachmentsHtml = '<div class="view-details-card"><h6><i class="fas fa-paperclip me-2"></i>Attachments</h6><div class="attachment-list-view">';
                        
                        if (hasPtr) {
                            const fileUrl = `<?= BASE_URL ?>/uploads/training/${r.ptr_file}`;
                            attachmentsHtml += `
                                <div class="attachment-item-view">
                                    <i class="fas fa-file-alt"></i>
                                    <div class="file-info">
                                        <p class="file-name">PTR (Post Training Report)</p>
                                        <p class="file-size">${r.ptr_file}</p>
                                    </div>
                                    <a href="${fileUrl}" class="btn btn-sm btn-primary" target="_blank" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            `;
                        }
                        
                        if (hasCoc) {
                            const fileUrl = `<?= BASE_URL ?>/uploads/training/${r.coc_file}`;
                            attachmentsHtml += `
                                <div class="attachment-item-view">
                                    <i class="fas fa-file-pdf"></i>
                                    <div class="file-info">
                                        <p class="file-name">COC (Certificate of Completion)</p>
                                        <p class="file-size">${r.coc_file}</p>
                                    </div>
                                    <a href="${fileUrl}" class="btn btn-sm btn-primary" target="_blank" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            `;
                        }
                        
                        if (hasMom) {
                            const fileUrl = `<?= BASE_URL ?>/uploads/training/${r.mom_file}`;
                            attachmentsHtml += `
                                <div class="attachment-item-view">
                                    <i class="fas fa-file-word"></i>
                                    <div class="file-info">
                                        <p class="file-name">MOM (Minutes of the Meeting)</p>
                                        <p class="file-size">${r.mom_file}</p>
                                    </div>
                                    <a href="${fileUrl}" class="btn btn-sm btn-primary" target="_blank" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            `;
                        }
                        
                        attachmentsHtml += '</div></div>';
                    } else {
                        attachmentsHtml = '<div class="view-details-card"><h6><i class="fas fa-paperclip me-2"></i>Attachments</h6><p class="text-muted">No attachments uploaded</p></div>';
                    }
                    
                    modalBody.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-tag me-2"></i>Training Information</h6>
                                    <p><strong>Title:</strong> ${escapeHtml(r.title)}</p>
                                    <p><strong>Type:</strong> <span class="badge badge-warning">${escapeHtml(r.training_type)}</span></p>
                                    <p><strong>Location Type:</strong> ${escapeHtml(r.location_type || 'N/A')}</p>
                                    <p><strong>Hospital Order No.:</strong> ${escapeHtml(r.hospital_order_no || 'N/A')}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-calendar me-2"></i>Schedule</h6>
                                    <p><strong>Date Start:</strong> ${new Date(r.date_start).toLocaleDateString()}</p>
                                    <p><strong>Date End:</strong> ${new Date(r.date_end).toLocaleDateString()}</p>
                                    <p><strong>Amount:</strong> ₱${parseFloat(r.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-user me-2"></i>Requester Information</h6>
                                    <p><strong>Requester:</strong> ${escapeHtml(r.requester_name || 'N/A')}</p>
                                    <p><strong>Status:</strong> <span class="badge status-badge-complete">Complete</span></p>
                                    <p><strong>Created:</strong> ${new Date(r.created_at).toLocaleString()}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-flag me-2"></i>Flags</h6>
                                    <p><strong>Official Business:</strong> ${r.official_business ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}</p>
                                    <p><strong>Late Filing:</strong> ${r.late_filing ? '<span class="badge" style="background-color: #ff69b4;">Yes</span>' : '<span class="badge badge-secondary">No</span>'}</p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-comment me-2"></i>Remarks</h6>
                                    <p>${escapeHtml(r.remarks) || '<em>No remarks</em>'}</p>
                                </div>
                            </div>
                            ${r.resched_reason ? `
                            <div class="col-12">
                                <div class="view-details-card">
                                    <h6><i class="fas fa-calendar-alt me-2"></i>Reschedule Reason</h6>
                                    <p>${escapeHtml(r.resched_reason)}</p>
                                </div>
                            </div>
                            ` : ''}
                            <div class="col-12">
                                ${attachmentsHtml}
                            </div>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'Error loading request details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading request details. Please try again.
                    </div>
                `;
            });
    }
    
<?php if (is_admin() || is_superadmin()): ?>
// Report Modal Functions
let allDepartments = [];

// Load filter options
function loadFilterOptions() {
    fetch(`${window.location.href}?get_filter_options=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate years
                const yearSelect = document.getElementById('reportYear');
                yearSelect.innerHTML = '<option value="">All Years</option>';
                data.years.forEach(year => {
                    yearSelect.innerHTML += `<option value="${year}">${year}</option>`;
                });
                
                // Populate divisions
                const divisionSelect = document.getElementById('reportDivision');
                divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                data.divisions.forEach(division => {
                    divisionSelect.innerHTML += `<option value="${division.id}">${escapeHtml(division.name)}</option>`;
                });
                
                // Store departments for dynamic filtering
                allDepartments = data.departments;
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

// Load report data
function loadReportData() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    const division_id = document.getElementById('reportDivision').value;
    const dept_id = document.getElementById('reportDepartment').value;
    const type = document.getElementById('reportType').value;
    
    let url = `${window.location.href}?get_report_data=1`;
    if (year) url += `&year=${year}`;
    if (month) url += `&month=${month}`;
    if (division_id) url += `&division_id=${division_id}`;
    if (dept_id) url += `&dept_id=${dept_id}`;
    if (type) url += `&type=${encodeURIComponent(type)}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('reportTableBody');
            if (data.success && data.reports.length > 0) {
                tbody.innerHTML = '';
                data.reports.forEach(report => {
                    tbody.innerHTML += `
                        <tr>
                            <td><strong>${escapeHtml(report.title)}</strong></div>
                            <td>
                                <span class="badge ${report.training_type === 'Internal' ? 'badge-info' : 'badge-warning'}">
                                    ${escapeHtml(report.training_type)}
                                </span>
                            </div>
                            <td>${escapeHtml(report.date_start)}</div>
                            <td>${escapeHtml(report.date_end)}</div>
                            <td>${escapeHtml(report.requester_name)}</div>
                            <td>${escapeHtml(report.division_name || '—')}</div>
                            <td>${escapeHtml(report.department_name || '—')}</div>
                            <td>${escapeHtml(report.hospital_order_no || '—')}</div>
                            <td>₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                            <td>
                                <span class="badge badge-success">Approved</span>
                            </div>
                        <tr>
                    `;
                });
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                            <p class="text-muted mb-0">No approved training requests found</p>
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

// Update department dropdown based on selected division
function updateDepartments() {
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

// Export report as CSV
function exportReportToCSV() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    const division_id = document.getElementById('reportDivision').value;
    const dept_id = document.getElementById('reportDepartment').value;
    const type = document.getElementById('reportType').value;
    
    let url = `${window.location.href}?get_report_data=1`;
    if (year) url += `&year=${year}`;
    if (month) url += `&month=${month}`;
    if (division_id) url += `&division_id=${division_id}`;
    if (dept_id) url += `&dept_id=${dept_id}`;
    if (type) url += `&type=${encodeURIComponent(type)}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reports.length > 0) {
                // Create CSV content
                let csvContent = "Title,Type,From,To,Requester,Division,Department,Hospital Order No.,Amount,Status\n";
                
                data.reports.forEach(report => {
                    csvContent += `"${escapeCsv(report.title)}","${report.training_type}","${report.date_start}","${report.date_end}","${escapeCsv(report.requester_name)}","${escapeCsv(report.division_name || '—')}","${escapeCsv(report.department_name || '—')}","${escapeCsv(report.hospital_order_no || '—')}","${report.amount}","Approved"\n`;
                });
                
                // Download CSV
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `approved_training_report_${new Date().toISOString().slice(0,10)}.csv`);
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

// Helper function to escape CSV fields
function escapeCsv(str) {
    if (!str) return '';
    return str.replace(/"/g, '""');
}

// Event listeners for report modal
document.getElementById('reportYear')?.addEventListener('change', loadReportData);
document.getElementById('reportMonth')?.addEventListener('change', loadReportData);
document.getElementById('reportDivision')?.addEventListener('change', function() {
    updateDepartments();
    loadReportData();
});
document.getElementById('reportDepartment')?.addEventListener('change', loadReportData);
document.getElementById('reportType')?.addEventListener('change', loadReportData);
document.getElementById('exportReportBtn')?.addEventListener('click', exportReportToCSV);

// When modal opens, load filter options and data
document.getElementById('reportModal')?.addEventListener('show.bs.modal', function() {
    loadFilterOptions();
    setTimeout(() => {
        updateDepartments();
        loadReportData();
    }, 100);
});
<?php endif; ?>
    
    // Open Attachments Modal
    function openAttachmentModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('viewAttachmentsModal'));
        const attachmentsList = document.getElementById('attachmentsList');
        
        // Show loading state
        attachmentsList.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p>Loading attachments...</p>
            </div>
        `;
        
        modal.show();
        
        // Fetch request data with attachments
        fetch(`${window.location.href}?get_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    const files = [
                        { name: 'PTR (Post Training Report)', file: request.ptr_file, icon: 'fa-file-alt', required: true },
                        { name: 'COC (Certificate of Completion)', file: request.coc_file, icon: 'fa-file-pdf', required: true },
                        { name: 'MOM (Minutes of the Meeting)', file: request.mom_file, icon: 'fa-file-word', required: false }
                    ];
                    
                    let attachmentsHtml = '<div class="row g-3">';
                    let hasFiles = false;
                    let missingRequired = [];
                    
                    files.forEach(file => {
                        if (file.file) {
                            hasFiles = true;
                            const fileUrl = `<?= BASE_URL ?>/uploads/training/${file.file}`;
                            const fileExt = file.file.split('.').pop().toUpperCase();
                            attachmentsHtml += `
                                <div class="col-md-6">
                                    <div class="attachment-card">
                                        <div class="attachment-icon">
                                            <i class="fas ${file.icon} fa-2x"></i>
                                        </div>
                                        <div class="attachment-info">
                                            <h6 class="attachment-title">${file.name}</h6>
                                            <p class="attachment-filename">${file.file}</p>
                                            <span class="attachment-badge">${fileExt}</span>
                                        </div>
                                        <a href="${fileUrl}" class="btn btn-sm btn-primary" target="_blank" download>
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            `;
                        } else if (file.required) {
                            missingRequired.push(file.name);
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
                        let warningHtml = '';
                        if (missingRequired.length > 0) {
                            warningHtml = `
                                <div class="alert alert-warning mb-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Missing Required Attachments:</strong> ${missingRequired.join(', ')} are required to mark this training as complete.
                                </div>
                            `;
                        }
                        attachmentsList.innerHTML = warningHtml + attachmentsHtml;
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
    
    // Submit training request via AJAX
    document.getElementById('trainingFormModal').addEventListener('submit', function(e) {
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
        formData.append('add_request_ajax', '1');
        
        const submitBtn = document.getElementById('submitRequestBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new row to table
                const tableBody = document.getElementById('trainingTableBody');
                const emptyStateRow = document.getElementById('emptyStateRow');
                
                if (emptyStateRow) {
                    emptyStateRow.remove();
                }
                
                const startDateFormatted = new Date(data.request.date_start).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const endDateFormatted = new Date(data.request.date_end).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                const newRow = document.createElement('tr');
                newRow.setAttribute('data-id', data.request.id);
                newRow.setAttribute('data-title', data.request.title.toLowerCase());
                newRow.setAttribute('data-type', data.request.training_type.toLowerCase());
                newRow.setAttribute('data-order', data.request.hospital_order_no.toLowerCase());
                newRow.setAttribute('data-remarks', (data.request.remarks || '').toLowerCase());
                newRow.setAttribute('data-resched', '');
                newRow.setAttribute('data-has-ptr', '0');
                newRow.setAttribute('data-has-coc', '0');
                newRow.setAttribute('data-status', 'pending');
                
                newRow.innerHTML = `
                    <td><strong>${escapeHtml(data.request.title)}</strong></div>
                    <td>
                        <span class="badge ${data.request.training_type === 'Internal' ? 'badge-info' : 'badge-warning'}">
                            ${data.request.training_type}
                        </span>
                      </div>
                    <td>${startDateFormatted}</div>
                    <td>${endDateFormatted}</div>
                    <td>${escapeHtml(data.request.requester_name)}</div>
                    <td>${escapeHtml(data.request.hospital_order_no)}</div>
                    <td>₱${parseFloat(data.request.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    <td>
                        ${data.request.official_business ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}
                      </div>
                    <td>
                        ${data.request.late_filing ? '<span class="badge" style="background-color: #ff69b4; color: white; border-radius: 4px; padding: 4px 12px;">Yes</span>' : '<span class="badge" style="background-color: #6c757d; color: white; border-radius: 4px; padding: 4px 12px;">No</span>'}
                      </div>
                    <td class="truncated-cell" title="${escapeHtml(data.request.remarks)}">
                        ${data.request.remarks.length > 30 ? escapeHtml(data.request.remarks.substring(0, 30)) + '...' : escapeHtml(data.request.remarks)}
                      </div>
                    <td class="truncated-cell" title="">—</div>
                    <td>
                        <span class="badge badge-warning">
                            <i class="fas fa-hourglass-half me-1"></i>Pending
                        </span>
                      </div>
                    <td>
                        <span class="status-badge status-pending">Pending</span>
                      </div>
                    <td class="action-buttons">
                        <button class="btn-action btn-edit" onclick="openEditModal(${data.request.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-action btn-reschedule" onclick="openRescheduleModal(${data.request.id})">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </button>
                        <button class="btn-action btn-view-attachment" onclick="openAttachmentModal(${data.request.id})">
                            <i class="fas fa-paperclip"></i> View Attachments
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteRequest(${data.request.id}, this)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                      </div>
                `;
                
                tableBody.insertBefore(newRow, tableBody.firstChild);
                
                // Update total count
                const totalCountSpan = document.getElementById('totalCount');
                const currentTotal = parseInt(totalCountSpan.textContent) || 0;
                totalCountSpan.textContent = currentTotal + 1;
                
                // Update statistics
                const externalSpan = document.querySelector('.stat-item:nth-child(2) .stat-number');
                if (data.request.training_type === 'External') {
                    externalSpan.textContent = parseInt(externalSpan.textContent) + 1;
                }
                
                const modalElement = document.getElementById('trainingRequestModal');
                const modal = bootstrap.Modal.getInstance(modalElement);

                // Wait for modal to fully close
                modalElement.addEventListener('hidden.bs.modal', function onHidden() {
                    // Force remove any backdrop that remains
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    modalElement.removeEventListener('hidden.bs.modal', onHidden);
                }, { once: true });

                modal.hide();
                document.getElementById('trainingFormModal').reset();
                
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Request';
        });
    });
    
    // Open Edit Modal
    function openEditModal(id) {
        fetch(`${window.location.href}?get_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    document.getElementById('edit_id').value = request.id;
                    document.getElementById('edit_created_at').value = request.created_at;
                    document.getElementById('edit_training_type').value = request.training_type;
                    document.getElementById('edit_title').value = request.title;
                    document.getElementById('edit_date_start').value = request.date_start;
                    document.getElementById('edit_date_end').value = request.date_end;
                    document.getElementById('edit_location_type').value = request.location_type || '';
                    document.getElementById('edit_hospital_id').value = request.hospital_order_no;
                    document.getElementById('edit_amount').value = request.amount;
                    
                    // Check if attachments exist
                    const hasPtr = !!request.ptr_file;
                    const hasCoc = !!request.coc_file;
                    const hasAttachments = hasPtr || hasCoc;
                    
                    const endDate = new Date(request.date_end);
                    const currentDate = new Date();
                    const daysElapsed = Math.floor((currentDate - endDate) / (1000 * 60 * 60 * 24));
                    
                    const reminderAlert = document.getElementById('attachmentReminderAlert');
                    let reminderLevel = 'none';
                    let alertClass = 'alert-warning';
                    let alertMessage = '';
                    
                    if (!hasAttachments) {
                        if (daysElapsed >= 60) {
                            reminderLevel = 'red';
                            alertClass = 'alert-danger';
                            alertMessage = '<strong>Urgent:</strong> No attachments in 60+ days. Please upload the required documents immediately (PTR and COC).';
                        } else if (daysElapsed >= 45) {
                            reminderLevel = 'orange';
                            alertClass = 'alert-warning';
                            alertMessage = '<strong>Reminder:</strong> No attachments in 45+ days. Please upload the required documents (PTR and COC).';
                        }
                    } else if (!hasPtr || !hasCoc) {
                        const missing = [];
                        if (!hasPtr) missing.push('PTR');
                        if (!hasCoc) missing.push('COC');
                        alertMessage = `<strong>Missing Required Attachments:</strong> ${missing.join(' and ')} ${missing.length > 1 ? 'are' : 'is'} required to mark this training as complete.`;
                        alertClass = 'alert-warning';
                    }
                    
                    if (alertMessage) {
                        reminderAlert.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${alertMessage}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
                        reminderAlert.className = `alert ${alertClass} alert-dismissible fade show`;
                        reminderAlert.classList.remove('d-none');
                    } else {
                        reminderAlert.classList.add('d-none');
                    }
                    
                    // Only set checkbox values if elements exist (admin/superadmin only)
                    const lateFilingCheckbox = document.getElementById('edit_late_filing');
                    const officialBusinessCheckbox = document.getElementById('edit_official_business');
                    
                    if (lateFilingCheckbox) {
                        lateFilingCheckbox.checked = request.late_filing == 1;
                    }
                    if (officialBusinessCheckbox) {
                        officialBusinessCheckbox.checked = request.official_business == 1;
                    }
                    
                    // Disable approve checkbox if status is already approved or complete
                    const approveCheckbox = document.getElementById('edit_approve_status');
                    if (approveCheckbox) {
                        if (request.status === 'approved' || request.status === 'complete') {
                            approveCheckbox.disabled = true;
                            approveCheckbox.checked = request.status === 'approved';
                        } else {
                            approveCheckbox.disabled = false;
                            approveCheckbox.checked = false;
                        }
                    }
                    
                    // Check if current date is before end date (training not yet finished)
                    const currentDateForCheck = new Date();
                    const endDateObj = new Date(request.date_end);
                    const isTrainingOngoing = currentDateForCheck < endDateObj;
                    
                    // Enable/disable attachments section based on status AND date condition
                    const attachmentsSection = document.getElementById('attachmentsSection');
                    const attachmentInputs = document.querySelectorAll('.attachment-input');
                    
                    // Remove any existing warning
                    const existingWarning = document.getElementById('trainingOngoingWarning');
                    if (existingWarning) existingWarning.remove();
                    
                    if (isTrainingOngoing) {
                        attachmentsSection.style.opacity = '0.5';
                        attachmentsSection.style.pointerEvents = 'none';
                        attachmentInputs.forEach(input => input.disabled = true);
                        
                        // Add warning message
                        const warningDiv = document.createElement('div');
                        warningDiv.className = 'alert alert-info mt-2 mb-0';
                        warningDiv.id = 'trainingOngoingWarning';
                        warningDiv.innerHTML = '<i class="fas fa-calendar-alt me-2"></i><strong>Notice:</strong> Attachments can only be added after the training end date has passed.';
                        attachmentsSection.insertAdjacentElement('afterend', warningDiv);
                    } else if (request.status === 'pending') {
                        attachmentsSection.style.opacity = '0.5';
                        attachmentsSection.style.pointerEvents = 'none';
                        attachmentInputs.forEach(input => input.disabled = true);
                        
                        const warningDiv = document.createElement('div');
                        warningDiv.className = 'alert alert-warning mt-2 mb-0';
                        warningDiv.id = 'trainingOngoingWarning';
                        warningDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Notice:</strong> Attachments cannot be added until this request is approved.';
                        attachmentsSection.insertAdjacentElement('afterend', warningDiv);
                    } else {
                        attachmentsSection.style.opacity = '1';
                        attachmentsSection.style.pointerEvents = 'auto';
                        attachmentInputs.forEach(input => input.disabled = false);
                    }
                    
                    // Show/hide Mark as Complete button with both PTR and COC requirement
                    const markCompleteContainer = document.getElementById('markCompleteContainer');
                    const markCompleteBtn = document.getElementById('markCompleteBtn');
                    const helpTextSpan = document.querySelector('#markCompleteContainer .completion-help-text');
                    
                    if (markCompleteContainer) {
                        const canBeCompleted = (request.status === 'approved' || request.status === 'submitted') && !isTrainingOngoing && hasPtr && hasCoc;
                        
                        if (canBeCompleted) {
                            markCompleteContainer.style.display = 'block';
                            if (markCompleteBtn) markCompleteBtn.disabled = false;
                            if (helpTextSpan) helpTextSpan.innerHTML = '✓ Both PTR and COC have been uploaded. The training end date has passed. Click to mark it as complete.';
                        } else {
                            markCompleteContainer.style.display = 'block';
                            if (markCompleteBtn) markCompleteBtn.disabled = true;
                            
                            let reasonText = '';
                            if (isTrainingOngoing) {
                                reasonText = '<i class="fas fa-calendar-alt me-1"></i> This training has not yet ended. Complete button will be available after the end date.';
                            } else if (!hasPtr || !hasCoc) {
                                const missing = [];
                                if (!hasPtr) missing.push('PTR');
                                if (!hasCoc) missing.push('COC');
                                reasonText = `<i class="fas fa-paperclip me-1"></i> <strong>Missing Required Attachments:</strong> ${missing.join(' and ')} ${missing.length > 1 ? 'are' : 'is'} required. Please upload both PTR and COC files.`;
                            } else if (request.status !== 'approved' && request.status !== 'submitted') {
                                reasonText = '<i class="fas fa-info-circle me-1"></i> Request must be approved or submitted before marking as complete.';
                            }
                            
                            if (helpTextSpan) {
                                helpTextSpan.innerHTML = reasonText;
                            }
                        }
                    }
                    
                    document.getElementById('edit_remarks').value = request.remarks || '';
                    
                    // Show current files
                    if (request.ptr_file) {
                        document.getElementById('current_ptr').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.ptr_file}" target="_blank">📄 View Current PTR File</a>`;
                    } else {
                        document.getElementById('current_ptr').innerHTML = '<span class="text-muted">No PTR file uploaded</span>';
                    }
                    if (request.coc_file) {
                        document.getElementById('current_coc').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.coc_file}" target="_blank">📄 View Current COC File</a>`;
                    } else {
                        document.getElementById('current_coc').innerHTML = '<span class="text-muted">No COC file uploaded</span>';
                    }
                    if (request.mom_file) {
                        document.getElementById('current_mom').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.mom_file}" target="_blank">📄 View Current MOM File</a>`;
                    } else {
                        document.getElementById('current_mom').innerHTML = '<span class="text-muted">No MOM file uploaded</span>';
                    }
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editRequestModal'));
                    editModal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }
    
    // Edit form submission
    document.getElementById('editFormModal').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('edit_request_ajax', '1');
        
        const submitBtn = document.getElementById('updateRequestBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editRequestModal'));
                editModal.hide();
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
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Update Request';
        });
    });
    
    // Mark as Complete button handler
    document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
        if (!confirm('Are you sure you want to mark this training request as complete? This will require both PTR and COC to be uploaded.')) {
            return;
        }
        
        const id = document.getElementById('edit_id').value;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Marking...';
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('mark_complete_ajax', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const editModal = bootstrap.Modal.getInstance(document.getElementById('editRequestModal'));
                editModal.hide();
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Mark as Complete';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Mark as Complete';
        });
    });
    
    // Open Reschedule Modal
    function openRescheduleModal(id) {
        fetch(`${window.location.href}?get_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    document.getElementById('reschedule_id').value = request.id;
                    document.getElementById('reschedule_date_start').value = request.date_start;
                    document.getElementById('reschedule_date_end').value = request.date_end;
                    document.getElementById('reschedule_reason').value = '';
                    
                    const rescheduleModal = new bootstrap.Modal(document.getElementById('rescheduleRequestModal'));
                    rescheduleModal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }
    
    // Reschedule form submission
    document.getElementById('rescheduleFormModal').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('reschedule_request_ajax', '1');
        
        const submitBtn = document.getElementById('rescheduleRequestBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rescheduleModal = bootstrap.Modal.getInstance(document.getElementById('rescheduleRequestModal'));
                rescheduleModal.hide();
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
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-calendar-check me-1"></i> Submit Reschedule';
        });
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Real-time search functionality
    const searchInput = document.getElementById('searchInput');
    const nonSubmissionFilter = document.getElementById('nonSubmissionFilter');
    const tableBody = document.getElementById('trainingTableBody');
    
    // Combined filter function
    function filterTableRows() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterType = nonSubmissionFilter ? nonSubmissionFilter.value : 'all';
        let visibleCount = 0;
        const rows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
        
        rows.forEach(row => {
            const title = row.getAttribute('data-title') || '';
            const type = row.getAttribute('data-type') || '';
            const order = row.getAttribute('data-order') || '';
            const remarks = row.getAttribute('data-remarks') || '';
            const resched = row.getAttribute('data-resched') || '';
            const reminderLevel = row.getAttribute('data-reminder-level') || 'none';
            
            // Check search term match
            const matchesSearch = searchTerm === '' || 
                title.includes(searchTerm) || 
                type.includes(searchTerm) || 
                order.includes(searchTerm) || 
                remarks.includes(searchTerm) ||
                resched.includes(searchTerm);
            
            // Check submission status filter
            let matchesSubmissionFilter = false;
            switch(filterType) {
                case 'all':
                    matchesSubmissionFilter = true;
                    break;
                case 'none':
                    matchesSubmissionFilter = reminderLevel === 'none';
                    break;
                case 'orange':
                    matchesSubmissionFilter = reminderLevel === 'orange';
                    break;
                case 'red':
                    matchesSubmissionFilter = reminderLevel === 'red';
                    break;
                case 'any':
                    matchesSubmissionFilter = reminderLevel !== 'none';
                    break;
                default:
                    matchesSubmissionFilter = true;
            }
            
            // Show row if both conditions match
            if (matchesSearch && matchesSubmissionFilter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const totalCountSpan = document.getElementById('totalCount');
        if (totalCountSpan) {
            totalCountSpan.textContent = visibleCount;
        }
        
        const emptyStateRow = document.getElementById('emptyStateRow');
        if (visibleCount === 0 && rows.length === 0 && !emptyStateRow) {
            const emptyRow = document.createElement('tr');
            emptyRow.id = 'emptyStateRow';
            emptyRow.innerHTML = `
                <td colspan="14" class="text-center py-5">
                    <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                    <p class="text-muted mb-0">No training requests found</p>
                  </div>
            `;
            tableBody.appendChild(emptyRow);
        } else if (visibleCount === 0 && rows.length > 0 && !document.querySelector('.no-results-row')) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="14" class="text-center py-5">
                    <i class="fas fa-search fa-2x mb-2" style="color: #dee2e6;"></i>
                    <p class="text-muted mb-0">No matching training requests found</p>
                  </div>
            `;
            tableBody.appendChild(noResultsRow);
        } else if (visibleCount > 0) {
            const noResultsRow = tableBody.querySelector('.no-results-row');
            if (noResultsRow) noResultsRow.remove();
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('keyup', filterTableRows);
    }
    
    if (nonSubmissionFilter) {
        nonSubmissionFilter.addEventListener('change', filterTableRows);
    }
    
    // Delete request via AJAX
    function deleteRequest(id, buttonElement) {
        if (!confirm('Are you sure you want to delete this training request? This action cannot be undone.')) {
            return;
        }
        
        const row = buttonElement.closest('tr');
        
        const buttons = row.querySelectorAll('.btn-action');
        buttons.forEach(btn => btn.disabled = true);
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `delete_request=1&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                row.remove();
                
                const totalCountSpan = document.getElementById('totalCount');
                const currentTotal = parseInt(totalCountSpan.textContent) || 0;
                totalCountSpan.textContent = currentTotal - 1;
                
                const trainingType = row.querySelector('.badge-info, .badge-warning').textContent.trim();
                if (trainingType === 'External') {
                    const externalSpan = document.querySelector('.stat-item:nth-child(2) .stat-number');
                    externalSpan.textContent = parseInt(externalSpan.textContent) - 1;
                }
                
                const remainingRows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
                if (remainingRows.length === 0 && !document.getElementById('emptyStateRow')) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.id = 'emptyStateRow';
                    emptyRow.innerHTML = `
                        <td colspan="14" class="text-center py-5">
                            <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                            <p class="text-muted mb-0">No training requests found</p>
                          </div>
                    `;
                    tableBody.appendChild(emptyRow);
                }
                
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'danger');
                buttons.forEach(btn => btn.disabled = false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
            buttons.forEach(btn => btn.disabled = false);
        });
    }

    // Continuously update day counter for training requests
    function updateDayCounters() {
        const table = document.getElementById('trainingTableBody');
        if (!table) return;
        
        const rows = table.querySelectorAll('tr[data-end-date]');
        rows.forEach(row => {
            const endDate = new Date(row.getAttribute('data-end-date'));
            const currentDate = new Date();
            const daysElapsed = Math.floor((currentDate - endDate) / (1000 * 60 * 60 * 24));
            
            const hasAttachments = row.getAttribute('data-has-attachments') === '1';
            
            // Find the warning badge in this row
            const badge = row.querySelector('.training-warning-badge');
            if (badge) {
                if (!hasAttachments) {
                    // Update the badge text with current days elapsed
                    let reminderText = '';
                    if (daysElapsed >= 60) {
                        reminderText = `Warning: No attachments (${daysElapsed}+ days)`;
                    } else if (daysElapsed >= 45) {
                        reminderText = `Warning: No attachments (${daysElapsed}+ days)`;
                    }
                    
                    if (reminderText && !badge.textContent.includes(reminderText.split('(')[1])) {
                        // Update badge with new day count
                        const iconSpan = badge.querySelector('i');
                        badge.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${reminderText}`;
                        badge.title = reminderText;
                    }
                }
            }
        });
    }
    
    // Initialize day counter update on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateDayCounters();
        // Update every hour (3600000 milliseconds)
        setInterval(updateDayCounters, 3600000);
    });
</script>
</html>