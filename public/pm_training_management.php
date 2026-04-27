<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();

$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];

$success_message = '';
$error_message = '';

// Helper function to check if training end date has passed
function isTrainingDatePassed($end_date) {
    $today = new DateTime();
    $end = new DateTime($end_date);
    $end->setTime(23, 59, 59);
    return $today > $end;
}

// Handle AJAX Get Users for Attendance (Lazy Loading) - EXCLUDES already added users
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_users_for_attendance'])) {
    header('Content-Type: application/json');
    try {
        $search = trim($_GET['search'] ?? '');
        $request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
        
        // Get already added user IDs
        $existing_user_ids = [];
        if ($request_id > 0) {
            $stmt = $pdo->prepare("SELECT user_id FROM pm_training_attendance WHERE pm_training_request_id = ?");
            $stmt->execute([$request_id]);
            $existing_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $query = "SELECT id, username, CONCAT(fname, ' ', lname) as fullname 
                  FROM users 
                  WHERE role NOT IN ('admin', 'superadmin', 'proponent')";
        
        $params = [];
        
        // Exclude already added users
        if (!empty($existing_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($existing_user_ids), '?'));
            $query .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, $existing_user_ids);
        }
        
        if (!empty($search)) {
            $query .= " AND (fname LIKE ? OR lname LIKE ? OR username LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
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
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : NULL;
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks_input = trim($_POST['remarks'] ?? '');
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
        
        // Store only the text in remarks, committee_id is separate now
        $remarks_to_store = $remarks_input;
        
        // Insert training request
        $stmt = $pdo->prepare("INSERT INTO pm_training_requests (
            title, venue, date_start, time_start, date_end, time_end, hospital_order_no,
            amount, late_filing, remarks, requester_id, committee_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
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
            $remarks_to_store,
            $requester_id,
            $committee_id
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
                'committee_id' => $committee_id,
                'remarks' => $remarks_input,
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
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : NULL;
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks_input = trim($_POST['remarks'] ?? '');
        
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
        
        // Build update query
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
        $params[] = $remarks_input;
        
        $updates[] = "committee_id = ?";
        $params[] = $committee_id;
        
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
        
        $stmt = $pdo->prepare("SELECT status, ptr_file, date_end FROM pm_training_requests WHERE id = ?");
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
        
        $today = new DateTime();
        $endDate = new DateTime($request['date_end']);
        $endDate->setTime(23, 59, 59);
        
        if ($today <= $endDate) {
            echo json_encode(['success' => false, 'message' => 'Training cannot be marked as complete until the end date has passed.']);
            exit;
        }
        
        if (empty($request['ptr_file'])) {
            echo json_encode(['success' => false, 'message' => 'PTR file is required before marking as complete. Please upload PTR first.']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET status = 'complete', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training marked as complete successfully! Requester can now submit new requests.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Request Data for View
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_request_view'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                ptr.*,
                c.name as committee_name
            FROM pm_training_requests ptr
            LEFT JOIN committees c ON ptr.committee_id = c.id
            WHERE ptr.id = ?
        ");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        // Get requester name
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname, username FROM users WHERE id = ?");
        $stmt->execute([$request['requester_id']]);
        $user = $stmt->fetch();
        $request['requester_name'] = $user['fullname'] ?: ($user['username'] ?? 'Unknown');
        
        // Get attendees
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

// Handle AJAX Get Request Data for Edit
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_request'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                ptr.*,
                c.name as committee_name
            FROM pm_training_requests ptr
            LEFT JOIN committees c ON ptr.committee_id = c.id
            WHERE ptr.id = ?
        ");
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
        
        $stmt = $pdo->prepare("SELECT requester_id, date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        $requestRequesterId = $request ? $request['requester_id'] : 0;
        $date_end = $request ? $request['date_end'] : null;
        
        $isPastEndDate = false;
        if ($date_end) {
            $today = new DateTime();
            $endDate = new DateTime($date_end);
            $endDate->setTime(23, 59, 59);
            $isPastEndDate = $today > $endDate;
        }
        
        $stmt = $pdo->prepare("
            SELECT pta.user_id, u.username, CONCAT(u.fname, ' ', u.lname) as fullname, pta.attended
            FROM pm_training_attendance pta
            LEFT JOIN users u ON pta.user_id = u.id
            WHERE pta.pm_training_request_id = ?
            ORDER BY u.fname, u.lname
        ");
        $stmt->execute([$id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'attendees' => $attendees,
            'requestRequesterId' => $requestRequesterId,
            'isPastEndDate' => $isPastEndDate
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Update Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pm_attendance'])) {
    header('Content-Type: application/json');
    try {
        $pm_request_id = (int)$_POST['pm_request_id'];
        
        $stmt = $pdo->prepare("SELECT date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$pm_request_id]);
        $training = $stmt->fetch();
        
        if ($training) {
            $today = new DateTime();
            $endDate = new DateTime($training['date_end']);
            $endDate->setTime(23, 59, 59);
            
            if ($today <= $endDate) {
                echo json_encode(['success' => false, 'message' => 'Attendance checklist can only be updated after the training end date has passed.']);
                exit;
            }
        }
        
        $attendees = isset($_POST['attendees']) ? json_decode($_POST['attendees'], true) : [];

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

// Handle AJAX Add Multiple Attendees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_multiple_pm_attendees'])) {
    header('Content-Type: application/json');
    try {
        $pm_request_id = (int)$_POST['pm_request_id'];
        
        $stmt = $pdo->prepare("SELECT date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$pm_request_id]);
        $training = $stmt->fetch();
        
        if ($training) {
            $today = new DateTime();
            $endDate = new DateTime($training['date_end']);
            $endDate->setTime(23, 59, 59);
            
            if ($today > $endDate) {
                echo json_encode(['success' => false, 'message' => 'Attendees can only be added or removed before the training end date has passed.']);
                exit;
            }
        }
        
        $user_ids = isset($_POST['user_ids']) ? json_decode($_POST['user_ids'], true) : [];
        
        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'message' => 'No users selected']);
            exit;
        }
        
        $added_count = 0;
        $stmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, attended) VALUES (?, ?, 0)");
        
        foreach ($user_ids as $user_id) {
            $check_stmt = $pdo->prepare("SELECT id FROM pm_training_attendance WHERE pm_training_request_id = ? AND user_id = ?");
            $check_stmt->execute([$pm_request_id, (int)$user_id]);
            if (!$check_stmt->fetch()) {
                $stmt->execute([$pm_request_id, (int)$user_id]);
                $added_count++;
            }
        }

        echo json_encode(['success' => true, 'message' => $added_count . ' attendee(s) added successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Add Single Attendee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pm_attendee'])) {
    header('Content-Type: application/json');
    try {
        $pm_request_id = (int)$_POST['pm_request_id'];
        
        $stmt = $pdo->prepare("SELECT date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$pm_request_id]);
        $training = $stmt->fetch();
        
        if ($training) {
            $today = new DateTime();
            $endDate = new DateTime($training['date_end']);
            $endDate->setTime(23, 59, 59);
            
            if ($today > $endDate) {
                echo json_encode(['success' => false, 'message' => 'Attendees can only be added or removed before the training end date has passed.']);
                exit;
            }
        }
        
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        $stmt = $pdo->prepare("SELECT id FROM pm_training_attendance WHERE pm_training_request_id = ? AND user_id = ?");
        $stmt->execute([$pm_request_id, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is already in the attendance list']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, attended) VALUES (?, ?, 0)");
        $stmt->execute([$pm_request_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Attendee added successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Delete Attendee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pm_attendee'])) {
    header('Content-Type: application/json');
    try {
        $pm_request_id = (int)$_POST['pm_request_id'];
        
        $stmt = $pdo->prepare("SELECT date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$pm_request_id]);
        $training = $stmt->fetch();
        
        if ($training) {
            $today = new DateTime();
            $endDate = new DateTime($training['date_end']);
            $endDate->setTime(23, 59, 59);
            
            if ($today > $endDate) {
                echo json_encode(['success' => false, 'message' => 'Attendees can only be added or removed before the training end date has passed.']);
                exit;
            }
        }
        
        $user_id = (int)$_POST['user_id'];

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

// Handle AJAX Get Report Data (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_report_data'])) {
    header('Content-Type: application/json');
    try {
        if (!is_admin() && !is_superadmin()) {
            echo json_encode(['success' => false, 'message' => 'Only admins can view reports']);
            exit;
        }
        
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        
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
        
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM pm_training_requests WHERE status = 'complete' ORDER BY year DESC");
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

// Fetch venues for dropdown
$cache_key = 'pm_training_venues';
$venues = $_SESSION[$cache_key] ?? null;

if ($venues === null) {
    $stmt = $pdo->prepare("SELECT DISTINCT venue FROM pm_training_requests WHERE venue IS NOT NULL ORDER BY venue");
    $stmt->execute();
    $venues = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION[$cache_key] = $venues;
}

// Check for incomplete PTR uploads
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
$filter_committee = isset($_GET['filter_committee']) && !empty($_GET['filter_committee']) ? (int)$_GET['filter_committee'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
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

if (!empty($filter_committee)) {
    $where_clause[] = "ptr.committee_id = ?";
    $main_params[] = $filter_committee;
    $count_where_clause[] = "ptr.committee_id = ?";
}

$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";
$count_where_sql = !empty($count_where_clause) ? "WHERE " . implode(" AND ", $count_where_clause) : "";

// Build count parameters array
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
if (!empty($filter_committee)) {
    $count_params[] = $filter_committee;
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id $count_where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Main query with pagination
$query = "SELECT
    ptr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    c.name as committee_name
    FROM pm_training_requests ptr
    LEFT JOIN users u ON ptr.requester_id = u.id
    LEFT JOIN committees c ON ptr.committee_id = c.id
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
    <link href="<?= BASE_URL ?>/assets/css/training_request.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .days-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .days-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .days-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
            animation: pulse-warning 1.5s infinite;
        }

        .days-normal {
            background: #e2e3e5;
            color: #383d41;
        }

        @keyframes pulse-warning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .table tbody tr.warning-row {
            background-color: #fff3cd !important;
        }

        .table tbody tr.danger-row {
            background-color: #f8d7da !important;
        }

        .table tbody tr.warning-row:hover {
            background-color: #ffe69c !important;
        }

        .table tbody tr.danger-row:hover {
            background-color: #f5c2c7 !important;
        }

        .late-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .late-yes {
            background: #ff69b4;
            color: white;
        }

        .late-no {
            background: #808080;
            color: white;
        }
        
        .add-attendee-search-results {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .btn-disabled-date {
            opacity: 0.65;
            cursor: not-allowed;
        }
        
        .attendee-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
        }
        
        .attendee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .attendee-item:last-child {
            border-bottom: none;
        }
        
        .attendee-item:hover {
            background: #f8f9fa;
        }
        
        .attendee-info {
            flex: 1;
        }
        
        .attendee-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-attendee {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e9ecef;
            border-top-color: #007bff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .attendance-status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .attendance-yes {
            background: #d4edda;
            color: #155724;
        }
        
        .attendance-no {
            background: #f8d7da;
            color: #721c24;
        }
        
        .user-list-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .user-list-item:hover {
            background-color: #f8f9fa;
        }
        
        .user-list-item input[type="checkbox"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .user-list-item .user-info {
            flex: 1;
        }
        
        .user-list-item .user-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .user-list-item .user-username {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .select-all-checkbox {
            margin-right: 10px;
        }
        
        .bulk-actions-bar {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
                <h3 class="mb-0">PM Training Request Management</h3>
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
                            $committees_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
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
                            <th>Late Filing</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pm_requests)): ?>
                            <?php foreach ($pm_requests as $request): ?>
                                <?php
                                $end_date = new DateTime($request['date_end']);
                                $current_date = new DateTime();
                                $interval = $current_date->diff($end_date);
                                $days_elapsed = $interval->days;
                                $is_past = $current_date > $end_date;
                                $has_ptr = !empty($request['ptr_file']);
                                $is_complete = ($request['status'] === 'complete');
                                $row_class = '';
                                if (!$has_ptr && $is_past && $request['status'] !== 'complete') {
                                    if ($days_elapsed >= 20 && $days_elapsed < 30) {
                                        $row_class = 'warning-row';
                                    } elseif ($days_elapsed >= 30) {
                                        $row_class = 'danger-row';
                                    }
                                }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if (!$has_ptr && $is_past && $request['status'] !== 'complete'): ?>
                                            <br>
                                            <?php if ($days_elapsed >= 20 && $days_elapsed < 30): ?>
                                                <span class="days-badge days-warning"><?= $days_elapsed ?> days overdue</span>
                                            <?php elseif ($days_elapsed >= 30): ?>
                                                <span class="days-badge days-danger"><?= $days_elapsed ?> days overdue</span>
                                            <?php else: ?>
                                                <span class="days-badge days-normal"><?= $days_elapsed ?> days</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($request['venue']) ?></td>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></td>
                                    <td><?= !empty($request['time_start']) ? date('H:i', strtotime($request['time_start'])) : '-' ?></td>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></td>
                                    <td><?= htmlspecialchars($request['requester_name']) ?></td>
                                    <td><?= !empty($request['committee_name']) ? htmlspecialchars($request['committee_name']) : '-' ?></td>
                                    <td><?= htmlspecialchars($request['hospital_order_no'] ?? '-') ?></td>
                                    <td>₱<?= number_format($request['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($request['late_filing'] == 1): ?>
                                            <span class="late-badge late-yes">Yes</span>
                                        <?php else: ?>
                                            <span class="late-badge late-no">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                            <?php 
                                            $remarks = $request['remarks'] ?? '';
                                            echo htmlspecialchars(strlen($remarks) > 30 ? substr($remarks, 0, 30) . '...' : $remarks);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: row; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <?php if ($is_complete): ?>
                                                <button class="btn-action btn-view" onclick="openViewPmModal(<?= $request['id'] ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-edit" onclick="openEditPmModal(<?= $request['id'] ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action btn-reschedule" onclick="openReschedulePmModal(<?= $request['id'] ?>)" title="Reschedule">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                                <button class="btn-action btn-delete" onclick="deletePmRequest(<?= $request['id'] ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center py-5">
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
                            <label class="form-label">Committee</label>
                            <select name="committee_id" class="form-select" id="add_committee_id">
                                <option value="">-- Select Committee --</option>
                                <?php
                                $committees_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
                                $all_committees = $committees_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($all_committees as $comm): 
                                ?>
                                    <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                <form id="editPmTrainingForm" enctype="multipart/form-data">
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
                            <label class="form-label">Committee</label>
                            <select name="committee_id" class="form-select" id="edit_pm_committee">
                                <option value="">-- Select Committee --</option>
                                <?php
                                $committees_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
                                $all_committees = $committees_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($all_committees as $comm): 
                                ?>
                                    <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                                <small class="text-muted d-block mt-2" id="completeHelpText">Clicking this will allow the requester to submit new training requests.</small>
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

<!-- View PM Training Request Modal -->
<div class="modal fade" id="viewPmTrainingModal" tabindex="-1" aria-labelledby="viewPmTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewPmTrainingLabel">
                    <i class="fas fa-eye me-2"></i>View PM Training Request
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
                        <label class="form-label fw-bold">Committee</label>
                        <p class="form-control-static" id="view_pm_committee">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Program Manager</label>
                        <p class="form-control-static" id="view_pm_requester">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Amount (PHP)</label>
                        <p class="form-control-static" id="view_pm_amount">-</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Late Filing</label>
                        <p class="form-control-static" id="view_pm_late_filing">-</p>
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
                <div class="d-flex justify-content-between align-items-center mb-3" id="attendanceHeaderActions">
                    <div class="attendance-search flex-grow-1 me-3">
                        <input type="text" class="form-control" id="attendanceListSearch" placeholder="Search attendees...">
                    </div>
                    <div id="addAttendeeButtonContainer"></div>
                </div>
                <div id="attendeeListContainer" class="attendee-list">
                </div>
            </div>
            <div class="modal-footer">
                <div id="saveAttendanceButtonContainer"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Multiple Attendees Modal -->
<div class="modal fade" id="addMultipleAttendeesModal" tabindex="-1" aria-labelledby="addMultipleAttendeesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addMultipleAttendeesLabel">
                    <i class="fas fa-users me-2"></i>Add Attendees
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" id="addMultipleAttendeeSearch" placeholder="Search users by name or username...">
                        <button class="btn btn-outline-secondary" id="clearSearchBtn" type="button">Clear</button>
                    </div>
                    <small class="text-muted">Type at least 2 characters to search, or leave empty to see all available users</small>
                </div>
                
                <div class="bulk-actions-bar">
                    <div>
                        <input type="checkbox" id="selectAllUsers" class="select-all-checkbox">
                        <label for="selectAllUsers" class="mb-0">Select All</label>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-success" id="addSelectedUsersBtn">
                            <i class="fas fa-user-plus me-1"></i> Add Selected
                        </button>
                    </div>
                </div>
                
                <div id="userSearchResults" class="add-attendee-search-results">
                    <div class="text-center py-3">
                        <span class="loading-spinner"></span> Loading users...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload New PTR File</label>
                    <input type="file" class="form-control" id="ptrFileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small class="text-muted d-block mt-2" id="ptrUploadHelpText">Accepted formats: PDF, JPG, JPEG, PNG, DOC, DOCX</small>
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
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="exportPmReportBtn" class="btn btn-success">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="pmReportTable">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Venue</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Program Manager</th>
                                <th>Hospital Order No.</th>
                                <th>Amount</th>
                                <th>Attnd</th>
                                <th>PTR</th>
                            </tr>
                        </thead>
                        <tbody id="pmReportTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                    <p>Loading data...</p>
                                </div>
                             </div>
                            </div>
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
    let currentPmRequestId = null;
    let attendeeSearchTimeout = null;
    let isPastEndDateForAttendance = false;

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

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // Load initial available users for Add Attendee Modal
    function loadInitialAvailableUsers() {
        const resultsDiv = document.getElementById('userSearchResults');
        resultsDiv.innerHTML = '<div class="text-center py-3"><span class="loading-spinner"></span> Loading users...</div>';
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', currentPmRequestId);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.users.length > 0) {
                        resultsDiv.innerHTML = '';
                        let userCheckboxes = [];
                        
                        data.users.forEach(user => {
                            const div = document.createElement('div');
                            div.className = 'user-list-item';
                            div.innerHTML = `
                                <input type="checkbox" class="user-checkbox" value="${user.id}" data-name="${escapeHtml(user.fullname)}">
                                <div class="user-info">
                                    <div class="user-name">${escapeHtml(user.fullname)}</div>
                                    <div class="user-username">${escapeHtml(user.username)}</div>
                                </div>
                            `;
                            resultsDiv.appendChild(div);
                            userCheckboxes.push(div.querySelector('.user-checkbox'));
                        });
                        
                        const selectAllCheckbox = document.getElementById('selectAllUsers');
                        if (selectAllCheckbox) {
                            selectAllCheckbox.onchange = function() {
                                userCheckboxes.forEach(cb => {
                                    cb.checked = this.checked;
                                });
                            };
                            
                            userCheckboxes.forEach(cb => {
                                cb.onchange = function() {
                                    const allChecked = userCheckboxes.every(c => c.checked);
                                    selectAllCheckbox.checked = allChecked;
                                    const someChecked = userCheckboxes.some(c => c.checked);
                                    selectAllCheckbox.indeterminate = !allChecked && someChecked;
                                };
                            });
                        }
                    } else {
                        resultsDiv.innerHTML = '<div class="text-center py-3 text-muted">No users available to add</div>';
                    }
                } else {
                    resultsDiv.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
            });
    }

    // Load users for search in Add Modal
    function loadUsersForAddModal(search = '') {
        const resultsDiv = document.getElementById('userSearchResults');
        resultsDiv.innerHTML = '<div class="text-center py-3"><span class="loading-spinner"></span> Searching...</div>';
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', currentPmRequestId);
        if (search) {
            url.searchParams.set('search', search);
        }
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.users.length > 0) {
                        resultsDiv.innerHTML = '';
                        let userCheckboxes = [];
                        
                        data.users.forEach(user => {
                            const div = document.createElement('div');
                            div.className = 'user-list-item';
                            div.innerHTML = `
                                <input type="checkbox" class="user-checkbox" value="${user.id}" data-name="${escapeHtml(user.fullname)}">
                                <div class="user-info">
                                    <div class="user-name">${escapeHtml(user.fullname)}</div>
                                    <div class="user-username">${escapeHtml(user.username)}</div>
                                </div>
                            `;
                            resultsDiv.appendChild(div);
                            userCheckboxes.push(div.querySelector('.user-checkbox'));
                        });
                        
                        const selectAllCheckbox = document.getElementById('selectAllUsers');
                        if (selectAllCheckbox) {
                            selectAllCheckbox.onchange = function() {
                                userCheckboxes.forEach(cb => {
                                    cb.checked = this.checked;
                                });
                            };
                            
                            userCheckboxes.forEach(cb => {
                                cb.onchange = function() {
                                    const allChecked = userCheckboxes.every(c => c.checked);
                                    selectAllCheckbox.checked = allChecked;
                                    const someChecked = userCheckboxes.some(c => c.checked);
                                    selectAllCheckbox.indeterminate = !allChecked && someChecked;
                                };
                            });
                        }
                    } else {
                        resultsDiv.innerHTML = '<div class="text-center py-3 text-muted">No users found</div>';
                    }
                } else {
                    resultsDiv.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="text-center py-3 text-danger">Error loading users</div>';
            });
    }

    // Load users for attendance in Add Modal
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

    // Venue dropdown
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
    document.getElementById('addPmTrainingForm')?.addEventListener('submit', function(e) {
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
                    
                    const committeeSelect = document.getElementById('edit_pm_committee');
                    if (committeeSelect && req.committee_id) {
                        committeeSelect.value = req.committee_id;
                    }
                    
                    const lateFilingCheckbox = document.getElementById('edit_pm_late_filing');
                    if (lateFilingCheckbox) {
                        lateFilingCheckbox.checked = req.late_filing == 1;
                    }
                    
                    document.getElementById('edit_pm_remarks').value = req.remarks || '';

                    const today = new Date();
                    const endDate = new Date(req.date_end);
                    endDate.setHours(23, 59, 59);
                    const isPastEndDate = today > endDate;
                    
                    const ptrFileInput = document.getElementById('ptrFileInput');
                    const ptrUploadHelpText = document.getElementById('ptrUploadHelpText');
                    const attendanceBtn = document.getElementById('editAttendanceListBtn');
                    
                    if (ptrFileInput) {
                        if (!isPastEndDate) {
                            ptrFileInput.disabled = true;
                            ptrFileInput.classList.add('btn-disabled-date');
                            if (ptrUploadHelpText) {
                                ptrUploadHelpText.innerHTML = '<i class="fas fa-clock me-1 text-warning"></i>PTR can only be uploaded after the training end date (' + new Date(req.date_end).toLocaleDateString() + ') has passed.';
                                ptrUploadHelpText.classList.add('text-warning');
                            }
                        } else {
                            ptrFileInput.disabled = false;
                            ptrFileInput.classList.remove('btn-disabled-date');
                            if (ptrUploadHelpText) {
                                ptrUploadHelpText.innerHTML = 'Accepted formats: PDF, JPG, JPEG, PNG, DOC, DOCX';
                                ptrUploadHelpText.classList.remove('text-warning');
                            }
                        }
                    }
                    
                    if (attendanceBtn) {
                        if (!isPastEndDate) {
                            attendanceBtn.disabled = false;
                            attendanceBtn.classList.remove('btn-disabled-date');
                            attendanceBtn.title = 'Manage attendees (add/remove) - available until end date';
                        } else {
                            attendanceBtn.disabled = false;
                            attendanceBtn.classList.remove('btn-disabled-date');
                            attendanceBtn.title = 'Mark attendance (check who attended) - available after end date';
                        }
                    }

                    const shouldShowCompleteButton = req.status === 'approved';

                    const approvalSection = document.getElementById('approvalSection');
                    if (approvalSection) {
                        const approveSection = document.getElementById('approveCheckboxSection');
                        const completeSection = document.getElementById('completeButtonSection');
                        const completeHelpText = document.getElementById('completeHelpText');
                        const markCompleteBtn = document.getElementById('markCompleteBtn');
                        
                        if (shouldShowCompleteButton) {
                            if (approveSection) approveSection.style.display = 'none';
                            if (completeSection) completeSection.style.display = 'block';
                            
                            const hasPtr = !!req.ptr_file;
                            
                            if (markCompleteBtn) {
                                if (!hasPtr || !isPastEndDate) {
                                    markCompleteBtn.disabled = true;
                                    markCompleteBtn.classList.add('btn-secondary');
                                    markCompleteBtn.classList.remove('btn-success');
                                    
                                    if (!hasPtr && isPastEndDate) {
                                        if (completeHelpText) {
                                            completeHelpText.innerHTML = '<i class="fas fa-exclamation-circle me-1 text-danger"></i>PTR file is required before marking as complete. Please upload PTR first.';
                                            completeHelpText.classList.add('text-danger');
                                        }
                                    } else if (!isPastEndDate) {
                                        if (completeHelpText) {
                                            completeHelpText.innerHTML = '<i class="fas fa-calendar-day me-1 text-warning"></i>Training cannot be marked as complete until the end date (' + new Date(req.date_end).toLocaleDateString() + ') has passed.';
                                            completeHelpText.classList.add('text-warning');
                                        }
                                    } else {
                                        if (completeHelpText) {
                                            completeHelpText.innerHTML = 'Clicking this will allow the requester to submit new training requests.';
                                            completeHelpText.classList.remove('text-danger', 'text-warning');
                                        }
                                    }
                                } else {
                                    markCompleteBtn.disabled = false;
                                    markCompleteBtn.classList.remove('btn-secondary');
                                    markCompleteBtn.classList.add('btn-success');
                                    if (completeHelpText) {
                                        completeHelpText.innerHTML = 'Click to mark this training as complete. This will allow the requester to submit new requests.';
                                        completeHelpText.classList.remove('text-danger', 'text-warning');
                                    }
                                }
                            }
                        } else {
                            if (approveSection) approveSection.style.display = 'block';
                            if (completeSection) completeSection.style.display = 'none';
                            const approveCheckbox = document.getElementById('edit_pm_approve_status');
                            if (approveCheckbox) approveCheckbox.checked = false;
                        }
                    }

                    window.currentEditPmRequestId = req.id;
                    
                    const attendanceBtnClick = document.getElementById('editAttendanceListBtn');
                    if (attendanceBtnClick) {
                        attendanceBtnClick.onclick = function() {
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

    // Open View Modal
    function openViewPmModal(id) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_year');
        url.searchParams.delete('filter_month');
        url.searchParams.delete('filter_committee');
        url.searchParams.delete('search');
        url.searchParams.set('get_pm_request_view', '1');
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
                    
                    const committeeDisplay = req.committee_name || '-';
                    document.getElementById('view_pm_committee').innerHTML = escapeHtml(committeeDisplay);
                    
                    document.getElementById('view_pm_requester').innerHTML = escapeHtml(req.requester_name || 'Unknown');
                    document.getElementById('view_pm_amount').innerHTML = '₱' + parseFloat(req.amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    document.getElementById('view_pm_late_filing').innerHTML = req.late_filing == 1 ? '<span class="late-badge late-yes">Yes</span>' : '<span class="late-badge late-no">No</span>';
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
                showToast('Error loading request data: ' + error.message, 'danger');
            });
    }

    // Edit Form Submit
    document.getElementById('editPmTrainingForm')?.addEventListener('submit', function(e) {
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
                    const attendeeListContainer = document.getElementById('attendeeListContainer');
                    attendeeListContainer.innerHTML = '';
                    
                    isPastEndDateForAttendance = data.isPastEndDate;
                    
                    const isRequester = <?= $current_user_id ?> === data.requestRequesterId;
                    const isAdminUser = <?= json_encode(is_admin() || is_superadmin()) ?>;
                    const canEdit = isRequester || isAdminUser;
                    
                    const canAddDeleteAttendees = !data.isPastEndDate && canEdit;
                    const canMarkAttendance = data.isPastEndDate && canEdit;
                    
                    const addButtonContainer = document.getElementById('addAttendeeButtonContainer');
                    if (addButtonContainer) {
                        if (canAddDeleteAttendees) {
                            addButtonContainer.innerHTML = '<button class="btn btn-primary btn-sm" onclick="openAddMultipleAttendeesModal()"><i class="fas fa-user-plus me-1"></i>Add Attendees</button>';
                        } else {
                            addButtonContainer.innerHTML = '';
                        }
                    }
                    
                    const saveButtonContainer = document.getElementById('saveAttendanceButtonContainer');
                    if (saveButtonContainer) {
                        if (canMarkAttendance) {
                            saveButtonContainer.innerHTML = '<button class="btn btn-success" id="saveAttendanceBtn"><i class="fas fa-save me-1"></i>Save Attendance</button>';
                            setTimeout(() => {
                                document.getElementById('saveAttendanceBtn')?.addEventListener('click', saveAttendance);
                            }, 100);
                        } else {
                            saveButtonContainer.innerHTML = '';
                        }
                    }
                    
                    if (data.attendees.length > 0) {
                        data.attendees.forEach(attendee => {
                            const div = document.createElement('div');
                            div.className = 'attendee-item';
                            
                            if (canMarkAttendance) {
                                div.innerHTML = `
                                    <div class="attendee-info">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input attendance-checkbox"
                                                data-user-id="${attendee.user_id}"
                                                ${attendee.attended ? 'checked' : ''}>
                                            <label class="form-check-label">
                                                <strong>${escapeHtml(attendee.fullname || attendee.username)}</strong>
                                                <div class="small text-muted">${escapeHtml(attendee.username)}</div>
                                            </label>
                                        </div>
                                    </div>
                                `;
                            } else if (canAddDeleteAttendees) {
                                div.innerHTML = `
                                    <div class="attendee-info">
                                        <div>
                                            <strong>${escapeHtml(attendee.fullname || attendee.username)}</strong>
                                            <div class="small text-muted">${escapeHtml(attendee.username)}</div>
                                            ${attendee.attended ? '<span class="attendance-status-badge attendance-yes ms-2">Attended</span>' : ''}
                                        </div>
                                    </div>
                                    <div class="attendee-actions">
                                        <button class="btn btn-sm btn-danger btn-attendee" onclick="deleteAttendee('${attendee.user_id}', '${escapeHtml(attendee.fullname || attendee.username)}')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                `;
                            } else {
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
                                `;
                            }
                            attendeeListContainer.appendChild(div);
                        });
                    } else {
                        attendeeListContainer.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-users fa-2x mb-2"></i><p>No attendees added yet.</p></div>';
                    }

                    const searchInput = document.getElementById('attendanceListSearch');
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.onkeyup = function() {
                            const searchTerm = this.value.toLowerCase();
                            const items = document.querySelectorAll('#attendeeListContainer .attendee-item');
                            items.forEach(item => {
                                const text = item.textContent.toLowerCase();
                                item.style.display = text.includes(searchTerm) ? '' : 'none';
                            });
                        };
                    }

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

    // Save Attendance function
    function saveAttendance() {
        if (!currentPmRequestId) return;

        const attendees = {};
        document.querySelectorAll('#attendeeListContainer input.attendance-checkbox').forEach(cb => {
            attendees[cb.dataset.userId] = cb.checked ? 1 : 0;
        });

        const btn = document.getElementById('saveAttendanceBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
        }

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
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Attendance';
            }
        });
    }

    // Open Add Multiple Attendees Modal
    function openAddMultipleAttendeesModal() {
        if (!currentPmRequestId) {
            showToast('No training request selected', 'danger');
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('addMultipleAttendeesModal'));
        modal.show();
        
        // Clear search input
        document.getElementById('addMultipleAttendeeSearch').value = '';
        document.getElementById('selectAllUsers').checked = false;
        
        // Load initial users immediately
        loadInitialAvailableUsers();
    }

    // Search for users to add (multiple selection)
    let multipleAttendeeSearchTimeout;
    document.getElementById('addMultipleAttendeeSearch')?.addEventListener('input', function() {
        clearTimeout(multipleAttendeeSearchTimeout);
        const searchTerm = this.value.trim();
        
        if (searchTerm.length >= 2) {
            multipleAttendeeSearchTimeout = setTimeout(() => {
                loadUsersForAddModal(searchTerm);
            }, 300);
        } else if (searchTerm.length === 0) {
            loadInitialAvailableUsers();
        }
    });

    // Clear search button
    document.getElementById('clearSearchBtn')?.addEventListener('click', function() {
        document.getElementById('addMultipleAttendeeSearch').value = '';
        document.getElementById('selectAllUsers').checked = false;
        loadInitialAvailableUsers();
    });

    // Add selected users to attendance
    document.getElementById('addSelectedUsersBtn')?.addEventListener('click', function() {
        const selectedUsers = Array.from(document.querySelectorAll('#userSearchResults .user-checkbox:checked'))
            .map(cb => cb.value);
        
        if (selectedUsers.length === 0) {
            showToast('Please select at least one user to add', 'warning');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Adding...';

        const formData = new FormData();
        formData.append('add_multiple_pm_attendees', '1');
        formData.append('pm_request_id', currentPmRequestId);
        formData.append('user_ids', JSON.stringify(selectedUsers));

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('addMultipleAttendeesModal'));
                modal.hide();
                openAttendanceModal(currentPmRequestId);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error adding attendees', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-plus me-1"></i> Add Selected';
        });
    });

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
    document.getElementById('savePtrAttachmentBtn')?.addEventListener('click', function() {
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
    document.getElementById('reschedulePmForm')?.addEventListener('submit', function(e) {
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
                        const ptrLink = report.ptr_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${report.ptr_file}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i></a>` : '—';

                        tbody.innerHTML += `
                            <tr>
                                <td><strong>${escapeHtml(report.title)}</strong></td>
                                <td>${escapeHtml(report.venue)}</div>
                                <td>${escapeHtml(report.date_start)}</div>
                                <td>${escapeHtml(report.date_end)}</div>
                                <td>${escapeHtml(report.requester_name)}</div>
                                <td>${escapeHtml(report.hospital_order_no || '—')}</div>
                                <td class="amount-cell">₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                                <td><span class="badge-status">${report.attended_count} / ${report.total_attendees}</span></div>
                                <td>${ptrLink}</div>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                <p class="text-muted mb-0">No completed training requests found</p>
                            </div>
                        </table>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading report data:', error);
                document.getElementById('pmReportTableBody').innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <p>Error loading data. Please try again.</p>
                        </div>
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

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Any additional initialization can go here
    });
    
</script>

</body>
</html>