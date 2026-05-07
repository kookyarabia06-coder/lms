<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Force session settings
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);
session_set_cookie_params(7200);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Refresh session to prevent issues
session_regenerate_id(false);

// Use PDO for consistency with your LMS
$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];
$is_admin = is_admin() || is_superadmin();

// Initialize variables
$success_message = '';
$error_message = '';

// Helper function to calculate late filing based on created_at
function calculateLateFiling($official_business, $date_start, $created_at) {
    if (empty($date_start) || empty($created_at)) {
        return 0;
    }
    
    $start = new DateTime($date_start);
    $filed = new DateTime($created_at);
    $interval = $filed->diff($start)->days;
    
    if ($official_business == 1) {
        return ($interval <= 29) ? 1 : 0;
    } else {
        return ($interval <= 14) ? 1 : 0;
    }
}

// Handle AJAX Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("SELECT requester_id FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if (!$is_admin && $request['requester_id'] != $current_user_id) {
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
        $training_type = 'External';
        $title = trim($_POST['title'] ?? '');
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_id = trim($_POST['hospital_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
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
        $created_at = date('Y-m-d H:i:s');
        
        $late_filing = calculateLateFiling($official_business, $date_start, $created_at);
        
        $stmt = $pdo->prepare("INSERT INTO training_requests (
            training_type, title, date_start, date_end, location_type, 
            hospital_order_no, amount, late_filing, official_business, 
            remarks, requester_id, status, ptr_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");
        
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
        
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname FROM users WHERE id = ?");
        $stmt->execute([$requester_id]);
        $user = $stmt->fetch();
        $requester_name = $user['fullname'] ?? 'Unknown';
        
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
                'status' => 'pending',
                'ptr_status' => 'pending'
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
    // Clean output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    try {
        $id = (int)$_POST['id'];
        
        // Get current request data - include the original late_filing value
        $check_stmt = $pdo->prepare("SELECT ptr_status, status, date_end, created_at, date_start, late_filing, ptr_file, coc_file, mom_file FROM training_requests WHERE id = ?");
        $check_stmt->execute([$id]);
        $current_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_data) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if ($current_data['ptr_status'] === 'completed') {
            echo json_encode(['success' => false, 'message' => 'Completed requests cannot be edited.']);
            exit;
        }
        
        // Get form data
        $training_type = 'External';
        $title = trim($_POST['title'] ?? '');
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_id = trim($_POST['hospital_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        
        // CRITICAL FIX: Keep the original late_filing value, do NOT recalculate it
        // Late filing should only be calculated ONCE when the request is created
        $late_filing = $current_data['late_filing'];  // Use existing value, don't recalculate!
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit;
        }
        
        // Handle file uploads
        $upload_dir = __DIR__ . '/../uploads/training/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ptr_file = null;
        $coc_file = null;
        $mom_file = null;
        $has_upload = false;
        
        // Upload PTR file
        if (isset($_FILES['ptr_file']) && $_FILES['ptr_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['ptr_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (in_array($ext, $allowed)) {
                $filename = 'ptr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['ptr_file']['tmp_name'], $upload_dir . $filename)) {
                    $ptr_file = $filename;
                    $has_upload = true;
                }
            }
        }
        
        // Upload COC file
        if (isset($_FILES['coc_file']) && $_FILES['coc_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['coc_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (in_array($ext, $allowed)) {
                $filename = 'coc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['coc_file']['tmp_name'], $upload_dir . $filename)) {
                    $coc_file = $filename;
                    $has_upload = true;
                }
            }
        }
        
        // Upload MOM file
        if (isset($_FILES['mom_file']) && $_FILES['mom_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['mom_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (in_array($ext, $allowed)) {
                $filename = 'mom_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['mom_file']['tmp_name'], $upload_dir . $filename)) {
                    $mom_file = $filename;
                    $has_upload = true;
                }
            }
        }
        
        // Build the update query - NOTE: late_filing is NOT being updated
        $sql = "UPDATE training_requests SET 
            training_type = ?,
            title = ?,
            location_type = ?,
            hospital_order_no = ?,
            amount = ?,
            official_business = ?,
            remarks = ?";
        
        $params = [
            $training_type,
            $title,
            $location_type,
            $hospital_id,
            $amount,
            $official_business,
            $remarks
        ];
        
        // Note: late_filing is NOT included in the update - it stays as the original value
        
        // Add file fields to update if files were uploaded
        if ($ptr_file !== null) {
            $sql .= ", ptr_file = ?";
            $params[] = $ptr_file;
        }
        if ($coc_file !== null) {
            $sql .= ", coc_file = ?";
            $params[] = $coc_file;
        }
        if ($mom_file !== null) {
            $sql .= ", mom_file = ?";
            $params[] = $mom_file;
        }
        
        // Check if both PTR and COC exist
        $existing_ptr = $current_data['ptr_file'] ?? '';
        $existing_coc = $current_data['coc_file'] ?? '';
        $has_ptr = !empty($existing_ptr) || ($ptr_file !== null);
        $has_coc = !empty($existing_coc) || ($coc_file !== null);
        
        if ($has_ptr && $has_coc && $current_data['ptr_status'] === 'pending') {
            $sql .= ", ptr_status = 'submitted'";
        }
        
        // Handle admin actions
        $admin_action = isset($_POST['admin_action']) ? $_POST['admin_action'] : '';
        $action_remark = isset($_POST['action_remark']) ? trim($_POST['action_remark']) : '';
        
        if (!empty($admin_action) && $is_admin) {
            $new_status = '';
            $status_prefix = '';
            
            switch ($admin_action) {
                case 'approve':
                    $new_status = 'approved';
                    $status_prefix = 'Approved';
                    break;
                case 'conditional':
                    $new_status = 'conditional';
                    $status_prefix = 'Conditionally Approved';
                    break;
                case 'disapprove':
                    $new_status = 'disapproved';
                    $status_prefix = 'Disapproved';
                    break;
            }
            
            if ($new_status) {
                $sql .= ", status = ?";
                $params[] = $new_status;
                
                if (!empty($action_remark)) {
                    $timestamp = date('Y-m-d H:i:s');
                    $remark_entry = "\n[$timestamp] $status_prefix by " . ($_SESSION['user']['username'] ?? 'Admin') . ": $action_remark";
                    $sql .= ", remarks = CONCAT(COALESCE(remarks, ''), ?)";
                    $params[] = $remark_entry;
                }
            }
        }
        
        $sql .= ", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        
        // Execute the query
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Training request updated successfully!',
                'reload' => $has_upload
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
        exit;
        
    } catch (Exception $e) {
        error_log("Edit request error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Mark as Complete (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete_ajax'])) {
    header('Content-Type: application/json');
    try {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Only admins can mark requests as complete']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("SELECT ptr_status, ptr_file, coc_file, date_end FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if ($request['ptr_status'] !== 'submitted') {
            echo json_encode(['success' => false, 'message' => 'Cannot mark as complete: PTR status must be "submitted" first.']);
            exit;
        }
        
        $current_date = new DateTime();
        $end_date = new DateTime($request['date_end']);
        
        if ($current_date < $end_date) {
            echo json_encode(['success' => false, 'message' => 'Cannot mark as complete: Training end date has not yet passed.']);
            exit;
        }
        
        if (empty($request['ptr_file']) || empty($request['coc_file'])) {
            echo json_encode(['success' => false, 'message' => 'Both PTR and COC files are required before marking as complete.']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE training_requests SET ptr_status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request marked as complete successfully!', 'reload' => true]);
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
        
        $check_stmt = $pdo->prepare("SELECT ptr_status FROM training_requests WHERE id = ?");
        $check_stmt->execute([$id]);
        $current = $check_stmt->fetch();
        
        if ($current['ptr_status'] === 'completed') {
            echo json_encode(['success' => false, 'message' => 'Completed requests cannot be rescheduled.']);
            exit;
        }
        
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $resched_reason = trim($_POST['resched_reason'] ?? '');
        
        $errors = [];
        if (empty($date_start)) $errors[] = "New start date is required";
        if (empty($date_end)) $errors[] = "New end date is required";
        
        if (!empty($date_start) && !empty($date_end) && strtotime($date_end) < strtotime($date_start)) {
            $errors[] = "End date cannot be earlier than start date";
        }
        
        if (empty($resched_reason)) $errors[] = "Reschedule reason is required";
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE training_requests SET 
            date_start = ?, date_end = ?, resched_reason = ?, status = 'pending', ptr_status = 'pending',
            ptr_file = NULL, coc_file = NULL, mom_file = NULL
            WHERE id = ?");
        $stmt->execute([$date_start, $date_end, $resched_reason, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request rescheduled successfully!', 'reload' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Request Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_request'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Verify session is still valid
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page.']);
        exit;
    }
    
    try {
        $id = (int)$_GET['id'];
        
        if ($id <= 0) {
            throw new Exception('Invalid request ID');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname FROM users WHERE id = ?");
        $stmt->execute([$request['requester_id']]);
        $user = $stmt->fetch();
        $request['requester_name'] = $user['fullname'] ?? 'Unknown';
        
        foreach ($request as $key => $value) {
            if ($value === null) {
                $request[$key] = '';
            }
        }
        
        echo json_encode(['success' => true, 'request' => $request]);
        exit;
        
    } catch (Exception $e) {
        error_log("get_request error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Filtered Report Data (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_report_data']) && $is_admin) {
    header('Content-Type: application/json');
    try {
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        $division_id = isset($_GET['division_id']) && !empty($_GET['division_id']) ? (int)$_GET['division_id'] : null;
        $dept_id = isset($_GET['dept_id']) && !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        
        $where_clauses = ["tr.status = 'approved'"];
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
            ORDER BY tr.date_start DESC
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

// Handle AJAX Get Filter Options (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_filter_options']) && $is_admin) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM training_requests WHERE status = 'approved' ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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

// Handle filters
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : '';
$filter_status = isset($_GET['filter_status']) && !empty($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_ptr_status = isset($_GET['filter_ptr_status']) && !empty($_GET['filter_ptr_status']) ? $_GET['filter_ptr_status'] : '';
$filter_division = isset($_GET['filter_division']) && !empty($_GET['filter_division']) ? (int)$_GET['filter_division'] : '';
$filter_dept = isset($_GET['filter_dept']) && !empty($_GET['filter_dept']) ? (int)$_GET['filter_dept'] : '';

$where_clause = [];
$params = [];

$where_clause[] = "tr.training_type = 'external'";

if (!$is_admin) {
    $where_clause[] = "tr.requester_id = ?";
    $params[] = $current_user_id;
}

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

if (!empty($filter_ptr_status)) {
    $where_clause[] = "tr.ptr_status = ?";
    $params[] = $filter_ptr_status;
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

if (!$is_admin) {
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
    $stats = ['total' => 0, 'external_count' => 0, 'total_amount' => 0];
}

// Store base URL for JavaScript
$base_url = BASE_URL;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Request Management - LMS</title>
    <link href="<?= $base_url ?>/assets/css/training.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Delete Confirmation Modal Styles */
        .delete-confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .delete-confirm-modal.active {
            display: flex;
        }

        .delete-confirm-content {
            background: white;
            border-radius: 12px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .delete-confirm-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .delete-confirm-header i {
            font-size: 22px;
            color: #dc3545;
        }

        .delete-confirm-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #111827;
        }

        .delete-confirm-body {
            padding: 20px 24px;
        }

        .delete-confirm-body p {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .delete-confirm-body .training-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            border-left: 3px solid #dc3545;
        }

        .delete-confirm-body .training-info strong {
            display: block;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .delete-confirm-body .training-info small {
            color: #64748b;
            font-size: 12px;
        }

        .warning-note {
            background: #fef3c7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-note i {
            color: #f59e0b;
        }

        .delete-confirm-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .delete-confirm-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .delete-confirm-footer .btn-cancel-delete {
            background: #f3f4f6;
            color: #374151;
        }

        .delete-confirm-footer .btn-cancel-delete:hover {
            background: #e5e7eb;
        }

        .delete-confirm-footer .btn-confirm-delete {
            background: #dc3545;
            color: white;
        }

        .delete-confirm-footer .btn-confirm-delete:hover {
            background: #c82333;
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
        
        <!-- Filter Section -->
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
                        <option value="conditional" <?= ($filter_status == 'conditional') ? 'selected' : '' ?>>Conditional</option>
                        <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="disapproved" <?= ($filter_status == 'disapproved') ? 'selected' : '' ?>>Disapproved</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">PTR Status</label>
                    <select name="filter_ptr_status" class="form-select" id="filterPtrStatus">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="submitted">Submitted</option>
                        <option value="completed">Completed</option>
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
                    <?php if ($is_admin): ?>
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
                                $has_ptr = !empty($request['ptr_file']);
                                $has_coc = !empty($request['coc_file']);
                                $has_mom = !empty($request['mom_file']);
                                $has_required_attachments = $has_ptr && $has_coc;
                                $ptr_status = $request['ptr_status'] ?? 'pending';
                                $is_completed = $ptr_status === 'completed';
                                
                                $end_date = new DateTime($request['date_end']);
                                $current_date = new DateTime();
                                $days_elapsed = $current_date->diff($end_date)->days;
                                $is_past_end_date = $current_date > $end_date;
                                
                                $row_class = '';
                                $warning_message = '';
                                $badge_class = '';
                                
                                if (!$has_required_attachments && $is_past_end_date && $ptr_status !== 'completed') {
                                    if ($days_elapsed >= 90) {
                                        $row_class = 'warning-row-red';
                                        $warning_message = "EXPIRED: {$days_elapsed} days no attachment";
                                        $badge_class = 'badge-danger';
                                    } elseif ($days_elapsed >= 60) {
                                        $row_class = 'warning-row-orange';
                                        $warning_message = "WARNING: {$days_elapsed} days no attachment";
                                        $badge_class = 'badge-warning';
                                    } elseif ($days_elapsed >= 30) {
                                        $row_class = 'warning-row-yellow';
                                        $warning_message = "NOTICE: {$days_elapsed} days no attachment";
                                        $badge_class = 'badge-warning-yellow';
                                    }
                                }
                                ?>
                                <tr data-id="<?= $request['id'] ?>"
                                    data-title="<?= strtolower(htmlspecialchars($request['title'])) ?>" 
                                    data-type="<?= strtolower(htmlspecialchars($request['training_type'])) ?>"
                                    data-order="<?= strtolower(htmlspecialchars($request['hospital_order_no'])) ?>"
                                    data-remarks="<?= strtolower(htmlspecialchars($request['remarks'] ?? '')) ?>"
                                    data-resched="<?= strtolower(htmlspecialchars($request['resched_reason'] ?? '')) ?>"
                                    data-end-date="<?= $request['date_end'] ?>"
                                    data-has-ptr="<?= $has_ptr ? '1' : '0' ?>"
                                    data-has-coc="<?= $has_coc ? '1' : '0' ?>"
                                    data-status="<?= $request['status'] ?>"
                                    data-ptr-status="<?= $ptr_status ?>"
                                    class="<?= $row_class ?>"
                                    data-training-title="<?= htmlspecialchars($request['title']) ?>">
                                   
                                    <td>
                                        <strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if ($warning_message): ?>
                                            <br><span class="badge <?= $badge_class ?>" title="<?= $warning_message ?>"><i class="fas fa-exclamation-circle me-1"></i><?= $warning_message ?></span>
                                        <?php endif; ?>
                                     </div>
                                    <td><span class="badge badge-warning"><?= htmlspecialchars($request['training_type']) ?></span></div>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></div>
                                    <td><?= htmlspecialchars($request['requester_name'] ?? 'N/A') ?></div>
                                    <td><?= htmlspecialchars($request['hospital_order_no']) ?></div>
                                    <td>₱<?= number_format($request['amount'], 2) ?></div>
                                    <td><?= $request['official_business'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></div>
                                    <td><?= $request['late_filing'] ? '<span class="badge" style="background-color: #ff69b4; color: white;">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></div>
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                        <?= htmlspecialchars(strlen($request['remarks'] ?? '') > 30 ? substr($request['remarks'] ?? '', 0, 30) . '...' : $request['remarks'] ?? '-') ?>
                                      </div>
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['resched_reason'] ?? '') ?>">
                                        <?= htmlspecialchars(strlen($request['resched_reason'] ?? '') > 30 ? substr($request['resched_reason'] ?? '', 0, 30) . '...' : $request['resched_reason'] ?? '-') ?>
                                      </div>
                                    <td>
                                        <?php
                                        $ptr_badge_class = '';
                                        $ptr_icon = '';
                                        if ($ptr_status === 'pending') {
                                            $ptr_badge_class = 'badge-warning';
                                            $ptr_icon = 'fa-hourglass-half';
                                        } elseif ($ptr_status === 'submitted') {
                                            $ptr_badge_class = 'badge-info';
                                            $ptr_icon = 'fa-upload';
                                        } else {
                                            $ptr_badge_class = 'badge-success';
                                            $ptr_icon = 'fa-check-circle';
                                        }
                                        ?>
                                        <span class="badge <?= $ptr_badge_class ?>">
                                            <i class="fas <?= $ptr_icon ?> me-1"></i><?= ucfirst($ptr_status) ?>
                                        </span>
                                      </div>
                                    <td>
                                        <?php if ($request['status'] == 'conditional'): ?>
                                            <span class="status-badge-warning status-conditional">Conditional</span>
                                        <?php elseif ($request['status'] == 'disapproved'): ?>
                                            <span class="status-badge status-disapproved">Disapproved</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span>
                                        <?php endif; ?>
                                      </div>
                                    <td class="action-buttons">
                                        <?php if ($is_completed): ?>
                                            <button class="btn-action btn-view" onclick="openViewModal(<?= $request['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-view-attachment" onclick="openAttachmentModal(<?= $request['id'] ?>)" title="View Attachments">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <button class="btn-action btn-delete delete-training-btn" 
                                                    data-id="<?= $request['id'] ?>" 
                                                    data-title="<?= htmlspecialchars($request['title']) ?>"
                                                    data-requester="<?= htmlspecialchars($request['requester_name'] ?? 'N/A') ?>"
                                                    title="Delete Request">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-edit" onclick="openEditModal(<?= $request['id'] ?>)" title="Edit Request">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($is_admin): ?>
                                            <button class="btn-action btn-reschedule" onclick="openRescheduleModal(<?= $request['id'] ?>)" title="Reschedule Request">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-view-attachment" onclick="openAttachmentModal(<?= $request['id'] ?>)" title="View Attachments">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <button class="btn-action btn-delete delete-training-btn" 
                                                    data-id="<?= $request['id'] ?>" 
                                                    data-title="<?= htmlspecialchars($request['title']) ?>"
                                                    data-requester="<?= htmlspecialchars($request['requester_name'] ?? 'N/A') ?>"
                                                    title="Delete Request">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                      </div>
                                  <tr>
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
                        
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="official_business_add" name="official_business" value="1">
                                <label class="form-check-label" for="official_business_add">Official Business</label>
                            </div>
                        </div>
                        
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
                    <input type="hidden" name="admin_action" id="adminAction" value="">
                    
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
                            <input type="date" class="form-control" name="date_start" id="edit_date_start" required disabled style="background-color: #e9ecef;">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" id="edit_date_end" required disabled style="background-color: #e9ecef;">
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

                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_official_business" name="official_business" value="1">
                                <label class="form-check-label" for="edit_official_business">Official Business</label>
                            </div>
                        </div>

                        <!-- Admin Action Buttons (only show if status is pending) -->
                        <?php if ($is_admin): ?>
                        <div class="col-12" id="adminActionsContainer">
                            <div class="card bg-light p-3">
                                <h6 class="mb-3"><i class="fas fa-gavel me-2"></i>Administrative Actions</h6>
                                <div class="d-flex gap-3 flex-wrap" id="adminActionsButtons">
                                    <button type="button" class="btn btn-success" onclick="showAdminConfirmModal('approve')">
                                        <i class="fas fa-check-circle me-1"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="showAdminConfirmModal('conditional')">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Conditional
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="showAdminConfirmModal('disapprove')">
                                        <i class="fas fa-times-circle me-1"></i> Disapprove
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Complete Button (only shows when ptr_status = submitted) -->
                        <div class="col-12" id="completeButtonContainer" style="display: none;">
                            <div class="card bg-success bg-opacity-10 border-success p-3">
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="fas fa-check-circle me-2"></i> Mark as Complete
                                </button>
                                <small class="d-block mt-2 text-muted">Training has ended and both PTR and COC have been uploaded.</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        
                        <!-- Attachments Section (only shows after end date and status is approved/conditional) -->
                        <div class="col-12" id="attachmentsSection" style="display: none;">
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
                                    <label class="form-label">COC (Certificate of Completion) <span class="text-danger">*Required for completion</span></label>
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

<!-- View Training Request Modal -->
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
<?php if ($is_admin): ?>
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
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="exportReportBtn" class="btn btn-success w-100">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="reportTable">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Type</th><th>From</th><th>To</th><th>Requester</th><th>Division</th><th>Department</th><th>Hospital Order No.</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <tr><td colspan="10" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p>Loading data...</p></div></tr>
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
<?php endif; ?>

<!-- ADDED: Custom Delete Confirmation Modal -->
<div class="delete-confirm-modal" id="deleteConfirmModal">
    <div class="delete-confirm-content">
        <div class="delete-confirm-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3 id="deleteModalTitle">Delete Training Request</h3>
        </div>
        <div class="delete-confirm-body">
            <p id="deleteModalMessage">Are you sure you want to delete this training request?</p>
            <div class="training-info">
                <strong id="deleteTrainingTitle">Training Title</strong>
                <small id="deleteTrainingRequester">Requester: Name</small>
            </div>
            <div class="warning-note">
                <i class="fas fa-exclamation-circle"></i>
                <span>Warning: This action cannot be undone. All associated data including attachments will be permanently deleted.</span>
            </div>
        </div>
        <div class="delete-confirm-footer">
            <button class="btn-cancel-delete" id="cancelDeleteBtn">Cancel</button>
            <button class="btn-confirm-delete" id="confirmDeleteBtn">Delete Request</button>
        </div>
    </div>
</div>

<!-- ADDED: Admin Action Confirm Modal (Approve/Conditional/Disapprove) -->
<div class="modal fade" id="adminActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminActionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="adminActionMessage">Are you sure you want to perform this action?</p>
                <div class="mb-3">
                    <label class="form-label">Remarks (Optional)</label>
                    <textarea class="form-control" id="adminActionRemark" rows="3" placeholder="Add any remarks or notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAdminActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" class="toast-notification"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pass PHP variables to JavaScript
const BASE_URL = '<?= $base_url ?>';

let currentRequestId = null;
let adminAction = '';
let editModalAbortController = null;
let pendingDeleteUrl = null;
let pendingAdminAction = null;

// ADDED: Custom Delete Modal Functions
const deleteModal = document.getElementById('deleteConfirmModal');
const deleteModalTitle = document.getElementById('deleteModalTitle');
const deleteModalMessage = document.getElementById('deleteModalMessage');
const deleteTrainingTitle = document.getElementById('deleteTrainingTitle');
const deleteTrainingRequester = document.getElementById('deleteTrainingRequester');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

function closeDeleteModal() {
    deleteModal.classList.remove('active');
    pendingDeleteUrl = null;
}

function showDeleteModal(id, title, requester) {
    deleteTrainingTitle.textContent = title;
    deleteTrainingRequester.textContent = 'Requester: ' + requester;
    pendingDeleteUrl = id;
    deleteModal.classList.add('active');
}

if (confirmDeleteBtn) {
    confirmDeleteBtn.onclick = function() {
        if (pendingDeleteUrl) {
            deleteRequest(pendingDeleteUrl);
        }
    };
}

if (cancelDeleteBtn) {
    cancelDeleteBtn.onclick = closeDeleteModal;
}

deleteModal.addEventListener('click', function(e) {
    if (e.target === deleteModal) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && deleteModal.classList.contains('active')) closeDeleteModal();
});

// Override delete buttons
document.querySelectorAll('.delete-training-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        const title = this.getAttribute('data-title');
        const requester = this.getAttribute('data-requester');
        showDeleteModal(id, title, requester);
    });
});

// ADDED: Admin Action Modal Functions
const adminActionModal = new bootstrap.Modal(document.getElementById('adminActionModal'));
const adminActionModalTitle = document.getElementById('adminActionModalTitle');
const adminActionMessage = document.getElementById('adminActionMessage');
const adminActionRemark = document.getElementById('adminActionRemark');
const confirmAdminActionBtn = document.getElementById('confirmAdminActionBtn');

function showAdminConfirmModal(action) {
    let title = '';
    let message = '';
    let btnClass = '';
    
    switch(action) {
        case 'approve':
            title = 'Approve Training Request';
            message = 'Are you sure you want to approve this training request?';
            btnClass = 'btn-success';
            break;
        case 'conditional':
            title = 'Conditional Approval';
            message = 'Are you sure you want to conditionally approve this training request?';
            btnClass = 'btn-warning';
            break;
        case 'disapprove':
            title = 'Disapprove Training Request';
            message = 'Are you sure you want to disapprove this training request?';
            btnClass = 'btn-danger';
            break;
    }
    
    adminActionModalTitle.textContent = title;
    adminActionMessage.textContent = message;
    adminActionRemark.value = '';
    pendingAdminAction = action;
    
    confirmAdminActionBtn.className = 'btn ' + btnClass;
    adminActionModal.show();
}

confirmAdminActionBtn.onclick = function() {
    if (pendingAdminAction) {
        submitAdminAction(pendingAdminAction, adminActionRemark.value);
        adminActionModal.hide();
    }
};

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateDepartmentFilter() {
    const divisionId = document.getElementById('filterDivision').value;
    const deptSelect = document.getElementById('filterDept');
    const currentDeptValue = deptSelect.value;
    const allDepts = document.querySelectorAll('#filterDept option[data-division-id]');
    const allOption = deptSelect.querySelector('option:not([data-division-id])');
    deptSelect.innerHTML = '';
    deptSelect.appendChild(allOption);
    if (!divisionId) {
        allDepts.forEach(option => deptSelect.appendChild(option.cloneNode(true)));
        if (currentDeptValue) deptSelect.value = currentDeptValue;
    } else {
        allDepts.forEach(option => {
            if (option.getAttribute('data-division-id') === divisionId) deptSelect.appendChild(option.cloneNode(true));
        });
        if (currentDeptValue && document.querySelector(`#filterDept option[value="${currentDeptValue}"]`)) {
            deptSelect.value = currentDeptValue;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const filterDivision = document.getElementById('filterDivision');
    if (filterDivision) {
        updateDepartmentFilter();
        filterDivision.addEventListener('change', updateDepartmentFilter);
    }
});

function openViewModal(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
    const modalBody = document.getElementById('viewModalBody');
    modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading details...</p></div>';
    modal.show();
    
    const url = `${window.location.pathname}?get_request=1&id=${id}&t=${Date.now()}`;
    
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const r = data.request;
            const hasPtr = !!r.ptr_file;
            const hasCoc = !!r.coc_file;
            const hasMom = !!r.mom_file;
            let attachmentsHtml = '';
            const fileBaseUrl = BASE_URL + '/uploads/training/';
            
            if (hasPtr || hasCoc || hasMom) {
                attachmentsHtml = '<div class="view-details-card"><h6><i class="fas fa-paperclip me-2"></i>Attachments</h6><div class="attachment-list-view">';
                if (hasPtr) {
                    attachmentsHtml += `<div class="attachment-item-view"><i class="fas fa-file-alt"></i><div class="file-info"><p class="file-name">PTR (Post Training Report)</p><p class="file-size">${escapeHtml(r.ptr_file)}</p></div><a href="${fileBaseUrl}${r.ptr_file}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download"></i> Download</a></div>`;
                }
                if (hasCoc) {
                    attachmentsHtml += `<div class="attachment-item-view"><i class="fas fa-file-pdf"></i><div class="file-info"><p class="file-name">COC (Certificate of Completion)</p><p class="file-size">${escapeHtml(r.coc_file)}</p></div><a href="${fileBaseUrl}${r.coc_file}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download"></i> Download</a></div>`;
                }
                if (hasMom) {
                    attachmentsHtml += `<div class="attachment-item-view"><i class="fas fa-file-word"></i><div class="file-info"><p class="file-name">MOM (Minutes of the Meeting)</p><p class="file-size">${escapeHtml(r.mom_file)}</p></div><a href="${fileBaseUrl}${r.mom_file}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download"></i> Download</a></div>`;
                }
                attachmentsHtml += '</div></div>';
            } else {
                attachmentsHtml = '<div class="view-details-card"><h6><i class="fas fa-paperclip me-2"></i>Attachments</h6><p class="text-muted">No attachments uploaded</p></div>';
            }
            
            modalBody.innerHTML = `
                <div class="row"><div class="col-md-6"><div class="view-details-card"><h6><i class="fas fa-tag me-2"></i>Training Information</h6><p><strong>Title:</strong> ${escapeHtml(r.title)}</p><p><strong>Type:</strong> <span class="badge badge-warning">External</span></p><p><strong>Location Type:</strong> ${escapeHtml(r.location_type || 'N/A')}</p><p><strong>Hospital Order No.:</strong> ${escapeHtml(r.hospital_order_no || 'N/A')}</p></div></div>
                <div class="col-md-6"><div class="view-details-card"><h6><i class="fas fa-calendar me-2"></i>Schedule</h6><p><strong>Date Start:</strong> ${new Date(r.date_start).toLocaleDateString()}</p><p><strong>Date End:</strong> ${new Date(r.date_end).toLocaleDateString()}</p><p><strong>Amount:</strong> ₱${parseFloat(r.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p></div></div>
                <div class="col-md-6"><div class="view-details-card"><h6><i class="fas fa-user me-2"></i>Requester Information</h6><p><strong>Requester:</strong> ${escapeHtml(r.requester_name || 'N/A')}</p><p><strong>Created:</strong> ${new Date(r.created_at).toLocaleString()}</p></div></div>
                <div class="col-md-6"><div class="view-details-card"><h6><i class="fas fa-flag me-2"></i>Flags</h6><p><strong>Official Business:</strong> ${r.official_business ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}</p><p><strong>Late Filing:</strong> ${r.late_filing ? '<span class="badge" style="background-color: #ff69b4;">Yes</span>' : '<span class="badge badge-secondary">No</span>'}</p></div></div>
                <div class="col-12"><div class="view-details-card"><h6><i class="fas fa-comment me-2"></i>Remarks</h6><p>${escapeHtml(r.remarks) || '<em>No remarks</em>'}</p></div></div>
                ${r.resched_reason ? `<div class="col-12"><div class="view-details-card"><h6><i class="fas fa-calendar-alt me-2"></i>Reschedule Reason</h6><p>${escapeHtml(r.resched_reason)}</p></div></div>` : ''}
                <div class="col-12">${attachmentsHtml}</div></div>`;
        } else {
            modalBody.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> ${data.message || 'Error loading request details'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalBody.innerHTML = '<div class="alert alert-danger">Error loading request details. Please try again.</div>';
    });
}

function openAttachmentModal(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewAttachmentsModal'));
    const attachmentsList = document.getElementById('attachmentsList');
    attachmentsList.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p>Loading attachments...</p></div>';
    modal.show();
    
    const url = `${window.location.pathname}?get_request=1&id=${id}&t=${Date.now()}`;
    
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
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
            const fileBaseUrl = BASE_URL + '/uploads/training/';
            
            files.forEach(file => {
                if (file.file && file.file !== '') {
                    hasFiles = true;
                    const fileUrl = fileBaseUrl + file.file;
                    const fileExt = file.file.split('.').pop().toUpperCase();
                    attachmentsHtml += `<div class="col-md-6"><div class="attachment-card"><div class="attachment-icon"><i class="fas ${file.icon} fa-2x"></i></div><div class="attachment-info"><h6 class="attachment-title">${escapeHtml(file.name)}</h6><p class="attachment-filename">${escapeHtml(file.file)}</p><span class="attachment-badge">${fileExt}</span></div><a href="${fileUrl}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download me-1"></i> Download</a></div></div>`;
                } else if (file.required) {
                    missingRequired.push(file.name);
                }
            });
            attachmentsHtml += '</div>';
            
            if (!hasFiles) {
                attachmentsList.innerHTML = '<div class="text-center py-5"><i class="fas fa-paperclip fa-3x mb-3" style="color: #dee2e6;"></i><p class="text-muted">No attachments found for this training request.</p></div>';
            } else {
                let warningHtml = missingRequired.length > 0 ? `<div class="alert alert-warning mb-3"><i class="fas fa-exclamation-triangle me-2"></i><strong>Missing Required Attachments:</strong> ${missingRequired.join(', ')} are required to mark this training as complete.</div>` : '';
                attachmentsList.innerHTML = warningHtml + attachmentsHtml;
            }
        } else {
            attachmentsList.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> ${data.message || 'Error loading attachments'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading attachments:', error);
        attachmentsList.innerHTML = '<div class="alert alert-danger">Error loading attachments. Please try again.</div>';
    });
}

document.getElementById('trainingFormModal')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const startDate = document.querySelector('[name="date_start"]').value;
    const endDate = document.querySelector('[name="date_end"]').value;
    if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
        showToast('End date cannot be earlier than start date', 'danger');
        return;
    }
    const formData = new FormData(this);
    formData.append('add_request_ajax', '1');
    const submitBtn = document.getElementById('submitRequestBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
    fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('trainingRequestModal')).hide();
                document.getElementById('trainingFormModal').reset();
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

function openEditModal(id) {
    if (editModalAbortController) {
        try { editModalAbortController.abort(); } catch(e) {}
    }
    editModalAbortController = new AbortController();
    
    const url = `${window.location.pathname}?get_request=1&id=${id}&t=${Date.now()}`;
    
    fetch(url, {
        signal: editModalAbortController.signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Cache-Control': 'no-cache' }
    })
    .then(async response => {
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            throw new Error('Server returned invalid JSON');
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return data;
    })
    .then(data => {
        if (data.success) {
            const request = data.request;
            if (request.ptr_status === 'completed') {
                showToast('Completed requests cannot be edited.', 'warning');
                return;
            }
            
            document.getElementById('edit_id').value = request.id;
            document.getElementById('edit_title').value = request.title || '';
            document.getElementById('edit_date_start').value = request.date_start || '';
            document.getElementById('edit_date_end').value = request.date_end || '';
            document.getElementById('edit_location_type').value = request.location_type || '';
            document.getElementById('edit_hospital_id').value = request.hospital_order_no || '';
            document.getElementById('edit_amount').value = request.amount || 0;
            document.getElementById('edit_official_business').checked = request.official_business == 1;
            document.getElementById('edit_remarks').value = request.remarks || '';
            
            document.getElementById('adminAction').value = '';
            document.getElementById('updateRequestBtn').innerHTML = '<i class="fas fa-save me-1"></i> Update Request';
            
            const fileBaseUrl = BASE_URL + '/uploads/training/';
            document.getElementById('current_ptr').innerHTML = request.ptr_file ? `<a href="${fileBaseUrl}${request.ptr_file}" target="_blank">📄 View Current PTR File</a>` : '<span class="text-muted">No PTR file uploaded</span>';
            document.getElementById('current_coc').innerHTML = request.coc_file ? `<a href="${fileBaseUrl}${request.coc_file}" target="_blank">📄 View Current COC File</a>` : '<span class="text-muted">No COC file uploaded</span>';
            document.getElementById('current_mom').innerHTML = request.mom_file ? `<a href="${fileBaseUrl}${request.mom_file}" target="_blank">📄 View Current MOM File</a>` : '<span class="text-muted">No MOM file uploaded</span>';
            
            const adminContainer = document.getElementById('adminActionsContainer');
            if (adminContainer) {
                adminContainer.style.display = request.status === 'pending' ? 'block' : 'none';
            }
            
            const currentDate = new Date();
            const endDate = new Date(request.date_end);
            const isPastEndDate = currentDate > endDate;
            const attachmentsSection = document.getElementById('attachmentsSection');
            if (attachmentsSection) {
                attachmentsSection.style.display = (isPastEndDate && (request.status === 'approved' || request.status === 'conditional')) ? 'block' : 'none';
            }
            
            const completeContainer = document.getElementById('completeButtonContainer');
            if (completeContainer) {
                completeContainer.style.display = (request.ptr_status === 'submitted' && isPastEndDate) ? 'block' : 'none';
                if (request.ptr_status === 'submitted' && isPastEndDate) {
                    const completeBtn = document.getElementById('markCompleteBtn');
                    if (completeBtn) {
                        const newBtn = completeBtn.cloneNode(true);
                        completeBtn.parentNode.replaceChild(newBtn, completeBtn);
                        newBtn.onclick = () => markAsComplete(request.id);
                    }
                }
            }
            
            new bootstrap.Modal(document.getElementById('editRequestModal')).show();
        } else {
            showToast(data.message || 'Error loading request data', 'danger');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showToast('Error loading request data: ' + error.message, 'danger');
    });
}

// Add this event listener for the update button (non-admin)
document.getElementById('updateRequestBtn')?.addEventListener('click', function(e) {
    // Prevent default if it's a button type submit
    if (this.type !== 'submit') {
        e.preventDefault();
    }
    
    const formData = new FormData(document.getElementById('editFormModal'));
    formData.append('edit_request_ajax', '1');
    
    // Debug log
    console.log('=== Submitting Update Request ===');
    const ptrFile = document.querySelector('input[name="ptr_file"]');
    if (ptrFile && ptrFile.files.length > 0) {
        console.log('PTR File included:', ptrFile.files[0].name);
    }
    
    const submitBtn = this;
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
    
    fetch(window.location.pathname, { 
        method: 'POST', 
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editRequestModal')).hide();
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred: ' + error.message, 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

function markAsComplete(id) {
    // Use custom modal instead of confirm
    showConfirmModal('Mark this training as complete?', function() {
        const formData = new FormData();
        formData.append('mark_complete_ajax', '1');
        formData.append('id', id);
        fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editRequestModal')).hide();
                    showToast(data.message, 'success');
                    if (data.reload) {
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        location.reload();
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'danger');
            });
    });
}

function showConfirmModal(message, callback) {
    // Simple confirmation using Bootstrap modal
    const modalHtml = `
        <div class="modal fade" id="tempConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="tempConfirmOk">OK</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('tempConfirmModal');
    if (existingModal) existingModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('tempConfirmModal'));
    document.getElementById('tempConfirmOk').onclick = function() {
        modal.hide();
        if (callback) callback();
        setTimeout(() => document.getElementById('tempConfirmModal')?.remove(), 300);
    };
    modal.show();
}

function submitAdminAction(action, remark) {
    // Create FormData from the edit form - this includes files
    const formData = new FormData(document.getElementById('editFormModal'));
    
    // Add the AJAX flag and admin action
    formData.append('edit_request_ajax', '1');
    formData.append('admin_action', action);
    formData.append('action_remark', remark);
    
    // Debug: Log what's being sent
    console.log('=== Submitting Admin Action ===');
    console.log('Action:', action);
    console.log('Remark:', remark);
    
    // Debug: Check if files are included
    const ptrFile = document.querySelector('input[name="ptr_file"]');
    const cocFile = document.querySelector('input[name="coc_file"]');
    const momFile = document.querySelector('input[name="mom_file"]');
    
    if (ptrFile && ptrFile.files.length > 0) {
        console.log('PTR File included:', ptrFile.files[0].name);
    }
    if (cocFile && cocFile.files.length > 0) {
        console.log('COC File included:', cocFile.files[0].name);
    }
    if (momFile && momFile.files.length > 0) {
        console.log('MOM File included:', momFile.files[0].name);
    }
    
    const submitBtn = document.getElementById('updateRequestBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
    
    // Use fetch with the correct URL
    fetch(window.location.pathname, { 
        method: 'POST', 
        body: formData
        // Do NOT set Content-Type header - let the browser set it with the boundary
    })
    .then(async response => {
        const text = await response.text();
        console.log('Raw response:', text);
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', text.substring(0, 500));
            throw new Error('Server returned invalid response');
        }
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            const editModal = bootstrap.Modal.getInstance(document.getElementById('editRequestModal'));
            editModal.hide();
            showToast(data.message, 'success');
            // Always reload to see updated data
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'danger');
            const adminButtons = document.getElementById('adminActionsButtons');
            if (adminButtons) adminButtons.style.display = 'flex';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred: ' + error.message, 'danger');
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) adminButtons.style.display = 'flex';
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        document.getElementById('adminAction').value = '';
    });
}

function openRescheduleModal(id) {
    const url = `${window.location.pathname}?get_request=1&id=${id}&t=${Date.now()}`;
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const request = data.request;
                document.getElementById('reschedule_id').value = request.id;
                document.getElementById('reschedule_date_start').value = request.date_start;
                document.getElementById('reschedule_date_end').value = request.date_end;
                document.getElementById('reschedule_reason').value = '';
                new bootstrap.Modal(document.getElementById('rescheduleRequestModal')).show();
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading request data', 'danger');
        });
}

document.getElementById('rescheduleFormModal')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('reschedule_request_ajax', '1');
    const submitBtn = document.getElementById('rescheduleRequestBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
    fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('rescheduleRequestModal')).hide();
                showToast(data.message, 'success');
                if (data.reload) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    location.reload();
                }
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

const searchInput = document.getElementById('searchInput');
const filterPtrStatus = document.getElementById('filterPtrStatus');
const tableBody = document.getElementById('trainingTableBody');

function filterTableRows() {
    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const ptrStatusFilter = filterPtrStatus ? filterPtrStatus.value : '';
    let visibleCount = 0;
    const rows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
    rows.forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const type = row.getAttribute('data-type') || '';
        const order = row.getAttribute('data-order') || '';
        const remarks = row.getAttribute('data-remarks') || '';
        const resched = row.getAttribute('data-resched') || '';
        const ptrStatus = row.getAttribute('data-ptr-status') || '';
        const matchesSearch = searchTerm === '' || title.includes(searchTerm) || type.includes(searchTerm) || order.includes(searchTerm) || remarks.includes(searchTerm) || resched.includes(searchTerm);
        const matchesPtrStatus = ptrStatusFilter === '' || ptrStatus === ptrStatusFilter;
        if (matchesSearch && matchesPtrStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('totalCount').textContent = visibleCount;
    if (visibleCount === 0 && rows.length === 0 && !document.getElementById('emptyStateRow')) {
        const emptyRow = document.createElement('tr');
        emptyRow.id = 'emptyStateRow';
        emptyRow.innerHTML = `<td colspan="14" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i><p class="text-muted mb-0">No training requests found</p></td>`;
        tableBody.appendChild(emptyRow);
    }
}

if (searchInput) searchInput.addEventListener('keyup', filterTableRows);
if (filterPtrStatus) filterPtrStatus.addEventListener('change', filterTableRows);

function deleteRequest(id) {
    fetch(window.location.pathname, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
        body: `delete_request=1&id=${id}` 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();
            const totalCountSpan = document.getElementById('totalCount');
            if (totalCountSpan) {
                totalCountSpan.textContent = parseInt(totalCountSpan.textContent) - 1;
            }
            showToast(data.message, 'success');
            closeDeleteModal();
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'danger');
    });
}

<?php if ($is_admin): ?>
let allDepartments = [];
function loadFilterOptions() {
    fetch(`${window.location.pathname}?get_filter_options=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const yearSelect = document.getElementById('reportYear');
                yearSelect.innerHTML = '<option value="">All Years</option>';
                data.years.forEach(year => yearSelect.innerHTML += `<option value="${year}">${year}</option>`);
                const divisionSelect = document.getElementById('reportDivision');
                divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                data.divisions.forEach(division => divisionSelect.innerHTML += `<option value="${division.id}">${escapeHtml(division.name)}</option>`);
                allDepartments = data.departments;
            }
        });
}
function loadReportData() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    const division_id = document.getElementById('reportDivision').value;
    const dept_id = document.getElementById('reportDepartment').value;
    let url = `${window.location.pathname}?get_report_data=1`;
    if (year) url += `&year=${year}`;
    if (month) url += `&month=${month}`;
    if (division_id) url += `&division_id=${division_id}`;
    if (dept_id) url += `&dept_id=${dept_id}`;
    fetch(url).then(response => response.json()).then(data => {
        const tbody = document.getElementById('reportTableBody');
        if (data.success && data.reports.length > 0) {
            tbody.innerHTML = '';
            data.reports.forEach(report => {
                tbody.innerHTML += `<tr><td><strong>${escapeHtml(report.title)}</strong></td><td><span class="badge badge-warning">External</span></td><td>${escapeHtml(report.date_start)}</td><td>${escapeHtml(report.date_end)}</td><td>${escapeHtml(report.requester_name)}</td><td>${escapeHtml(report.division_name || '—')}</td><td>${escapeHtml(report.department_name || '—')}</td><td>${escapeHtml(report.hospital_order_no || '—')}</td><td>₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div><td><span class="badge badge-success">Approved</span></div></tr>`;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-2"></i><p>No approved training requests found</p></td></tr>`;
        }
    });
}
function updateDepartments() {
    const divisionId = document.getElementById('reportDivision').value;
    const deptSelect = document.getElementById('reportDepartment');
    if (!divisionId) { deptSelect.innerHTML = '<option value="">All Departments</option>'; return; }
    const filteredDepts = allDepartments.filter(dept => dept.department_id == divisionId);
    deptSelect.innerHTML = '<option value="">All Departments</option>';
    filteredDepts.forEach(dept => deptSelect.innerHTML += `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`);
}
function exportReportToCSV() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    const division_id = document.getElementById('reportDivision').value;
    const dept_id = document.getElementById('reportDepartment').value;
    let url = `${window.location.pathname}?get_report_data=1`;
    if (year) url += `&year=${year}`;
    if (month) url += `&month=${month}`;
    if (division_id) url += `&division_id=${division_id}`;
    if (dept_id) url += `&dept_id=${dept_id}`;
    fetch(url).then(response => response.json()).then(data => {
        if (data.success && data.reports.length > 0) {
            let csvContent = "Title,Type,From,To,Requester,Division,Department,Hospital Order No.,Amount,Status\n";
            data.reports.forEach(report => {
                csvContent += `"${report.title.replace(/"/g, '""')}","External","${report.date_start}","${report.date_end}","${report.requester_name}","${report.division_name || '—'}","${report.department_name || '—'}","${report.hospital_order_no || '—'}","${report.amount}","Approved"\n`;
            });
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
    });
}
document.getElementById('reportYear')?.addEventListener('change', loadReportData);
document.getElementById('reportMonth')?.addEventListener('change', loadReportData);
document.getElementById('reportDivision')?.addEventListener('change', function() { updateDepartments(); loadReportData(); });
document.getElementById('reportDepartment')?.addEventListener('change', loadReportData);
document.getElementById('exportReportBtn')?.addEventListener('click', exportReportToCSV);
document.getElementById('reportModal')?.addEventListener('show.bs.modal', function() { loadFilterOptions(); setTimeout(() => { updateDepartments(); loadReportData(); }, 100); });
<?php endif; ?>
</script>
</body>
</html>