


<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];

$success_message = '';
$error_message = '';

// Handle AJAX Get Users for Attendance (Lazy Loading)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_users_for_attendance'])) {
    header('Content-Type: application/json');
    try {
        $search = trim($_GET['search'] ?? '');
        
        $query = "SELECT id, username, CONCAT(fname, ' ', lname) as fullname 
                  FROM users 
                  WHERE role NOT IN ('admin', 'superadmin', 'proponent')";
        
        $params = [];
        if (!empty($search)) {
            $query .= " AND (fname LIKE ? OR lname LIKE ? OR username LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param, $search_param];
        }
        
        $query .= " ORDER BY fname, lname LIMIT 100";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pm_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Check permission
        $stmt = $pdo->prepare("SELECT requester_id FROM pm_training_requests WHERE id = ?");
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
        
        $stmt = $pdo->prepare("DELETE FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Add Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pm_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $title = trim($_POST['title'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $date_start = $_POST['date_start'] ?? '';
        $time_start = $_POST['time_start'] ?? NULL;
        $date_end = $_POST['date_end'] ?? '';
        $time_end = $_POST['time_end'] ?? NULL;
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        $attendees = isset($_POST['attendees']) ? json_decode($_POST['attendees'], true) : [];
        
        $errors = [];
        if (empty($title)) $errors[] = "Title is required";
        if (empty($venue)) $errors[] = "Venue is required";
        if (empty($date_start)) $errors[] = "Start date is required";
        if (empty($date_end)) $errors[] = "End date is required";
        
        if (!empty($date_start) && !empty($date_end)) {
            if (strtotime($date_end) < strtotime($date_start)) {
                $errors[] = "End date cannot be earlier than start date";
            }
        }
        
        // Check if user has approved training requests without PTR file
        if (!is_admin() && !is_superadmin()) {
            $stmt = $pdo->prepare("SELECT id FROM pm_training_requests WHERE requester_id = ? AND status = 'approved' AND ptr_file IS NULL");
            $stmt->execute([$current_user_id]);
            $incompletedRequests = $stmt->fetchAll();
            
            if (!empty($incompletedRequests)) {
                $errors[] = "Please upload Post Training Report (PTR) for your previous approved training request(s) before submitting a new request.";
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
        $stmt = $pdo->prepare("INSERT INTO pm_training_requests (
            title, venue, date_start, time_start, date_end, time_end, hospital_order_no,
            amount, late_filing, remarks, requester_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        $stmt->execute([
            $title,
            $venue,
            $date_start,
            $time_start ?: NULL,
            $date_end,
            $time_end ?: NULL,
            $hospital_order_no,
            $amount,
            $late_filing,
            $remarks,
            $requester_id
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        // Insert attendees
        if (!empty($attendees)) {
            $stmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, attended) VALUES (?, ?, 0)");
            foreach ($attendees as $user_id) {
                $stmt->execute([$new_id, (int)$user_id]);
            }
        }
        
        // Clear venues cache
        unset($_SESSION['pm_training_venues']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Training request submitted successfully!',
            'request' => [
                'id' => $new_id,
                'title' => $title,
                'venue' => $venue,
                'date_start' => $date_start,
                'date_end' => $date_end,
                'requester_name' => $requester_name,
                'hospital_order_no' => $hospital_order_no,
                'amount' => $amount,
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pm_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        // Check if request is completed - prevent editing
        $stmt = $pdo->prepare("SELECT status FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $checkRequest = $stmt->fetch();
        if ($checkRequest && $checkRequest['status'] === 'complete') {
            echo json_encode(['success' => false, 'message' => 'Completed requests cannot be edited.']);
            exit;
        }
        
        $title = trim($_POST['title'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Handle file uploads
        $upload_dir = __DIR__ . '/../uploads/pm_training/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        function uploadPmTrainingFile($field_name) {
            if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                return null;
            }
            $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'csv'];
            if (!in_array($ext, $allowed)) {
                return null;
            }
            $filename = 'pm_training_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = __DIR__ . '/../uploads/pm_training/';
            if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $upload_dir . $filename)) {
                return $filename;
            }
            return null;
        }
        
        $ptr_file = uploadPmTrainingFile('ptr_file');
        $attendance_file = uploadPmTrainingFile('attendance_file');
        
        // Build update query - only update fields that are provided
        $sql = "UPDATE pm_training_requests SET";
        $params = [];
        $updates = [];
        
        if (!empty($title)) {
            $updates[] = "title = ?";
            $params[] = $title;
        }
        
        if (!empty($venue)) {
            $updates[] = "venue = ?";
            $params[] = $venue;
        }
        
        $updates[] = "hospital_order_no = ?";
        $params[] = $hospital_order_no;
        
        $updates[] = "amount = ?";
        $params[] = $amount;
        
        $updates[] = "late_filing = ?";
        $params[] = $late_filing;
        
        $updates[] = "remarks = ?";
        $params[] = $remarks;
        
        if ($ptr_file) {
            $updates[] = "ptr_file = ?";
            $params[] = $ptr_file;
        }
        
        if ($attendance_file) {
            $updates[] = "attendance_file = ?";
            $params[] = $attendance_file;
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_pm_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can complete training requests']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        
        // Check if request exists and is approved
        $stmt = $pdo->prepare("SELECT status FROM pm_training_requests WHERE id = ?");
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
        
        // Update status to complete
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET status = 'complete', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training marked as complete successfully! Requester can now submit new requests.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Request Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        // Get the request
        $stmt = $pdo->prepare("SELECT * FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        // Check permission - user can only view their own requests, admins can view all
        $currentUserId = $_SESSION['user']['id'];
        $userRole = $_SESSION['user']['role'] ?? '';
        
        if ($userRole !== 'admin' && $userRole !== 'superadmin' && $request['requester_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to view this request']);
            exit;
        }
        
        // Get attendees with their user info
        $stmt = $pdo->prepare("
            SELECT pta.user_id, pta.attended, u.username, CONCAT(u.fname, ' ', u.lname) as fullname
            FROM pm_training_attendance pta
            LEFT JOIN users u ON pta.user_id = u.id
            WHERE pta.pm_training_request_id = ?
            ORDER BY u.fname, u.lname
        ");
        $stmt->execute([$id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $request['attendees'] = $attendees;
        
        echo json_encode(['success' => true, 'request' => $request]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Attendees for Modal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_attendees'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        // Get all attendees with their attendance status
        $stmt = $pdo->prepare("
            SELECT pta.user_id, u.username, CONCAT(u.fname, ' ', u.lname) as fullname, pta.attended
            FROM pm_training_attendance pta
            LEFT JOIN users u ON pta.user_id = u.id
            WHERE pta.pm_training_request_id = ?
            ORDER BY u.fname, u.lname
        ");
        $stmt->execute([$id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'attendees' => $attendees]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Update Attendance (Regular Users Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pm_attendance'])) {
    header('Content-Type: application/json');
    try {
        // Only regular users can update attendance
        if (is_admin() || is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Admins cannot update attendance']);
            exit;
        }

        $pm_request_id = (int)$_POST['pm_request_id'];
        $attendees = isset($_POST['attendees']) ? json_decode($_POST['attendees'], true) : [];

        // Update attendance status
        foreach ($attendees as $user_id => $attended) {
            $stmt = $pdo->prepare("UPDATE pm_training_attendance SET attended = ? WHERE pm_training_request_id = ? AND user_id = ?");
            $stmt->execute([$attended ? 1 : 0, $pm_request_id, $user_id]);
        }

        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Add Attendee (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pm_attendee'])) {
    header('Content-Type: application/json');
    try {
        // Only admins can add attendees
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can add attendees']);
            exit;
        }

        $pm_request_id = (int)$_POST['pm_request_id'];
        $user_search = trim($_POST['user_search']);

        if (empty($user_search)) {
            echo json_encode(['success' => false, 'message' => 'User search term is required']);
            exit;
        }

        // Search for user by name or username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE CONCAT(fname, ' ', lname) LIKE ? OR username LIKE ? LIMIT 1");
        $stmt->execute(["%$user_search%", "%$user_search%"]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Check if already exists
        $stmt = $pdo->prepare("SELECT id FROM pm_training_attendance WHERE pm_training_request_id = ? AND user_id = ?");
        $stmt->execute([$pm_request_id, $user['id']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is already in the attendance list']);
            exit;
        }

        // Add attendee
        $stmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, attended) VALUES (?, ?, 0)");
        $stmt->execute([$pm_request_id, $user['id']]);

        echo json_encode(['success' => true, 'message' => 'Attendee added successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Edit Attendee (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pm_attendee'])) {
    header('Content-Type: application/json');
    try {
        // Only admins can edit attendees
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can edit attendees']);
            exit;
        }

        $pm_request_id = (int)$_POST['pm_request_id'];
        $user_id = (int)$_POST['user_id'];
        $new_user_search = trim($_POST['new_user_search']);

        if (empty($new_user_search)) {
            echo json_encode(['success' => false, 'message' => 'User search term is required']);
            exit;
        }

        // Search for new user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE CONCAT(fname, ' ', lname) LIKE ? OR username LIKE ? LIMIT 1");
        $stmt->execute(["%$new_user_search%", "%$new_user_search%"]);
        $new_user = $stmt->fetch();

        if (!$new_user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Remove old attendee and add new one
        $stmt = $pdo->prepare("DELETE FROM pm_training_attendance WHERE pm_training_request_id = ? AND user_id = ?");
        $stmt->execute([$pm_request_id, $user_id]);

        $stmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, attended) VALUES (?, ?, 0)");
        $stmt->execute([$pm_request_id, $new_user['id']]);

        echo json_encode(['success' => true, 'message' => 'Attendee updated successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Delete Attendee (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pm_attendee'])) {
    header('Content-Type: application/json');
    try {
        // Only admins can delete attendees
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can delete attendees']);
            exit;
        }

        $pm_request_id = (int)$_POST['pm_request_id'];
        $user_id = (int)$_POST['user_id'];

        // Delete attendee
        $stmt = $pdo->prepare("DELETE FROM pm_training_attendance WHERE pm_training_request_id = ? AND user_id = ?");
        $stmt->execute([$pm_request_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Attendee removed successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Reschedule Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_pm_request_ajax'])) {
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
        $stmt = $pdo->prepare("SELECT requester_id FROM pm_training_requests WHERE id = ?");
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
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET
            date_start = ?, date_end = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Rescheduled: ', ?, ']'), status = 'pending', updated_at = NOW()
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_report_data'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can view reports']);
            exit;
        }
        
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        
        // Only show COMPLETED status
        $where_clauses = ["ptr.status = 'complete'"];
        $params = [];
        
        if ($year) {
            $where_clauses[] = "YEAR(ptr.date_start) = ?";
            $params[] = $year;
        }
        
        if ($month) {
            $where_clauses[] = "MONTH(ptr.date_start) = ?";
            $params[] = $month;
        }
        
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        // OPTIMIZED REPORT QUERY - Using a single subquery instead of multiple
        $query = "
            SELECT 
                ptr.id,
                ptr.title,
                ptr.venue,
                DATE_FORMAT(ptr.date_start, '%M %d, %Y') as date_start,
                DATE_FORMAT(ptr.date_end, '%M %d, %Y') as date_end,
                ptr.date_end as date_end_raw,
                CONCAT(u.fname, ' ', u.lname) as requester_name,
                u.username,
                ptr.hospital_order_no,
                ptr.amount,
                ptr.status,
                ptr.ptr_file,
                COALESCE(att_counts.attended_count, 0) as attended_count,
                COALESCE(att_counts.total_count, 0) as total_attendees
            FROM pm_training_requests ptr
            LEFT JOIN users u ON ptr.requester_id = u.id
            LEFT JOIN (
                SELECT 
                    pm_training_request_id,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_count
                FROM pm_training_attendance
                GROUP BY pm_training_request_id
            ) att_counts ON ptr.id = att_counts.pm_training_request_id
            $where_sql
            ORDER BY ptr.date_start DESC, ptr.created_at DESC
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_filter_options'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can view reports']);
            exit;
        }
        
        // Get available years
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM pm_training_requests ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'years' => $years
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch venues for dropdown (with caching)
$cache_key = 'pm_training_venues';
$venues = $_SESSION[$cache_key] ?? null;

if ($venues === null) {
    $stmt = $pdo->prepare("SELECT DISTINCT venue FROM pm_training_requests WHERE venue IS NOT NULL ORDER BY venue");
    $stmt->execute();
    $venues = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION[$cache_key] = $venues; // Cache for current session
}

// REMOVED: All users query - now loaded via AJAX on demand

// Check for incomplete PTR uploads (approved requests without PTR file) - only for regular users
$incomplete_ptr_requests = [];
if (!is_admin() && !is_superadmin()) {
    $stmt = $pdo->prepare("SELECT id, title, date_end FROM pm_training_requests WHERE requester_id = ? AND status IN ('approved', 'pending') AND ptr_file IS NULL");
    $stmt->execute([$current_user_id]);
    $incomplete_ptr_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get filter parameters
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : '';
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
// REMOVED: Division and Department filters
$filter_committee = isset($_GET['filter_committee']) && !empty($_GET['filter_committee']) ? (int)$_GET['filter_committee'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// REMOVED: Get divisions and departments queries - no longer needed

// Build WHERE clause for both queries
$where_clause = [];
$main_params = [];
$count_where_clause = [];

if (!is_admin() && !is_superadmin()) {
    $where_clause[] = "ptr.requester_id = ?";
    $main_params[] = $current_user_id;
    $count_where_clause[] = "ptr.requester_id = ?";
}

if (!empty($filter_status)) {
    $where_clause[] = "ptr.status = ?";
    $main_params[] = $filter_status;
    $count_where_clause[] = "ptr.status = ?";
}

if (!empty($filter_year)) {
    $where_clause[] = "YEAR(ptr.date_start) = ?";
    $main_params[] = $filter_year;
    $count_where_clause[] = "YEAR(ptr.date_start) = ?";
}

if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $where_clause[] = "MONTH(ptr.date_start) = ?";
    $main_params[] = $filter_month;
    $count_where_clause[] = "MONTH(ptr.date_start) = ?";
}

if (!empty($search)) {
    $where_clause[] = "(ptr.title LIKE ? OR ptr.venue LIKE ? OR ptr.hospital_order_no LIKE ? OR ptr.remarks LIKE ? OR CONCAT(u.fname, ' ', u.lname) LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $main_params[] = $search_param;
    $main_params[] = $search_param;
    $main_params[] = $search_param;
    $main_params[] = $search_param;
    $main_params[] = $search_param;
    $main_params[] = $search_param;
    
    $count_where_clause[] = "(ptr.title LIKE ? OR ptr.venue LIKE ? OR ptr.hospital_order_no LIKE ? OR ptr.remarks LIKE ? OR CONCAT(u.fname, ' ', u.lname) LIKE ? OR u.username LIKE ?)";
}

$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";
$count_where_sql = !empty($count_where_clause) ? "WHERE " . implode(" AND ", $count_where_clause) : "";

// Build count parameters array separately
$count_params = [];
if (!is_admin() && !is_superadmin()) {
    $count_params[] = $current_user_id;
}
if (!empty($filter_status)) {
    $count_params[] = $filter_status;
}
if (!empty($filter_year)) {
    $count_params[] = $filter_year;
}
if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $count_params[] = $filter_month;
}
if (!empty($search)) {
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// REMOVED: Division and department filter conditions

if (!empty($filter_committee)) {
    $where_clause[] = "EXISTS (SELECT 1 FROM user_departments ud
                             WHERE ud.user_id = ptr.requester_id AND ud.committee_id = ?)";
    $main_params[] = $filter_committee;
    $count_where_clause[] = "EXISTS (SELECT 1 FROM user_departments ud
                             WHERE ud.user_id = ptr.requester_id AND ud.committee_id = ?)";
    $count_params[] = $filter_committee;
}

// Rebuild WHERE SQL after adding filters
$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";
$count_where_sql = !empty($count_where_clause) ? "WHERE " . implode(" AND ", $count_where_clause) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id $count_where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// OPTIMIZED MAIN QUERY with pagination
$query = "SELECT
    ptr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    GROUP_CONCAT(DISTINCT com.name) as committee_name
    FROM pm_training_requests ptr
    LEFT JOIN users u ON ptr.requester_id = u.id
    LEFT JOIN user_departments ud ON ud.user_id = ptr.requester_id
    LEFT JOIN committees com ON ud.committee_id = com.id
    $where_sql
    GROUP BY ptr.id
    ORDER BY ptr.created_at DESC
    LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($main_params);
$pm_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate active filters count
$active_filters = 0;
if (!empty($filter_year)) $active_filters++;
if (!empty($filter_month)) $active_filters++;
if (!empty($filter_status)) $active_filters++;
if (!empty($search)) $active_filters++;
if (!empty($filter_committee)) $active_filters++;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PM Training Request Management - LMS</title>
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
        .pagination {
            margin-top: 20px;
            justify-content: center;
        }
        .page-info {
            text-align: center;
            margin: 10px 0;
            color: #6c757d;
        }
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .attendee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s;
        }
        .attendee-item:hover {
            background-color: #f8f9fa;
        }
        .attendee-item:last-child {
            border-bottom: none;
        }
        .attendee-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .attendee-info {
            flex: 1;
        }
        .btn-attendee {
            padding: 4px 8px;
            font-size: 0.85rem;
        }
        
        /* PM Training Requests Table Improvements */
        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .table-card-header {
            padding: 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-card-header h4 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }
        .table-card .table-responsive {
            max-height: 600px;
            overflow-y: auto;
            overflow-x: auto;
        }
        .table-card .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .table-card .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .table-card .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .table-card .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .table-card table.table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-card table.table thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 0.75rem;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table-card table.table tbody td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .table-card table.table tbody tr:hover {
            background-color: #f8fafc;
        }
        .table-card table.table tbody tr:last-child td {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        .status-badge.status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge.status-approved {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-badge.status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-badge.status-complete {
            background: #dcfce7;
            color: #166534;
        }
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-action.btn-edit {
            background-color: #f59e0b;
            color: white;
        }
        .btn-action.btn-edit:hover {
            background-color: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        }
        .btn-action.btn-reschedule {
            background-color: #3b82f6;
            color: white;
        }
        .btn-action.btn-reschedule:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }
        .btn-action.btn-delete {
            background-color: #ef4444;
            color: white;
        }
        .btn-action.btn-delete:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        /* Report Modal Improvements */
        #pmReportModal .modal-body {
            padding: 1.5rem;
        }
        #pmReportModal .alert-info {
            background: #f0f4ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            border-radius: 8px;
            font-size: 0.875rem;
        }
        #pmReportModal .form-select,
        #pmReportModal .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        #pmReportModal .form-select:focus,
        #pmReportModal .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        #pmReportModal .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }
        #pmReportTable {
            border-collapse: separate;
            border-spacing: 0;
        }
        #pmReportTable thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem;
            white-space: nowrap;
        }
        #pmReportTable tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        #pmReportTable tbody tr:hover {
            background-color: #f8fafc;
        }
        #pmReportTable tbody tr:last-child td {
            border-bottom: none;
        }
        #exportPmReportBtn {
            background: #2563eb;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
        }
        #exportPmReportBtn:hover {
            background: #1e40af;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.15);
        }
        #pmReportTable .amount-cell {
            font-weight: 600;
            color: #059669;
        }
        #pmReportTable .badge-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #dcfce7;
            color: #166534;
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
        <div class="pm-training-container d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>PM Training Request Management</h3>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPmTrainingModal" <?= !empty($incomplete_ptr_requests) ? 'disabled' : '' ?>>
                <i class="fas fa-plus me-2"></i>New Training Request
            </button>
        </div>

        <!-- Warning Alert for Incomplete PTR -->
        <?php if (!empty($incomplete_ptr_requests)): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Action Required:</strong> You must upload the Post Training Report (PTR) for your approved training request(s) before submitting a new request.
                <div class="mt-2">
                    <ul class="mb-0">
                        <?php foreach ($incomplete_ptr_requests as $req): ?>
                            <li>
                                <strong><?= htmlspecialchars($req['title']) ?></strong> 
                                (ended: <?= date('M d, Y', strtotime($req['date_end'])) ?>)
                                <button type="button" class="btn btn-sm btn-warning ms-2" onclick="openEditPmModal(<?= $req['id'] ?>)">
                                    <i class="fas fa-upload me-1"></i>Upload PTR
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>
                    <i class="fas fa-filter"></i> Filter PM Training Requests
                    <?php if ($active_filters > 0): ?>
                        <span class="badge bg-info"><?= $active_filters ?> filter(s) applied</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="filter_year" class="form-select">
                            <option value="">All Years</option>
                            <?php 
                            $current_year = (int)date('Y');
                            for ($i = $current_year; $i >= $current_year - 5; $i--): 
                            ?>
                                <option value="<?= $i ?>" <?= ($filter_year == $i) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select name="filter_month" class="form-select">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="filter_status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="complete" <?= ($filter_status == 'complete') ? 'selected' : '' ?>>Complete</option>
                            <option value="rejected" <?= ($filter_status == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Committee</label>
                        <select name="filter_committee" class="form-select">
                            <option value="">All Committees</option>
                            <?php 
                            $committees_stmt = $pdo->query("SELECT DISTINCT ud.committee_id, c.id, c.name FROM user_departments ud LEFT JOIN committees c ON ud.committee_id = c.id WHERE ud.committee_id IS NOT NULL ORDER BY c.name");
                            $filter_committees = $committees_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($filter_committees as $comm): 
                            ?>
                                <option value="<?= $comm['id'] ?>" <?= (isset($filter_committee) && $filter_committee == $comm['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comm['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Search (Title, Venue, Order No., Program Manager)</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by title, venue, order number, or program manager name..." 
                            value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Active Filters Notification -->
        <?php if ($active_filters > 0): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-info-circle"></i>
                <strong>Filters Applied:</strong>
                <?php 
                    $filter_texts = [];
                    if (!empty($filter_year)) $filter_texts[] = "Year: " . $filter_year;
                    if (!empty($filter_month)) {
                        $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        $filter_texts[] = "Month: " . $months[$filter_month];
                    }
                    if (!empty($filter_status)) $filter_texts[] = "Status: " . ucfirst($filter_status);
                    if (!empty($search)) $filter_texts[] = "Search: \"" . htmlspecialchars($search) . "\"";
                    if (!empty($filter_committee)) {
                        $filter_texts[] = "Committee: " . htmlspecialchars($filter_committee);
                    }
                    echo implode(" | ", $filter_texts);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- PM Training Requests Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-list"></i> PM Training Requests List <span class="badge bg-secondary"><?= $total_records ?> record<?= $total_records !== 1 ? 's' : '' ?></span></h4>
                    <?php if (is_admin() || is_superadmin()): ?>
                    <button class="btn btn-success" id="generatePmReportBtn" data-bs-toggle="modal" data-bs-target="#pmReportModal">
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
                            <th>Venue</th>
                            <th>Start Date</th>
                            <th>Start Time</th>
                            <th>End Date</th>
                            <th>Program Manager</th>
                            <th>Committee</th>
                            <th>HO No.</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pm_requests)): ?>
                            <?php foreach ($pm_requests as $request): ?>
                                <?php
                                // Calculate days elapsed since training end date
                                $end_date = new DateTime($request['date_end']);
                                $current_date = new DateTime();
                                $days_elapsed = $current_date->diff($end_date)->days;
                                
                                // Check if PTR file exists
                                $has_ptr = !empty($request['ptr_file']);
                                
                                // Check if attachments are reviewed/complete
                                $attachments_reviewed = ($request['status'] === 'complete') || ($request['status'] === 'approved' && $has_ptr);
                                
                                // Determine row background color
                                $row_bg_style = '';
                                if (!$has_ptr && !$attachments_reviewed) {
                                    if ($days_elapsed >= 1 && $days_elapsed <= 28) {
                                        $row_bg_style = 'style="background-color: #fff3cd;"';
                                    } elseif ($days_elapsed >= 29) {
                                        $row_bg_style = 'style="background-color: #ffe6e6;"';
                                    }
                                }
                                ?>
                                <tr>
                                    <td <?= $row_bg_style ?>><strong><?= htmlspecialchars($request['title']) ?></strong></td>
                                    <td <?= $row_bg_style ?>><?= htmlspecialchars($request['venue']) ?></td>
                                    <td <?= $row_bg_style ?>><?= date('M d, Y', strtotime($request['date_start'])) ?></td>
                                    <td <?= $row_bg_style ?>><?= !empty($request['time_start']) ? date('H:i', strtotime($request['time_start'])) : '-' ?></td>
                                    <td <?= $row_bg_style ?>><?= date('M d, Y', strtotime($request['date_end'])) ?></td>
                                    <td <?= $row_bg_style ?>><?= htmlspecialchars($request['requester_name']) ?></td>
                                    <td <?= $row_bg_style ?>><?= !empty($request['committee_name']) ? htmlspecialchars($request['committee_name']) : '-' ?></td>
                                    <td <?= $row_bg_style ?>><?= htmlspecialchars($request['hospital_order_no'] ?? '-') ?></td>
                                    <td <?= $row_bg_style ?>>₱<?= number_format($request['amount'], 2) ?></td>
                                    <td <?= $row_bg_style ?>>
                                        <span title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                            <?php 
                                            $remarks = $request['remarks'] ?? '';
                                            echo htmlspecialchars(strlen($remarks) > 30 ? substr($remarks, 0, 30) . '...' : $remarks);
                                            ?>
                                        </span>
                                    </td>
                                    <td <?= $row_bg_style ?>>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </td>
                                    <td <?= $row_bg_style ?>>
                                        <div style="display: flex; flex-direction: row; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <?php if ($request['status'] === 'complete'): ?>
                                                <button class="btn-action btn-view" onclick="openViewPmModal(<?= $request['id'] ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-edit" onclick="openEditPmModal(<?= $request['id'] ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-reschedule" onclick="openReschedulePmModal(<?= $request['id'] ?>)" title="Reschedule">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deletePmRequest(<?= $request['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                    <p class="text-muted mb-0">No PM training requests found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="page-info">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php
                        // Build pagination URL with existing filters
                        $pagination_url = $_SERVER['PHP_SELF'] . '?';
                        $params = $_GET;
                        unset($params['page']);
                        $query_string = http_build_query($params);
                        $base_url = $pagination_url . ($query_string ? $query_string . '&' : '');
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $base_url ?>page=<?= $page - 1 ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $base_url ?>page=1">1</a></li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $base_url ?>page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $base_url ?>page=<?= $page + 1 ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Next <i class="fas fa-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add PM Training Request Modal -->
<div class="modal fade" id="addPmTrainingModal" tabindex="-1" aria-labelledby="addPmTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
                <h5 class="modal-title" id="addPmTrainingLabel">
                    <i class="fas fa-plus-circle me-2"></i>New PM Training Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPmTrainingForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required placeholder="Enter training title">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Venue <span class="text-danger">*</span></label>
                            <select name="venue" class="form-select" required>
                                <option value="">-- Select Venue --</option>
                                <?php foreach ($venues as $v): ?>
                                    <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Add New Venue</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="newVenueInput" name="new_venue" placeholder="Enter new venue" style="display: none;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_start" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Time Start</label>
                            <input type="time" class="form-control" name="time_start">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Time End</label>
                            <input type="time" class="form-control" name="time_end">
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
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="late_filing" name="late_filing" value="1">
                                <label class="form-check-label" for="late_filing">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Late Filing
                                </label>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="late_filing" value="0">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Additional remarks..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Attendees <span class="text-danger">*</span></label>
                            <div class="attendance-search">
                                <input type="text" class="form-control" id="addAttendeeSearch" placeholder="Search attendees...">
                                <small class="text-muted">Type at least 2 characters to search</small>
                            </div>
                            <div class="attendee-list" id="addAttendeeList">
                                <div class="text-center py-3">
                                    <span class="loading-spinner"></span> Loading users...
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">Only regular users (employees) can be selected as attendees.</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="addPmBtn">
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

<!-- Edit PM Training Request Modal -->
<div class="modal fade" id="editPmTrainingModal" tabindex="-1" aria-labelledby="editPmTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editPmTrainingLabel">
                    <i class="fas fa-edit me-2"></i>Edit PM Training Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editPmTrainingForm">
                    <input type="hidden" name="id" id="edit_pm_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="edit_pm_title">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Venue</label>
                            <input type="text" class="form-control" name="venue" id="edit_pm_venue">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date Start</label>
                            <input type="date" class="form-control" name="date_start" id="edit_pm_date_start" disabled style="background-color: #e9ecef;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Date End</label>
                            <input type="date" class="form-control" name="date_end" id="edit_pm_date_end" disabled style="background-color: #e9ecef;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_order_no" id="edit_pm_hospital_order_no">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" id="edit_pm_amount" step="0.01">
                        </div>

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_pm_late_filing" name="late_filing" value="1">
                                <label class="form-check-label" for="edit_pm_late_filing">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Late Filing
                                </label>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="late_filing" value="0">
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_pm_remarks" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Actions</label>
                            <div class="btn-group w-100" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline-primary" id="editAttendanceListBtn" title="View and manage attendance">
                                    <i class="fas fa-clipboard-list me-1"></i>Attendance List
                                </button>
                                <button type="button" class="btn btn-outline-info" id="editPtrAttachmentBtn" title="Upload PTR file">
                                    <i class="fas fa-file-upload me-1"></i>Upload PTR
                                </button>
                            </div>
                        </div>

                        <?php if (is_admin() || is_superadmin()): ?>
                        <div class="col-12" id="approvalSection">
                            <div id="approveCheckboxSection">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_pm_approve_status" name="approve_status" value="1">
                                    <label class="form-check-label" for="edit_pm_approve_status">
                                        <strong>Approve this request</strong>
                                    </label>
                                </div>
                            </div>
                            <div id="completeButtonSection" style="display: none;">
                                <p class="mb-2"><strong>Status: APPROVED</strong></p>
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="fas fa-check-circle me-1"></i>Mark as Complete
                                </button>
                                <small class="text-muted d-block mt-2">Clicking this will allow the requester to submit new training requests.</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div> 

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="editPmBtn">
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

<!-- View PM Training Request Modal (Read-only for completed requests) -->
<div class="modal fade" id="viewPmTrainingModal" tabindex="-1" aria-labelledby="viewPmTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewPmTrainingLabel">
                    <i class="fas fa-eye me-2"></i>View PM Training Request (Completed)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Title</label>
                        <p class="form-control-static" id="view_pm_title">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Venue</label>
                        <p class="form-control-static" id="view_pm_venue">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Start</label>
                        <p class="form-control-static" id="view_pm_date_start">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date End</label>
                        <p class="form-control-static" id="view_pm_date_end">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hospital Order No.</label>
                        <p class="form-control-static" id="view_pm_hospital_order_no">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Amount (PHP)</label>
                        <p class="form-control-static" id="view_pm_amount">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Remarks</label>
                        <p class="form-control-static" id="view_pm_remarks">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Status</label>
                        <p class="form-control-static" id="view_pm_status">-</p>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">PTR File</label>
                        <div id="view_pm_ptr_file">-</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Attendees (Confirmed Attendance)</label>
                        <div id="view_pm_attendees" style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6;">
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

<!-- Attendance Check Modal -->
<div class="modal fade" id="attendanceCheckModal" tabindex="-1" aria-labelledby="attendanceCheckLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="attendanceCheckLabel">
                    <i class="fas fa-users me-2"></i>Attendance List
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="attendance-search flex-grow-1 me-3">
                        <input type="text" class="form-control" id="attendanceListSearch" placeholder="Search attendees...">
                    </div>
                    <?php if (is_admin() || is_superadmin()): ?>
                    <button class="btn btn-primary btn-sm" onclick="openAddAttendeeModal()">
                        <i class="fas fa-user-plus me-1"></i>Add Attendee
                    </button>
                    <?php endif; ?>
                </div>
                <div class="attendee-list" id="attendanceCheckList">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <?php if (!is_admin() && !is_superadmin()): ?>
                <button type="button" class="btn btn-success" id="saveAttendanceBtn">
                    <i class="fas fa-save me-1"></i>Save Attendance
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PTR Attachment Modal -->
<div class="modal fade" id="ptrAttachmentModal" tabindex="-1" aria-labelledby="ptrAttachmentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="ptrAttachmentLabel">
                    <i class="fas fa-file-upload me-2"></i>PTR (Post Training Report) Attachment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Current PTR File</label>
                    <div id="currentPtrDisplay" style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #dee2e6; min-height: 60px; display: flex; align-items: center;">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload New PTR File</label>
                    <input type="file" class="form-control" id="ptrFileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small class="text-muted d-block mt-2">Accepted formats: PDF, JPG, JPEG, PNG, DOC, DOCX</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="savePtrAttachmentBtn">
                    <i class="fas fa-save me-1"></i>Save Attachment
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule PM Training Request Modal -->
<div class="modal fade" id="reschedulePmModal" tabindex="-1" aria-labelledby="reschedulePmLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="reschedulePmLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Reschedule Training
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reschedulePmForm">
                    <input type="hidden" name="id" id="reschedule_pm_id">
                   
                    <div class="mb-3">
                        <label class="form-label">New Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_start" id="reschedule_pm_date_start" required>
                    </div>
                   
                    <div class="mb-3">
                        <label class="form-label">New End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_end" id="reschedule_pm_date_end" required>
                    </div>
                   
                    <div class="mb-3">
                        <label class="form-label">Reschedule Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="resched_reason" id="reschedule_pm_reason" rows="3" required placeholder="Please provide reason for rescheduling..."></textarea>
                    </div>
                   
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" id="reschedulePmBtn">
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

<!-- Generate Report Modal -->
<?php if (is_admin() || is_superadmin()): ?>
<div class="modal fade" id="pmReportModal" tabindex="-1" aria-labelledby="pmReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
                <h5 class="modal-title" id="pmReportLabel">
                    <i class="fas fa-chart-line me-2"></i>Completed Trainings Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This report only shows trainings with <strong>COMPLETED</strong> status.
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
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="exportPmReportBtn">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="pmReportTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Venue</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Program Manager</th>
                                <th>Hospital Order No.</th>
                                <th>Amount</th>
                                <th>Attended</th>
                                <th>PTR</th>
                            </tr>
                        </thead>
                        <tbody id="pmReportTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-5">
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
<?php endif; ?>

<div id="toastContainer" class="toast-notification"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPmRequestId = null;
    let attendeeSearchTimeout = null;

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

    // Load users for attendance (Lazy loading)
    function loadUsersForAttendance(search = '') {
        const attendeeList = document.getElementById('addAttendeeList');
        attendeeList.innerHTML = '<div class="text-center py-3"><span class="loading-spinner"></span> Loading users...</div>';
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        if (search) {
            url.searchParams.set('search', search);
        }
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.users.length > 0) {
                        attendeeList.innerHTML = '';
                        data.users.forEach(user => {
                            const div = document.createElement('div');
                            div.className = 'attendee-item';
                            div.innerHTML = `
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input attendee-checkbox" name="attendees" value="${user.id}">
                                    <label class="form-check-label">
                                        ${escapeHtml(user.fullname + ' (' + user.username + ')')}
                                    </label>
                                </div>
                            `;
                            attendeeList.appendChild(div);
                        });
                    } else {
                        attendeeList.innerHTML = '<div class="text-center py-3 text-muted">No users found</div>';
                    }
                } else {
                    attendeeList.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                attendeeList.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
            });
    }

    // Setup attendee search with debounce
    const addAttendeeSearch = document.getElementById('addAttendeeSearch');
    if (addAttendeeSearch) {
        // Load initial users when modal opens
        document.getElementById('addPmTrainingModal').addEventListener('show.bs.modal', function() {
            loadUsersForAttendance('');
        });
        
        addAttendeeSearch.addEventListener('input', function() {
            clearTimeout(attendeeSearchTimeout);
            const searchTerm = this.value;
            
            if (searchTerm.length >= 2 || searchTerm.length === 0) {
                attendeeSearchTimeout = setTimeout(() => {
                    loadUsersForAttendance(searchTerm);
                }, 300);
            }
        });
    }

    // Venue dropdown - add new venue
    document.querySelector('[name="venue"]')?.addEventListener('change', function() {
        const newVenueInput = document.getElementById('newVenueInput');
        if (this.value === 'new') {
            newVenueInput.style.display = 'block';
            newVenueInput.required = true;
        } else {
            newVenueInput.style.display = 'none';
            newVenueInput.required = false;
            newVenueInput.value = '';
        }
    });

    // Add PM Training Form Submit
    document.getElementById('addPmTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const venue = document.querySelector('[name="venue"]').value;
        const newVenue = document.querySelector('[name="new_venue"]')?.value || '';
        const finalVenue = venue === 'new' ? newVenue : venue;

        if (!finalVenue) {
            showToast('Please select or enter a venue', 'danger');
            return;
        }

        const formData = new FormData(this);
        formData.set('venue', finalVenue);
        formData.append('add_pm_request_ajax', '1');

        // Get selected attendees
        const attendees = Array.from(document.querySelectorAll('#addAttendeeList input[name="attendees"]:checked'))
            .map(cb => cb.value);
        
        if (attendees.length === 0) {
            showToast('Please select at least one attendee', 'danger');
            return;
        }

        formData.set('attendees', JSON.stringify(attendees));

        const btn = document.getElementById('addPmBtn');
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
    function openEditPmModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_request', '1');
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
                    
                    document.getElementById('edit_pm_id').value = req.id;
                    document.getElementById('edit_pm_title').value = req.title;
                    document.getElementById('edit_pm_venue').value = req.venue;
                    document.getElementById('edit_pm_date_start').value = req.date_start;
                    document.getElementById('edit_pm_date_end').value = req.date_end;
                    document.getElementById('edit_pm_hospital_order_no').value = req.hospital_order_no || '';
                    document.getElementById('edit_pm_amount').value = req.amount || 0;
                    
                    const lateFilingCheckbox = document.getElementById('edit_pm_late_filing');
                    if (lateFilingCheckbox) {
                        lateFilingCheckbox.checked = req.late_filing == 1;
                    }
                    
                    document.getElementById('edit_pm_remarks').value = req.remarks || '';

                    // Handle approval section for admins
                    const isApproved = req.status === 'approved';
                    const shouldShowCompleteButton = req.status === 'approved';

                    const approvalSection = document.getElementById('approvalSection');
                    if (approvalSection) {
                        const approveSection = document.getElementById('approveCheckboxSection');
                        const completeSection = document.getElementById('completeButtonSection');
                        
                        if (shouldShowCompleteButton) {
                            if (approveSection) approveSection.style.display = 'none';
                            if (completeSection) completeSection.style.display = 'block';
                        } else {
                            if (approveSection) approveSection.style.display = 'block';
                            if (completeSection) completeSection.style.display = 'none';
                            const approveCheckbox = document.getElementById('edit_pm_approve_status');
                            if (approveCheckbox) approveCheckbox.checked = false;
                        }
                    }

                    window.currentEditPmRequestId = req.id;
                    
                    const attendanceBtn = document.getElementById('editAttendanceListBtn');
                    if (attendanceBtn) {
                        attendanceBtn.onclick = function() {
                            openAttendanceModal(req.id);
                        };
                    }
                    
                    const ptrBtn = document.getElementById('editPtrAttachmentBtn');
                    if (ptrBtn) {
                        ptrBtn.onclick = function() {
                            openPtrAttachmentModal(req.id);
                        };
                    }

                    const modal = new bootstrap.Modal(document.getElementById('editPmTrainingModal'));
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
    function openViewPmModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    
                    document.getElementById('view_pm_title').innerHTML = escapeHtml(req.title);
                    document.getElementById('view_pm_venue').innerHTML = escapeHtml(req.venue);
                    document.getElementById('view_pm_date_start').innerHTML = formatDate(req.date_start);
                    document.getElementById('view_pm_date_end').innerHTML = formatDate(req.date_end);
                    document.getElementById('view_pm_hospital_order_no').innerHTML = escapeHtml(req.hospital_order_no || '-');
                    document.getElementById('view_pm_amount').innerHTML = '₱' + parseFloat(req.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    document.getElementById('view_pm_remarks').innerHTML = escapeHtml(req.remarks || '-');
                    
                    const statusBadge = `<span class="status-badge status-${req.status}">${req.status.charAt(0).toUpperCase() + req.status.slice(1)}</span>`;
                    document.getElementById('view_pm_status').innerHTML = statusBadge;
                    
                    const ptrDiv = document.getElementById('view_pm_ptr_file');
                    if (req.ptr_file) {
                        ptrDiv.innerHTML = `<a href="<?= BASE_URL ?>/uploads/pm_training/${req.ptr_file}" target="_blank" class="btn btn-sm btn-info">
                            <i class="fas fa-download me-1"></i> Download PTR File
                        </a>`;
                    } else {
                        ptrDiv.innerHTML = '<em class="text-muted">No PTR file uploaded</em>';
                    }
                    
                    const attendeesDiv = document.getElementById('view_pm_attendees');
                    if (req.attendees && req.attendees.length > 0) {
                        const attendedUsers = req.attendees.filter(attendee => attendee.attended == 1);
                        if (attendedUsers.length > 0) {
                            let attendeesHtml = '<ul style="margin: 0; padding-left: 20px;">';
                            attendedUsers.forEach(attendee => {
                                attendeesHtml += `<li><strong>${escapeHtml(attendee.fullname)}</strong> (${escapeHtml(attendee.username)})</li>`;
                            });
                            attendeesHtml += '</ul>';
                            attendeesDiv.innerHTML = attendeesHtml;
                        } else {
                            attendeesDiv.innerHTML = '<em class="text-muted">No attendees have confirmed attendance.</em>';
                        }
                    } else {
                        attendeesDiv.innerHTML = '<em class="text-muted">No attendees selected.</em>';
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('viewPmTrainingModal'));
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

    // Edit Form Submit
    document.getElementById('editPmTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('edit_pm_request_ajax', '1');
        
        const entries = Array.from(formData.entries()).filter(([key]) => key !== 'attendees');
        const cleanFormData = new FormData();
        entries.forEach(([key, value]) => cleanFormData.append(key, value));
        cleanFormData.append('edit_pm_request_ajax', '1');

        const btn = document.getElementById('editPmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';

        fetch(window.location.href, {
            method: 'POST',
            body: cleanFormData
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

    // Open Attendance Modal
    function openAttendanceModal(id) {
        currentPmRequestId = id;
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_attendees', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const attendeeList = document.getElementById('attendanceCheckList');
                    attendeeList.innerHTML = '';

                    <?php if (is_admin() || is_superadmin()): ?>
                    // Admin view - read-only with action buttons
                    data.attendees.forEach(attendee => {
                        const div = document.createElement('div');
                        div.className = 'attendee-item';
                        const attendedIcon = attendee.attended ?
                            '<i class="fas fa-check-circle text-success me-2"></i>' :
                            '<i class="fas fa-times-circle text-danger me-2"></i>';
                        div.innerHTML = `
                            <div class="attendee-info">
                                <div class="d-flex align-items-center">
                                    ${attendedIcon}
                                    <div>
                                        <strong>${escapeHtml(attendee.fullname || attendee.username)}</strong>
                                        <div class="small text-muted">${escapeHtml(attendee.username)}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="attendee-actions">
                                <button class="btn btn-sm btn-warning btn-attendee" onclick="editAttendee('${attendee.user_id}', '${escapeHtml(attendee.fullname || attendee.username)}')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-attendee" onclick="deleteAttendee('${attendee.user_id}', '${escapeHtml(attendee.fullname || attendee.username)}')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        attendeeList.appendChild(div);
                    });
                    <?php else: ?>
                    // Regular user view - editable
                    data.attendees.forEach(attendee => {
                        const div = document.createElement('div');
                        div.className = 'attendee-item';
                        div.innerHTML = `
                            <div class="attendee-info">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input attendee-checkbox"
                                        data-user-id="${attendee.user_id}"
                                        ${attendee.attended ? 'checked' : ''}>
                                    <label class="form-check-label">
                                        <strong>${escapeHtml(attendee.fullname || attendee.username)}</strong>
                                        <div class="small text-muted">${escapeHtml(attendee.username)}</div>
                                    </label>
                                </div>
                            </div>
                        `;
                        attendeeList.appendChild(div);
                    });
                    <?php endif; ?>

                    // Setup search for attendance modal
                    document.getElementById('attendanceListSearch').addEventListener('keyup', function() {
                        const searchTerm = this.value.toLowerCase();
                        const items = document.querySelectorAll('#attendanceCheckList .attendee-item');
                        items.forEach(item => {
                            const text = item.textContent.toLowerCase();
                            item.style.display = text.includes(searchTerm) ? '' : 'none';
                        });
                    });

                    const modal = new bootstrap.Modal(document.getElementById('attendanceCheckModal'));
                    modal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading attendees', 'danger');
            });
    }

    // Save Attendance (Regular Users Only)
    <?php if (!is_admin() && !is_superadmin()): ?>
    document.getElementById('saveAttendanceBtn')?.addEventListener('click', function() {
        if (!currentPmRequestId) return;

        const attendees = {};
        document.querySelectorAll('#attendanceCheckList input.attendee-checkbox').forEach(cb => {
            attendees[cb.dataset.userId] = cb.checked;
        });

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        const formData = new FormData();
        formData.append('update_pm_attendance', '1');
        formData.append('pm_request_id', currentPmRequestId);
        formData.append('attendees', JSON.stringify(attendees));

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('attendanceCheckModal'));
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
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Attendance';
        });
    });
    <?php endif; ?>

    // Open Add Attendee Modal
    function openAddAttendeeModal() {
        const attendeeName = prompt('Enter attendee name or username:');
        if (!attendeeName) return;

        if (!currentPmRequestId) {
            showToast('No training request selected', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('add_pm_attendee', '1');
        formData.append('pm_request_id', currentPmRequestId);
        formData.append('user_search', attendeeName);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Attendee added successfully', 'success');
                // Reload the attendance list
                openAttendanceModal(currentPmRequestId);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error adding attendee', 'danger');
        });
    }

    // Edit Attendee
    function editAttendee(userId, userName) {
        if (!currentPmRequestId) {
            showToast('No training request selected', 'danger');
            return;
        }

        const newAction = prompt(`Edit attendee "${userName}". Enter new name/username:`, userName);
        if (!newAction || newAction === userName) return;

        const formData = new FormData();
        formData.append('edit_pm_attendee', '1');
        formData.append('pm_request_id', currentPmRequestId);
        formData.append('user_id', userId);
        formData.append('new_user_search', newAction);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Attendee updated successfully', 'success');
                openAttendanceModal(currentPmRequestId);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error updating attendee', 'danger');
        });
    }

    // Delete Attendee
    function deleteAttendee(userId, userName) {
        if (!confirm(`Are you sure you want to remove "${userName}" from the attendance list?`)) {
            return;
        }

        if (!currentPmRequestId) {
            showToast('No training request selected', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('delete_pm_attendee', '1');
        formData.append('pm_request_id', currentPmRequestId);
        formData.append('user_id', userId);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Attendee removed successfully', 'success');
                openAttendanceModal(currentPmRequestId);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error removing attendee', 'danger');
        });
    }

    // Open PTR Attachment Modal
    function openPtrAttachmentModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    window.currentPtrRequestId = req.id;
                    
                    const currentPtrDisplay = document.getElementById('currentPtrDisplay');
                    if (req.ptr_file) {
                        currentPtrDisplay.innerHTML = `
                            <a href="<?= BASE_URL ?>/uploads/pm_training/${req.ptr_file}" target="_blank" class="me-3">
                                <i class="fas fa-file-pdf fa-2x"></i>
                            </a>
                            <div>
                                <p class="mb-0"><strong>Current File:</strong> ${escapeHtml(req.ptr_file)}</p>
                                <small class="text-muted">Click the icon to download</small>
                            </div>
                        `;
                    } else {
                        currentPtrDisplay.innerHTML = '<p class="text-muted mb-0">No PTR file uploaded yet</p>';
                    }
                    
                    document.getElementById('ptrFileInput').value = '';
                    
                    const modal = new bootstrap.Modal(document.getElementById('ptrAttachmentModal'));
                    modal.show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading PTR data', 'danger');
            });
    }

    // Save PTR Attachment
    document.getElementById('savePtrAttachmentBtn').addEventListener('click', function() {
        if (!window.currentPtrRequestId) return;

        const fileInput = document.getElementById('ptrFileInput');
        if (!fileInput.files || fileInput.files.length === 0) {
            showToast('Please select a file to upload', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('id', window.currentPtrRequestId);
        formData.append('ptr_file', fileInput.files[0]);
        formData.append('edit_pm_request_ajax', '1');

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('PTR file uploaded successfully!', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('ptrAttachmentModal'));
                modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred during upload', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Attachment';
        });
    });

    // Open Reschedule Modal
    function openReschedulePmModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_request', '1');
        url.searchParams.set('id', id);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const req = data.request;
                    document.getElementById('reschedule_pm_id').value = req.id;
                    document.getElementById('reschedule_pm_date_start').value = req.date_start;
                    document.getElementById('reschedule_pm_date_end').value = req.date_end;
                    document.getElementById('reschedule_pm_reason').value = '';

                    const modal = new bootstrap.Modal(document.getElementById('reschedulePmModal'));
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
    document.getElementById('reschedulePmForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const startDate = document.getElementById('reschedule_pm_date_start').value;
        const endDate = document.getElementById('reschedule_pm_date_end').value;

        if (startDate && endDate) {
            if (new Date(endDate) < new Date(startDate)) {
                showToast('End date cannot be earlier than start date', 'danger');
                return false;
            }
        }

        const formData = new FormData(this);
        formData.append('reschedule_pm_request_ajax', '1');

        const btn = document.getElementById('reschedulePmBtn');
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
                const modal = bootstrap.Modal.getInstance(document.getElementById('reschedulePmModal'));
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

    // Mark Training as Complete (Admin only)
    document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
        if (confirm('Mark this training as complete? The requester will be able to submit new training requests.')) {
            const id = document.getElementById('edit_pm_id').value;
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            const formData = new FormData();
            formData.append('complete_pm_request_ajax', '1');
            formData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editPmTrainingModal'));
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

    // Delete PM Request
    function deletePmRequest(id) {
        if (confirm('Are you sure you want to delete this training request?')) {
            const formData = new FormData();
            formData.append('delete_pm_request', '1');
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

    // Report Modal Functions
    <?php if (is_admin() || is_superadmin()): ?>
    
    function loadPmFilterOptions() {
        fetch(`${window.location.pathname}?get_pm_filter_options=1`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const yearSelect = document.getElementById('reportYear');
                    yearSelect.innerHTML = '<option value="">All Years</option>';
                    data.years.forEach(year => {
                        yearSelect.innerHTML += `<option value="${year}">${year}</option>`;
                    });
                }
            })
            .catch(error => console.error('Error loading filter options:', error));
    }

    function loadPmReportData() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
       
        let url = `${window.location.pathname}?get_pm_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
       
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('pmReportTableBody');
                if (data.success && data.reports.length > 0) {
                    tbody.innerHTML = '';
                    data.reports.forEach(report => {
                        const ptrLink = report.ptr_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${report.ptr_file}" target="_blank"><i class="fas fa-download"></i></a>` : '—';

                        tbody.innerHTML += `
                            <tr>
                                <td><strong>${escapeHtml(report.title)}</strong></td>
                                <td>${escapeHtml(report.venue)}</td>
                                <td>${escapeHtml(report.date_start)}</td>
                                <td>${escapeHtml(report.date_end)}</td>
                                <td>${escapeHtml(report.requester_name)}</td>
                                <td>${escapeHtml(report.hospital_order_no || '—')}</td>
                                <td class="amount-cell">₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                <td><span class="badge-status">${report.attended_count} / ${report.total_attendees}</span></td>
                                <td>${ptrLink}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 3rem 1rem;">
                                <i class="fas fa-inbox fa-2x mb-2" style="color: #cbd5e1;"></i>
                                <p style="color: #64748b; margin: 0;">No completed training requests found</p>
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading report data:', error);
                document.getElementById('pmReportTableBody').innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 3rem 1rem;">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2" style="color: #ef4444;"></i>
                            <p style="color: #64748b; margin: 0;">Error loading data. Please try again.</p>
                        </td>
                    </tr>
                `;
            });
    }

    function exportPmReportToCSV() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
       
        let url = `${window.location.pathname}?get_pm_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
       
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.reports.length > 0) {
                    let csvContent = "Title,Venue,From,To,Requester,Hospital Order No.,Amount,Attended,PTR File\n";
                   
                    data.reports.forEach(report => {
                        csvContent += `"${escapeCsv(report.title)}","${escapeCsv(report.venue)}","${report.date_start}","${report.date_end}","${escapeCsv(report.requester_name)}","${escapeCsv(report.hospital_order_no || '—')}","${report.amount}","${report.attended_count}/${report.total_attendees}","${report.ptr_file || ''}"\n`;
                    });
                   
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `completed_trainings_report_${new Date().toISOString().slice(0,10)}.csv`);
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

    document.getElementById('reportYear')?.addEventListener('change', loadPmReportData);
    document.getElementById('reportMonth')?.addEventListener('change', loadPmReportData);
    document.getElementById('exportPmReportBtn')?.addEventListener('click', exportPmReportToCSV);

    document.getElementById('pmReportModal')?.addEventListener('show.bs.modal', function() {
        loadPmFilterOptions();
        setTimeout(() => loadPmReportData(), 100);
    });
    
    <?php endif; ?>
</script>

</body>
</html>