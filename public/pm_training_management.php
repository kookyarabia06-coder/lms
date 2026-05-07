<?php
// Start output buffering at the VERY beginning
ob_start();

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

// Set error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_login();

$pdo = $pdo;
$current_user_id = $_SESSION['user']['id'];
$is_admin = is_admin() || is_superadmin();

$success_message = '';
$error_message = '';

// Helper function to calculate late filing based on created_at
function calculateLateFiling($date_start, $created_at) {
    if (empty($date_start) || empty($created_at)) {
        return 0;
    }
    
    $start = new DateTime($date_start);
    $filed = new DateTime($created_at);
    $interval = $filed->diff($start)->days;
    
    // Late if filed within 30 days before start date
    return ($interval <= 29) ? 1 : 0;
}

// Handle AJAX Get Users for Attendance - EXCLUDES already added users
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_users_for_attendance'])) {
    header('Content-Type: application/json');
    try {
        $search = trim($_GET['search'] ?? '');
        $request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
        $current_batch_id = isset($_GET['current_batch_id']) ? (int)$_GET['current_batch_id'] : 0;
        
        // Get already added user IDs from all batches except current batch
        $existing_user_ids = [];
        if ($request_id > 0) {
            if ($current_batch_id > 0) {
                $stmt = $pdo->prepare("SELECT user_id FROM pm_training_attendance WHERE pm_training_request_id = ? AND batch_id != ?");
                $stmt->execute([$request_id, $current_batch_id]);
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM pm_training_attendance WHERE pm_training_request_id = ?");
                $stmt->execute([$request_id]);
            }
            $existing_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $query = "SELECT id, username, CONCAT(fname, ' ', lname) as fullname 
                  FROM users 
                  WHERE role NOT IN ('admin', 'superadmin', 'proponent')";
        
        $params = [];
        
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

// Handle AJAX Get Batch Details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_batch_details'])) {
    header('Content-Type: application/json');
    try {
        $batch_id = (int)$_GET['batch_id'];
        
        $stmt = $pdo->prepare("
            SELECT b.*, ptr.date_start as training_start, ptr.date_end as training_end 
            FROM pm_training_batches b
            LEFT JOIN pm_training_requests ptr ON b.pm_training_request_id = ptr.id
            WHERE b.id = ?
        ");
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$batch) {
            echo json_encode(['success' => false, 'message' => 'Batch not found']);
            exit;
        }
        
        // Get attendees for this batch
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, CONCAT(u.fname, ' ', u.lname) as fullname
            FROM pm_training_attendance a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.batch_id = ?
        ");
        $stmt->execute([$batch_id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'batch' => $batch,
            'attendees' => $attendees
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Update Batch (from batch modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch_ajax'])) {
    header('Content-Type: application/json');
    try {
        $batch_id = (int)$_POST['batch_id'];
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $start_time = $_POST['start_time'] ?? null;
        $end_time = $_POST['end_time'] ?? null;
        $attendees = isset($_POST['attendees']) ? json_decode($_POST['attendees'], true) : [];
        
        $errors = [];
        if (empty($start_date)) $errors[] = "Start date is required";
        if (empty($end_date)) $errors[] = "End date is required";
        
        if (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End date cannot be earlier than start date";
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        // Get the training request ID for this batch
        $stmt = $pdo->prepare("SELECT pm_training_request_id FROM pm_training_batches WHERE id = ?");
        $stmt->execute([$batch_id]);
        $request_id = $stmt->fetchColumn();
        
        // Update batch
        $stmt = $pdo->prepare("
            UPDATE pm_training_batches SET 
                batch_start_date = ?, batch_end_date = ?, 
                batch_start_time = ?, batch_end_time = ?
            WHERE id = ?
        ");
        $stmt->execute([$start_date, $end_date, $start_time ?: null, $end_time ?: null, $batch_id]);
        
        // Update attendance records for this batch
        // First, remove all existing attendees for this batch
        $stmt = $pdo->prepare("DELETE FROM pm_training_attendance WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        
        // Then add the new attendees
        if (!empty($attendees)) {
            $attStmt = $pdo->prepare("INSERT INTO pm_training_attendance (pm_training_request_id, user_id, batch_id, attended) VALUES (?, ?, ?, 0)");
            foreach ($attendees as $user_id) {
                $attStmt->execute([$request_id, (int)$user_id, $batch_id]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Batch updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Add Batch to Training (from batches modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch_to_training'])) {
    header('Content-Type: application/json');
    try {
        $training_id = (int)$_POST['training_id'];
        $batch_name = trim($_POST['batch_name'] ?? '');
        
        // Get training dates to set as default batch dates
        $stmt = $pdo->prepare("SELECT date_start, date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$training_id]);
        $training = $stmt->fetch();
        
        $batch_data = json_encode(['attendees' => []]);
        
        $stmt = $pdo->prepare("INSERT INTO pm_training_batches (
            pm_training_request_id, batch_name, 
            batch_start_date, batch_end_date, 
            batch_start_time, batch_end_time,
            batch_data, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $training_id,
            $batch_name,
            $training['date_start'],
            $training['date_end'],
            null,
            null,
            $batch_data
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Batch added successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Delete Batch from Training (from batches modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_batch_from_training'])) {
    header('Content-Type: application/json');
    try {
        $batch_id = (int)$_POST['batch_id'];
        
        // Delete attendance records first
        $stmt = $pdo->prepare("DELETE FROM pm_training_attendance WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        
        // Then delete the batch
        $stmt = $pdo->prepare("DELETE FROM pm_training_batches WHERE id = ?");
        $stmt->execute([$batch_id]);
        
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully!']);
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
        
        if (!$is_admin && $request['requester_id'] != $current_user_id) {
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

// Handle AJAX Get Batches for Training Request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_training_batches'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT id, batch_name, batch_start_date, batch_end_date, batch_start_time, batch_end_time, batch_data FROM pm_training_batches WHERE pm_training_request_id = ? ORDER BY id ASC");
        $stmt->execute([$id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$batches) {
            echo json_encode(['success' => true, 'batches' => []]);
            exit;
        }

        $result = [];
        foreach ($batches as $index => $batch) {
            $batch_data = json_decode($batch['batch_data'], true);
            $attendees = $batch_data['attendees'] ?? [];

            // Get attendee details
            $attendee_details = [];
            if (!empty($attendees)) {
                $placeholders = implode(',', array_fill(0, count($attendees), '?'));
                $stmt = $pdo->prepare("SELECT id, CONCAT(fname, ' ', lname) as fullname, username FROM users WHERE id IN ($placeholders)");
                $stmt->execute($attendees);
                $attendee_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $result[] = [
                'id' => $batch['id'],
                'name' => $batch['batch_name'],
                'attendees' => $attendee_details,
                'attendee_count' => count($attendee_details),
                'start_date' => $batch['batch_start_date'],
                'end_date' => $batch['batch_end_date'],
                'start_time' => $batch['batch_start_time'],
                'end_time' => $batch['batch_end_time']
            ];
        }

        echo json_encode(['success' => true, 'batches' => $result]);
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
        $date_end = $_POST['date_end'] ?? '';
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : NULL;
        $late_filing_manual = isset($_POST['late_filing']) ? 1 : 0;
        $remarks_input = trim($_POST['remarks'] ?? '');
        
        // Collect batch data from JSON
        $batches = [];
        if (isset($_POST['batches']) && !empty($_POST['batches'])) {
            $batches = json_decode($_POST['batches'], true);
            
            if (is_array($batches)) {
                foreach ($batches as $index => $batch) {
                    if (empty($batch['start_date'])) {
                        throw new Exception("Batch " . ($index + 1) . " is missing start date");
                    }
                    if (empty($batch['end_date'])) {
                        throw new Exception("Batch " . ($index + 1) . " is missing end date");
                    }
                    if (empty($batch['attendees']) || !is_array($batch['attendees'])) {
                        throw new Exception("Batch " . ($index + 1) . " has no attendees selected");
                    }
                    
                    if (strtotime($batch['end_date']) < strtotime($batch['start_date'])) {
                        throw new Exception("Batch " . ($index + 1) . " has invalid date range");
                    }
                }
            }
        }
        
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
        
        if (empty($batches)) {
            $errors[] = "At least one batch is required";
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit;
        }
        
        $requester_id = $current_user_id;
        $created_at = date('Y-m-d H:i:s');
        
        // Calculate late filing based on created_at
        $auto_late_filing = calculateLateFiling($date_start, $created_at);
        $final_late_filing = $late_filing_manual ?: $auto_late_filing;
        
        // Get requester name
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname, username FROM users WHERE id = ?");
        $stmt->execute([$requester_id]);
        $user = $stmt->fetch();
        $requester_name = $user['fullname'] ?: ($user['username'] ?? 'Unknown');
        
        // Insert training request
        $stmt = $pdo->prepare("INSERT INTO pm_training_requests (
            title, venue, date_start, date_end, hospital_order_no,
            amount, late_filing, remarks, requester_id, committee_id, 
            status, ptr_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");
        
        $stmt->execute([
            $title,
            $venue,
            $date_start,
            $date_end,
            $hospital_order_no,
            $amount,
            $final_late_filing,
            $remarks_input,
            $requester_id,
            $committee_id
        ]);
        
        $new_id = $pdo->lastInsertId();
        
        // Insert batches
        if (!empty($batches)) {
            $stmt = $pdo->prepare("INSERT INTO pm_training_batches (
                pm_training_request_id, batch_name, 
                batch_start_date, batch_end_date, 
                batch_start_time, batch_end_time,
                batch_data, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $attStmt = $pdo->prepare("INSERT INTO pm_training_attendance 
                (pm_training_request_id, user_id, batch_id, attended) 
                VALUES (?, ?, ?, 0)");
            
            foreach ($batches as $index => $batch) {
                $batch_name = "Batch " . ($index + 1);
                $batch_data = json_encode([
                    'attendees' => array_map('intval', $batch['attendees'])
                ]);
                
                $stmt->execute([
                    $new_id,
                    $batch_name,
                    $batch['start_date'],
                    $batch['end_date'],
                    $batch['start_time'] ?: null,
                    $batch['end_time'] ?: null,
                    $batch_data
                ]);
                
                $batch_id = $pdo->lastInsertId();
                
                if (!empty($batch['attendees'])) {
                    foreach ($batch['attendees'] as $userId) {
                        $attStmt->execute([$new_id, (int)$userId, $batch_id]);
                    }
                }
            }
        }
        
        // Clear venues cache
        unset($_SESSION['pm_training_venues']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Training request submitted successfully with ' . count($batches) . ' batch(es)!',
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
                'late_filing' => $final_late_filing,
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pm_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("SELECT ptr_status, status, date_end, date_start FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $current_data = $stmt->fetch();
        
        if (!$current_data) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if ($current_data['ptr_status'] === 'complete') {
            echo json_encode(['success' => false, 'message' => 'Completed requests cannot be edited.']);
            exit;
        }
        
        // Check if admin action is allowed
        if (isset($_POST['admin_action']) && !empty($_POST['admin_action']) && $is_admin) {
            if ($current_data['status'] !== 'pending' && $current_data['status'] !== 'disapproved') {
                echo json_encode(['success' => false, 'message' => 'This request has already been reviewed.']);
                exit;
            }
        }
        
        $title = trim($_POST['title'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : NULL;
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks_input = trim($_POST['remarks'] ?? '');
        
        // Handle file uploads (only if training has ended and status is approved/conditional)
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
            if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $upload_dir . $filename)) {
                return $filename;
            }
            return null;
        }
        
        $current_date = new DateTime();
        $end_date = new DateTime($current_data['date_end']);
        $has_training_ended = $current_date >= $end_date;
        
        $ptr_file = null;
        $attendance_file = null;
        
        if ($has_training_ended && ($current_data['status'] === 'approved' || $current_data['status'] === 'conditional')) {
            $ptr_file = uploadPmTrainingFile('ptr_file');
            $attendance_file = uploadPmTrainingFile('attendance_file');
        }
        
        // Build update query
        $sql = "UPDATE pm_training_requests SET 
            title = ?, venue = ?, 
            hospital_order_no = ?, amount = ?, 
            late_filing = ?, remarks = ?, committee_id = ?";
        $params = [$title, $venue, $hospital_order_no, $amount, $late_filing, $remarks_input, $committee_id];
        
        if ($ptr_file) {
            $sql .= ", ptr_file = ?";
            $params[] = $ptr_file;
            $sql .= ", ptr_status = 'submitted'";
        }
        
        if ($attendance_file) {
            $sql .= ", attendance_file = ?";
            $params[] = $attendance_file;
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
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Training request updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Mark as Complete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_pm_request_ajax'])) {
    header('Content-Type: application/json');
    try {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Only admins can mark requests as complete']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        
        $stmt = $pdo->prepare("SELECT ptr_status, ptr_file, date_end FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        if ($request['ptr_status'] !== 'submitted') {
            echo json_encode(['success' => false, 'message' => 'PTR status must be "submitted" first.']);
            exit;
        }
        
        $current_date = new DateTime();
        $end_date = new DateTime($request['date_end']);
        
        if ($current_date < $end_date) {
            echo json_encode(['success' => false, 'message' => 'Training end date has not yet passed.']);
            exit;
        }
        
        if (empty($request['ptr_file'])) {
            echo json_encode(['success' => false, 'message' => 'PTR file is required.']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET ptr_status = 'complete', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Training marked as complete!']);
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
        
        $stmt = $pdo->prepare("SELECT ptr_status FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        
        if ($current['ptr_status'] === 'complete') {
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
        
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET 
            date_start = ?, date_end = ?, remarks = CONCAT(COALESCE(remarks, ''), '\n[Rescheduled: ', ?, ']'), 
            status = 'pending', ptr_status = 'pending', ptr_file = NULL, attendance_file = NULL
            WHERE id = ?");
        $stmt->execute([$date_start, $date_end, $resched_reason, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Training request rescheduled successfully!']);
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
        $stmt = $pdo->prepare("SELECT * FROM pm_training_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        // Get requester name
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname FROM users WHERE id = ?");
        $stmt->execute([$request['requester_id']]);
        $user = $stmt->fetch();
        $request['requester_name'] = $user['fullname'] ?? 'Unknown';
        
        echo json_encode(['success' => true, 'request' => $request]);
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
            SELECT ptr.*, c.name as committee_name
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
        
        $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as fullname FROM users WHERE id = ?");
        $stmt->execute([$request['requester_id']]);
        $user = $stmt->fetch();
        $request['requester_name'] = $user['fullname'] ?? 'Unknown';
        
        echo json_encode(['success' => true, 'request' => $request]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Report Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_report_data']) && $is_admin) {
    header('Content-Type: application/json');
    try {
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        
        $where_clauses = ["ptr.ptr_status = 'complete'"];
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
                CONCAT(u.fname, ' ', u.lname) as requester_name,
                u.username,
                ptr.hospital_order_no,
                ptr.amount,
                ptr.ptr_status
            FROM pm_training_requests ptr
            LEFT JOIN users u ON ptr.requester_id = u.id
            $where_sql
            ORDER BY ptr.date_start DESC
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

// Handle AJAX Get Report Filter Options
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_pm_filter_options']) && $is_admin) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT DISTINCT YEAR(date_start) as year FROM pm_training_requests WHERE ptr_status = 'complete' ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'years' => $years]);
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

// Get all committees for dropdown
$all_committees = [];
$stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
$all_committees = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

if (!$is_admin) {
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

// Build count parameters
$count_params = [];
if (!$is_admin) {
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

// Get total count
$count_query = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id $count_where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$query = "SELECT
    ptr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name,
    c.name as committee_name,
    (SELECT COUNT(id) FROM pm_training_batches WHERE pm_training_request_id = ptr.id) as batch_count
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
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0">PM Training Request Management</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPmTrainingModal">
                <i class="fas fa-plus me-2"></i>New Training Request
            </button>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-row" id="filterForm">
                <div class="filter-group">
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

                <div class="filter-group">
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

                <div class="filter-group">
                    <label class="form-label">Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="conditional" <?= ($filter_status == 'conditional') ? 'selected' : '' ?>>Conditional</option>
                        <option value="disapproved" <?= ($filter_status == 'disapproved') ? 'selected' : '' ?>>Disapproved</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Committee</label>
                    <select name="filter_committee" class="form-select">
                        <option value="">All Committees</option>
                        <?php foreach ($all_committees as $comm): ?>
                            <option value="<?= $comm['id'] ?>" <?= (isset($filter_committee) && $filter_committee == $comm['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($comm['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by title, venue, order number, or program manager name..." 
                        value="<?= htmlspecialchars($search) ?>">
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
                <span class="stat-number" id="totalCount"><?= number_format($total_records) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Pending Approval:</span>
                <span class="stat-number"><?= number_format(count(array_filter($pm_requests, function($r) { return $r['status'] == 'pending'; }))) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Completed:</span>
                <span class="stat-number"><?= number_format(count(array_filter($pm_requests, function($r) { return $r['ptr_status'] == 'complete'; }))) ?></span>
            </div>
        </div>
        
        <!-- PM Training Requests Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-list"></i> PM Training Requests List</h4>
                    <?php if ($is_admin): ?>
                    <button class="btn btn-success" id="generatePmReportBtn" data-bs-toggle="modal" data-bs-target="#pmReportModal">
                        <i class="fas fa-chart-line me-2"></i>Generate Report
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Venue</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Batches</th>
                            <th>Program Manager</th>
                            <th>Committee</th>
                            <th>HO No.</th>
                            <th>Amount</th>
                            <th>Late Filing</th>
                            <th>Remarks</th>
                            <th>PTR Status</th>
                            <th>Status</th>
                            <th>Actions</th>
                         </thead>
                    <tbody>
                        <?php if (!empty($pm_requests)): ?>
                            <?php foreach ($pm_requests as $request): ?>
                                <?php 
                                $ptr_status = $request['ptr_status'] ?? 'pending';
                                $is_complete = $ptr_status === 'complete';
                                $end_date = new DateTime($request['date_end']);
                                $current_date = new DateTime();
                                $is_past_end_date = $current_date > $end_date;
                                $days_elapsed = $current_date->diff($end_date)->days;
                                
                                $row_class = '';
                                $warning_message = '';
                                
                                if ($ptr_status === 'pending' && $is_past_end_date) {
                                    if ($days_elapsed >= 32) {
                                        $row_class = 'danger-row';
                                        $warning_message = "EXPIRED: {$days_elapsed} days no attachment";
                                    } elseif ($days_elapsed >= 20) {
                                        $row_class = 'warning-row';
                                        $warning_message = "WARNING: {$days_elapsed} days no attachment";
                                    }
                                }
                                ?>
                                <tr class="<?= $row_class ?>" data-training-start="<?= $request['date_start'] ?>">
                                    <td><strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if ($warning_message): ?>
                                            <br><span class="badge <?= $row_class === 'danger-row' ? 'badge-danger' : 'badge-warning' ?>" title="<?= $warning_message ?>"><i class="fas fa-exclamation-circle me-1"></i><?= $warning_message ?></span>
                                        <?php endif; ?>
                                     </div>
                                    <td><?= htmlspecialchars($request['venue']) ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></div>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></div>
                                    <td>
                                        <?php 
                                        $batch_count = $request['batch_count'] ?? 0;
                                        if ($batch_count > 0): 
                                        ?>
                                            <button class="batch-main-btn" onclick="openBatchesModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['date_start']) ?>')">
                                                <i class="fas fa-layer-group me-1"></i> Batches (<?= $batch_count ?>)
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($request['requester_name']) ?></div>
                                    <td><?= !empty($request['committee_name']) ? htmlspecialchars($request['committee_name']) : '-' ?></div>
                                    <td><?= htmlspecialchars($request['hospital_order_no'] ?? '-') ?></div>
                                    <td>₱<?= number_format($request['amount'], 2) ?></div>
                                    <td><?php if ($request['late_filing'] == 1): ?>
                                            <span class="late-badge late-yes">Yes</span>
                                        <?php else: ?>
                                            <span class="late-badge late-no">No</span>
                                        <?php endif; ?>
                                     </div>
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                        <?= htmlspecialchars(strlen($request['remarks'] ?? '') > 30 ? substr($request['remarks'] ?? '', 0, 30) . '...' : $request['remarks'] ?? '-') ?>
                                     </div>
                                    <td>
                                        <?php
                                        $ptr_badge_class = 'ptr-' . $ptr_status;
                                        $ptr_icon = $ptr_status === 'pending' ? 'fa-hourglass-half' : ($ptr_status === 'submitted' ? 'fa-upload' : 'fa-check-circle');
                                        ?>
                                        <span class="badge <?= $ptr_badge_class ?>">
                                            <i class="fas <?= $ptr_icon ?> me-1"></i><?= ucfirst($ptr_status) ?>
                                        </span>
                                     </div>
                                    <td>
                                        <?php if ($request['status'] == 'conditional'): ?>
                                            <span class="status-badge status-conditional">Conditional</span>
                                        <?php elseif ($request['status'] == 'disapproved'): ?>
                                            <span class="status-badge status-disapproved">Disapproved</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span>
                                        <?php endif; ?>
                                     </div>
                                    <td class="action-buttons">
                                        <?php if ($is_complete): ?>
                                            <button class="btn-action btn-view" onclick="openViewPmModal(<?= $request['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-view-attachment" onclick="openPtrAttachmentModal(<?= $request['id'] ?>)" title="View PTR">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deletePmRequest(<?= $request['id'] ?>)" title="Delete Request">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-edit" onclick="openEditPmModal(<?= $request['id'] ?>)" title="Edit Request">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($is_admin): ?>
                                            <button class="btn-action btn-reschedule" onclick="openReschedulePmModal(<?= $request['id'] ?>)" title="Reschedule Request">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-view-attachment" onclick="openPtrAttachmentModal(<?= $request['id'] ?>)" title="View/Upload PTR">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deletePmRequest(<?= $request['id'] ?>)" title="Delete Request">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                     </div>
                                  </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyStateRow">
                                <td colspan="14" class="text-center py-5">
                                    <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                                    <p class="text-muted mb-0">No PM training requests found</p>
                                 </div>
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
                            <li class="page-item"><a class="page-link" href="<?= $base_url ?>page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Previous</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span></li>
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
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="<?= $base_url ?>page=<?= $i ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $base_url ?>page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="<?= $base_url ?>page=<?= $page + 1 ?>">Next <i class="fas fa-chevron-right"></i></a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Next <i class="fas fa-chevron-right"></i></span></li>
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPmTrainingLabel">
                    <i class="fas fa-plus-circle me-2"></i>New PM Training Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                            <input type="date" class="form-control" name="date_start" id="add_date_start" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" id="add_date_end" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No.</label>
                            <input type="text" class="form-control" name="hospital_order_no" placeholder="e.g., HO-2024-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Committee</label>
                            <select name="committee_id" class="form-select">
                                <option value="">-- Select Committee --</option>
                                <?php foreach ($all_committees as $comm): ?>
                                    <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="late_filing_add" name="late_filing" value="1">
                                <label class="form-check-label" for="late_filing_add">Late Filing (Manual Override)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Additional remarks..."></textarea>
                        </div>
                        
                        <!-- Batches Section -->
                        <div class="col-12">
                            <h5 class="mb-3">Training Batches</h5>
                            <div id="batchTabsContainer"></div>
                            <div id="batchPanelsContainer"></div>
                            <input type="hidden" name="batches" id="batchesData">
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
                    <input type="hidden" name="admin_action" id="adminAction" value="">
                    
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
                                <?php foreach ($all_committees as $comm): ?>
                                    <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" id="edit_pm_amount" step="0.01">
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_pm_late_filing" name="late_filing" value="1">
                                <label class="form-check-label" for="edit_pm_late_filing">Late Filing</label>
                            </div>
                        </div>

                        <?php if ($is_admin): ?>
                        <div class="col-12">
                            <div class="card bg-light p-3">
                                <h6 class="mb-3"><i class="fas fa-gavel me-2"></i>Administrative Actions</h6>
                                <div class="d-flex gap-3 flex-wrap" id="adminActionsButtons">
                                    <button type="button" class="btn btn-success" onclick="confirmApprove()">
                                        <i class="fas fa-check-circle me-1"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="confirmConditional()">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Conditional
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="confirmDisapprove()">
                                        <i class="fas fa-times-circle me-1"></i> Disapprove
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Complete Button -->
                        <div class="col-12" id="completeButtonContainer" style="display: none;">
                            <div class="card bg-success bg-opacity-10 border-success p-3">
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="fas fa-check-circle me-2"></i> Mark as Complete
                                </button>
                                <small class="d-block mt-2 text-muted">Training has ended and PTR has been uploaded.</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_pm_remarks" rows="2"></textarea>
                        </div>
                        
                        <!-- Attachments Section -->
                        <div class="col-12" id="attachmentsSection" style="display: none;">
                            <h6 class="mt-3 mb-3"><i class="fas fa-paperclip me-2"></i>Attachments</h6>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> PTR (Post Training Report) is required to mark this training as complete.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">PTR (Post Training Report) <span class="text-danger">*Required for completion</span></label>
                                    <input type="file" class="form-control" name="ptr_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.csv">
                                    <div id="current_ptr_file" class="current-file mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Attendance File (Optional)</label>
                                    <input type="file" class="form-control" name="attendance_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.csv">
                                    <div id="current_attendance_file" class="current-file mt-1"></div>
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

<!-- View PM Training Request Modal -->
<div class="modal fade" id="viewPmTrainingModal" tabindex="-1" aria-labelledby="viewPmTrainingLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewPmTrainingLabel"><i class="fas fa-eye me-2"></i>View PM Training Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewPmModalBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule PM Training Request Modal -->
<div class="modal fade" id="reschedulePmModal" tabindex="-1" aria-labelledby="reschedulePmLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="reschedulePmLabel"><i class="fas fa-calendar-alt me-2"></i>Reschedule Training</h5>
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
                        <button type="submit" class="btn btn-primary" id="reschedulePmBtn"><i class="fas fa-calendar-check me-1"></i> Submit Reschedule</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- PTR Attachment Modal -->
<div class="modal fade" id="ptrAttachmentModal" tabindex="-1" aria-labelledby="ptrAttachmentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="ptrAttachmentLabel"><i class="fas fa-file-upload me-2"></i>PTR (Post Training Report)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Current PTR File</label>
                    <div id="currentPtrDisplay" class="alert alert-info"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload New PTR File</label>
                    <input type="file" class="form-control" id="ptrFileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.csv">
                    <small class="text-muted d-block mt-2">Accepted formats: PDF, JPG, JPEG, PNG, DOC, DOCX, XLSX, CSV</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="savePtrAttachmentBtn"><i class="fas fa-save me-1"></i>Save Attachment</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Batches Modal (Single button that opens all batches) -->
<div class="modal fade" id="batchesModal" tabindex="-1" aria-labelledby="batchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchesModalLabel">
                    <i class="fas fa-layer-group me-2"></i>Training Batches
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="batchesModalBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading batches...</p>
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
<div class="modal fade" id="pmReportModal" tabindex="-1" aria-labelledby="pmReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="pmReportLabel"><i class="fas fa-chart-line me-2"></i>Completed Trainings Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i><strong>Note:</strong> This report only shows trainings with <strong>COMPLETED</strong> PTR status.</div>
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
                        <button id="exportPmReportBtn" class="btn btn-success"><i class="fas fa-download me-1"></i> Export CSV</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="pmReportTable">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Venue</th><th>From</th><th>To</th><th>Program Manager</th><th>Hospital Order No.</th><th>Amount</th><th>PTR Status</th></tr>
                        </thead>
                        <tbody id="pmReportTableBody">
                            <tr><td colspan="8" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p>Loading data...</p></div></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="toastContainer" class="toast-notification"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPmRequestId = null;
    let batches = [];
    let currentTrainingStart = '';
    let currentTrainingEnd = '';
    let batchCounter = 1;
    
    // Batch Modal Variables
    let currentBatchesData = [];
    let currentTrainingId = null;
    let currentTrainingStartDate = null;
    let canEditBatches = false;

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

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // ========== BATCH FUNCTIONS FOR ADD MODAL ==========
    
    // Get all selected user IDs from all batches except the current one
    function getSelectedUserIdsFromOtherBatches(currentBatchIndex) {
        let selectedIds = [];
        for (let i = 0; i < batches.length; i++) {
            if (i !== currentBatchIndex) {
                selectedIds = selectedIds.concat(batches[i].selectedAttendees);
            }
        }
        return selectedIds;
    }
    
    function initBatchTabs() {
        const container = document.getElementById('batchTabsContainer');
        const panelsContainer = document.getElementById('batchPanelsContainer');
        
        if (batches.length === 0) {
            addNewBatch();
        }
        
        renderBatchTabs();
        renderBatchPanels();
        
        batches.forEach((_, index) => {
            loadUsersForBatch(index);
        });
    }
    
    function renderBatchTabs() {
        const container = document.getElementById('batchTabsContainer');
        if (!container) return;
        
        let html = '<div class="batch-tabs">';
        batches.forEach((batch, index) => {
            html += `
                <div class="batch-tab ${index === 0 ? 'active' : ''}" data-batch-index="${index}" onclick="switchBatchTab(${index})">
                    Batch ${index + 1}
                    ${batches.length > 1 ? `<span class="batch-tab-remove" onclick="event.stopPropagation(); removeBatch(${index})">&times;</span>` : ''}
                </div>
            `;
        });
        if (batches.length < 10) {
            html += `<div class="batch-tab add-batch-tab" onclick="addNewBatch()">+ Add Batch</div>`;
        }
        html += '</div>';
        container.innerHTML = html;
    }
    
    function renderBatchPanels() {
        const container = document.getElementById('batchPanelsContainer');
        if (!container) return;
        
        let html = '';
        batches.forEach((batch, index) => {
            const attendeesHtml = (batch.attendees || []).map(att => {
                const isChecked = batch.selectedAttendees.includes(att.id);
                return `
                    <div class="batch-attendee-item">
                        <input type="checkbox" class="batch-attendee-checkbox" value="${att.id}" data-batch="${index}" ${isChecked ? 'checked' : ''} onchange="toggleBatchAttendee(${index}, ${att.id})">
                        <div class="attendee-info">
                            <div class="attendee-name">${escapeHtml(att.fullname)}</div>
                            <div class="attendee-username">${escapeHtml(att.username)}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            html += `
                <div class="batch-panel ${index === 0 ? 'active' : ''}" data-batch-panel="${index}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control batch-start-date" data-batch="${index}" value="${batch.start_date || ''}" min="${currentTrainingStart}" max="${currentTrainingEnd}" onchange="updateBatchStartDate(${index}, this.value)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control batch-end-date" data-batch="${index}" value="${batch.end_date || ''}" min="${currentTrainingStart}" max="${currentTrainingEnd}" onchange="updateBatchEndDate(${index}, this.value)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control batch-start-time" data-batch="${index}" value="${batch.start_time || ''}" onchange="updateBatchStartTime(${index}, this.value)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control batch-end-time" data-batch="${index}" value="${batch.end_time || ''}" onchange="updateBatchEndTime(${index}, this.value)">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Attendees <span class="text-danger">*</span></label>
                        <div class="search-box">
                            <input type="text" class="form-control batch-attendee-search" data-batch="${index}" placeholder="Search attendees..." onkeyup="searchBatchAttendees(${index}, this.value)">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearBatchSearch(${index})">Clear</button>
                        </div>
                        <div class="batch-attendee-list" id="batch-attendee-list-${index}">
                            ${attendeesHtml || '<div class="text-center py-3 text-muted">No attendees available</div>'}
                        </div>
                        <small class="text-muted">Selected: <span id="batch-selected-count-${index}">${batch.selectedAttendees.length}</span> attendees</small>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }
    
    function loadUsersForBatch(batchIndex, search = '') {
        // Get existing user IDs from other batches to exclude them
        const excludedUserIds = getSelectedUserIdsFromOtherBatches(batchIndex);
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', 0);
        if (search) url.searchParams.set('search', search);
        
        // Also need to pass excluded IDs to the backend
        if (excludedUserIds.length > 0) {
            url.searchParams.set('exclude_ids', JSON.stringify(excludedUserIds));
        }
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    // Filter out users that are already selected in other batches
                    const filteredUsers = data.users.filter(user => !excludedUserIds.includes(user.id));
                    batches[batchIndex].attendees = filteredUsers;
                    renderBatchPanels();
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function searchBatchAttendees(batchIndex, search) {
        if (search.length >= 2 || search.length === 0) {
            loadUsersForBatch(batchIndex, search);
        }
    }
    
    function clearBatchSearch(batchIndex) {
        const searchInput = document.querySelector(`.batch-attendee-search[data-batch="${batchIndex}"]`);
        if (searchInput) {
            searchInput.value = '';
            loadUsersForBatch(batchIndex, '');
        }
    }
    
    function switchBatchTab(index) {
        document.querySelectorAll('.batch-tab').forEach((tab, i) => {
            if (i === index) tab.classList.add('active');
            else tab.classList.remove('active');
        });
        document.querySelectorAll('.batch-panel').forEach((panel, i) => {
            if (i === index) panel.classList.add('active');
            else panel.classList.remove('active');
        });
    }
    
    function addNewBatch() {
        if (batches.length >= 10) {
            showToast('Maximum 10 batches allowed', 'warning');
            return;
        }
        
        batches.push({
            start_date: currentTrainingStart,
            end_date: currentTrainingEnd,
            start_time: '',
            end_time: '',
            attendees: [],
            selectedAttendees: []
        });
        
        renderBatchTabs();
        renderBatchPanels();
        switchBatchTab(batches.length - 1);
        loadUsersForBatch(batches.length - 1);
    }
    
    function removeBatch(index) {
        if (batches.length <= 1) {
            showToast('You must have at least one batch', 'warning');
            return;
        }
        batches.splice(index, 1);
        renderBatchTabs();
        renderBatchPanels();
        if (index > 0) switchBatchTab(index - 1);
        else switchBatchTab(0);
    }
    
    function updateBatchStartDate(index, date) {
        batches[index].start_date = date;
        if (batches[index].end_date && new Date(batches[index].end_date) < new Date(date)) {
            batches[index].end_date = date;
            renderBatchPanels();
        }
    }
    
    function updateBatchEndDate(index, date) {
        if (batches[index].start_date && new Date(date) < new Date(batches[index].start_date)) {
            showToast('End date cannot be earlier than start date', 'danger');
            return;
        }
        batches[index].end_date = date;
    }
    
    function updateBatchStartTime(index, time) {
        batches[index].start_time = time;
    }
    
    function updateBatchEndTime(index, time) {
        batches[index].end_time = time;
    }
    
    function toggleBatchAttendee(index, userId) {
        const idx = batches[index].selectedAttendees.indexOf(userId);
        if (idx === -1) {
            batches[index].selectedAttendees.push(userId);
        } else {
            batches[index].selectedAttendees.splice(idx, 1);
        }
        const countSpan = document.getElementById(`batch-selected-count-${index}`);
        if (countSpan) countSpan.innerText = batches[index].selectedAttendees.length;
        
        // Reload other batches to exclude this newly selected user
        for (let i = 0; i < batches.length; i++) {
            if (i !== index) {
                loadUsersForBatch(i);
            }
        }
    }
    
    // Set training dates for batch limits
    document.getElementById('add_date_start')?.addEventListener('change', function() {
        currentTrainingStart = this.value;
        document.querySelectorAll('.batch-start-date, .batch-end-date').forEach(el => {
            el.setAttribute('min', currentTrainingStart);
        });
        batches.forEach((batch, idx) => {
            if (!batch.start_date) batch.start_date = currentTrainingStart;
        });
        renderBatchPanels();
    });
    
    document.getElementById('add_date_end')?.addEventListener('change', function() {
        currentTrainingEnd = this.value;
        document.querySelectorAll('.batch-start-date, .batch-end-date').forEach(el => {
            el.setAttribute('max', currentTrainingEnd);
        });
        batches.forEach((batch, idx) => {
            if (!batch.end_date) batch.end_date = currentTrainingEnd;
        });
        renderBatchPanels();
    });
    
    // Add form submission
    document.getElementById('addPmTrainingForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const venue = document.querySelector('[name="venue"]').value;
        const newVenue = document.querySelector('[name="new_venue"]')?.value || '';
        const finalVenue = venue === 'new' ? newVenue : venue;
        
        if (!finalVenue) {
            showToast('Please select or enter a venue', 'danger');
            return;
        }
        
        if (batches.length === 0) {
            showToast('Please add at least one batch', 'danger');
            return;
        }
        
        for (let i = 0; i < batches.length; i++) {
            const batch = batches[i];
            if (!batch.start_date) {
                showToast(`Batch ${i + 1} is missing start date`, 'danger');
                return;
            }
            if (!batch.end_date) {
                showToast(`Batch ${i + 1} is missing end date`, 'danger');
                return;
            }
            if (batch.selectedAttendees.length === 0) {
                showToast(`Batch ${i + 1} has no attendees selected`, 'danger');
                return;
            }
            if (new Date(batch.end_date) < new Date(batch.start_date)) {
                showToast(`Batch ${i + 1} has invalid date range`, 'danger');
                return;
            }
        }
        
        const formData = new FormData(this);
        formData.set('venue', finalVenue);
        formData.append('add_pm_request_ajax', '1');
        
        const batchesToSubmit = batches.map(batch => ({
            start_date: batch.start_date,
            end_date: batch.end_date,
            start_time: batch.start_time,
            end_time: batch.end_time,
            attendees: batch.selectedAttendees
        }));
        
        formData.set('batches', JSON.stringify(batchesToSubmit));
        
        const btn = document.getElementById('addPmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
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
    
    // Open Add modal
    document.getElementById('addPmTrainingModal')?.addEventListener('show.bs.modal', function() {
        currentTrainingStart = document.getElementById('add_date_start')?.value || '';
        currentTrainingEnd = document.getElementById('add_date_end')?.value || '';
        
        batches = [{
            start_date: currentTrainingStart,
            end_date: currentTrainingEnd,
            start_time: '',
            end_time: '',
            attendees: [],
            selectedAttendees: []
        }];
        
        initBatchTabs();
    });
    
    // ========== BATCHES MODAL (SINGLE BUTTON) ==========
    
    function openBatchesModal(trainingId, trainingStartDate) {
        currentTrainingId = trainingId;
        currentTrainingStartDate = trainingStartDate;
        
        const currentDate = new Date();
        const trainingStart = new Date(trainingStartDate);
        canEditBatches = currentDate < trainingStart;
        
        const modal = new bootstrap.Modal(document.getElementById('batchesModal'));
        const modalBody = document.getElementById('batchesModalBody');
        modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading batches...</p></div>';
        modal.show();
        
        fetch(`${window.location.href}?get_training_batches=1&id=${trainingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.batches.length > 0) {
                    currentBatchesData = data.batches;
                    renderBatchesModal();
                    if (canEditBatches) {
                        loadAvailableUsersForBatches();
                    }
                } else {
                    modalBody.innerHTML = '<div class="alert alert-info">No batches found for this training.</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading batches</div>';
            });
    }
    
    function renderBatchesModal() {
        const modalBody = document.getElementById('batchesModalBody');
        
        let tabsHtml = '<div class="batch-modal-tabs">';
        let panelsHtml = '<div>';
        
        // Add Batch button in tabs (only if can edit)
        if (canEditBatches && currentBatchesData.length < 10) {
            tabsHtml += `<div class="batch-modal-tab add-batch-tab" onclick="addBatchToTraining()">+ Add Batch</div>`;
        }
        
        currentBatchesData.forEach((batch, index) => {
            const isActive = index === 0;
            
            const startDateFormatted = formatDate(batch.start_date);
            const endDateFormatted = formatDate(batch.end_date);
            const startWeekday = batch.start_date ? new Date(batch.start_date).toLocaleDateString('en-US', { weekday: 'short' }) : '';
            const endWeekday = batch.end_date ? new Date(batch.end_date).toLocaleDateString('en-US', { weekday: 'short' }) : '';
            
            tabsHtml += `
                <div class="batch-modal-tab ${isActive ? 'active' : ''}" data-batch-index="${index}" onclick="switchBatchModalTab(${index})">
                    Batch ${index + 1}
                    ${canEditBatches ? `<span class="batch-tab-remove" onclick="event.stopPropagation(); deleteBatchFromTraining(${batch.id}, ${index})">&times;</span>` : ''}
                </div>
            `;
            
            let attendeesHtml = '<div class="batch-modal-attendee-list">';
            if (batch.attendees && batch.attendees.length > 0) {
                batch.attendees.forEach(att => {
                    attendeesHtml += `
                        <div class="batch-modal-attendee-item">
                            <div class="attendee-info">
                                <div class="attendee-name">${escapeHtml(att.fullname)}</div>
                                <div class="attendee-username">${escapeHtml(att.username)}</div>
                            </div>
                        </div>
                    `;
                });
            } else {
                attendeesHtml += '<div class="text-center py-3 text-muted">No attendees assigned to this batch</div>';
            }
            attendeesHtml += '</div>';
            
            let editFormHtml = '';
            if (canEditBatches) {
                editFormHtml = `
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="mb-3">Edit Batch</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control edit-batch-start-date" data-batch-id="${batch.id}" value="${batch.start_date || ''}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control edit-batch-end-date" data-batch-id="${batch.id}" value="${batch.end_date || ''}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control edit-batch-start-time" data-batch-id="${batch.id}" value="${batch.start_time || ''}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control edit-batch-end-time" data-batch-id="${batch.id}" value="${batch.end_time || ''}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Add/Remove Attendees</label>
                                <div class="search-box mb-2">
                                    <input type="text" class="form-control batch-attendee-search" data-batch-id="${batch.id}" placeholder="Search attendees...">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="searchBatchAttendeesForEdit(${batch.id})">Search</button>
                                </div>
                                <div class="batch-available-attendees" id="batch-available-attendees-${batch.id}" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; background: white;">
                                    <div class="text-center py-3 text-muted">Loading available users...</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" onclick="saveBatchChanges(${batch.id})">Save Batch Changes</button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            panelsHtml += `
                <div class="batch-modal-panel ${isActive ? 'active' : ''}" data-batch-panel="${index}">
                    <div class="mb-3">
                        <h6>Schedule</h6>
                        <p><strong>Dates:</strong> ${startDateFormatted} ${startWeekday ? `<span class="weekday-badge">${startWeekday}</span>` : ''} - ${endDateFormatted} ${endWeekday ? `<span class="weekday-badge">${endWeekday}</span>` : ''}</p>
                        <p><strong>Times:</strong> ${batch.start_time || 'N/A'} - ${batch.end_time || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <h6>Attendees (${batch.attendee_count})</h6>
                        ${attendeesHtml}
                    </div>
                    ${editFormHtml}
                </div>
            `;
        });
        
        tabsHtml += '</div>';
        panelsHtml += '</div>';
        
        let editWarning = '';
        if (!canEditBatches) {
            editWarning = '<div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>Training has already started. Batches cannot be edited.</div>';
        } else {
            editWarning = '<div class="alert alert-success mb-3"><i class="fas fa-edit me-2"></i>Training has not started yet. You can edit batch details and attendees.</div>';
        }
        
        modalBody.innerHTML = editWarning + tabsHtml + panelsHtml;
    }
    
    function switchBatchModalTab(index) {
        document.querySelectorAll('.batch-modal-tab').forEach((tab, i) => {
            if (i === index) tab.classList.add('active');
            else tab.classList.remove('active');
        });
        document.querySelectorAll('.batch-modal-panel').forEach((panel, i) => {
            if (i === index) panel.classList.add('active');
            else panel.classList.remove('active');
        });
    }
    
    function addBatchToTraining() {
        if (currentBatchesData.length >= 10) {
            showToast('Maximum 10 batches allowed', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('add_batch_to_training', '1');
        formData.append('training_id', currentTrainingId);
        formData.append('batch_name', `Batch ${currentBatchesData.length + 1}`);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Batch added successfully!', 'success');
                    openBatchesModal(currentTrainingId, currentTrainingStartDate);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }
    
    function deleteBatchFromTraining(batchId, index) {
        if (!confirm(`Are you sure you want to delete Batch ${index + 1}? This action cannot be undone.`)) return;
        
        const formData = new FormData();
        formData.append('delete_batch_from_training', '1');
        formData.append('batch_id', batchId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Batch deleted successfully!', 'success');
                    openBatchesModal(currentTrainingId, currentTrainingStartDate);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }
    
    function loadAvailableUsersForBatches() {
        currentBatchesData.forEach(batch => {
            loadAvailableUsersForBatchEdit(batch.id);
        });
    }
    
    function loadAvailableUsersForBatchEdit(batchId) {
        const container = document.getElementById(`batch-available-attendees-${batchId}`);
        if (!container) return;
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', currentTrainingId);
        url.searchParams.set('current_batch_id', batchId);
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    let html = '';
                    const batch = currentBatchesData.find(b => b.id === batchId);
                    const existingAttendeeIds = batch ? batch.attendees.map(a => a.id) : [];
                    
                    data.users.forEach(user => {
                        const isSelected = existingAttendeeIds.includes(user.id);
                        html += `
                            <div class="batch-modal-attendee-item">
                                <input type="checkbox" class="batch-modal-attendee-checkbox" value="${user.id}" data-user-id="${user.id}" ${isSelected ? 'checked' : ''}>
                                <div class="attendee-info">
                                    <div class="attendee-name">${escapeHtml(user.fullname)}</div>
                                    <div class="attendee-username">${escapeHtml(user.username)}</div>
                                </div>
                            </div>
                        `;
                    });
                    if (data.users.length === 0) {
                        html = '<div class="text-center py-3 text-muted">No users available to add</div>';
                    }
                    container.innerHTML = html;
                    
                    const searchInput = document.querySelector(`.batch-attendee-search[data-batch-id="${batchId}"]`);
                    if (searchInput) {
                        const existingSearchHandler = searchInput._searchHandler;
                        if (existingSearchHandler) {
                            searchInput.removeEventListener('keyup', existingSearchHandler);
                        }
                        const searchHandler = function() {
                            filterBatchAttendees(batchId, this.value);
                        };
                        searchInput._searchHandler = searchHandler;
                        searchInput.addEventListener('keyup', searchHandler);
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function filterBatchAttendees(batchId, searchTerm) {
        const container = document.getElementById(`batch-available-attendees-${batchId}`);
        if (!container) return;
        
        const items = container.querySelectorAll('.batch-modal-attendee-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (searchTerm === '' || text.includes(searchTerm.toLowerCase())) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    function searchBatchAttendeesForEdit(batchId) {
        const searchInput = document.querySelector(`.batch-attendee-search[data-batch-id="${batchId}"]`);
        if (searchInput) {
            filterBatchAttendees(batchId, searchInput.value);
        }
    }
    
    function saveBatchChanges(batchId) {
        const startDate = document.querySelector(`.edit-batch-start-date[data-batch-id="${batchId}"]`)?.value || '';
        const endDate = document.querySelector(`.edit-batch-end-date[data-batch-id="${batchId}"]`)?.value || '';
        const startTime = document.querySelector(`.edit-batch-start-time[data-batch-id="${batchId}"]`)?.value || null;
        const endTime = document.querySelector(`.edit-batch-end-time[data-batch-id="${batchId}"]`)?.value || null;
        
        const container = document.getElementById(`batch-available-attendees-${batchId}`);
        const selectedAttendees = Array.from(container.querySelectorAll('.batch-modal-attendee-checkbox:checked')).map(cb => cb.value);
        
        if (!startDate || !endDate) {
            showToast('Start date and end date are required', 'danger');
            return;
        }
        if (new Date(endDate) < new Date(startDate)) {
            showToast('End date cannot be earlier than start date', 'danger');
            return;
        }
        
        const formData = new FormData();
        formData.append('update_batch_ajax', '1');
        formData.append('batch_id', batchId);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        formData.append('start_time', startTime);
        formData.append('end_time', endTime);
        formData.append('attendees', JSON.stringify(selectedAttendees));
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Batch updated successfully!', 'success');
                    openBatchesModal(currentTrainingId, currentTrainingStartDate);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }
    
    // ========== EDIT MODAL FUNCTIONS ==========
    
    function openEditPmModal(id) {
        fetch(`${window.location.href}?get_pm_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    const isComplete = request.ptr_status === 'complete';
                    if (isComplete) {
                        showToast('Completed requests cannot be edited.', 'warning');
                        return;
                    }
                    
                    document.getElementById('edit_pm_id').value = request.id;
                    document.getElementById('edit_pm_title').value = request.title || '';
                    document.getElementById('edit_pm_venue').value = request.venue || '';
                    document.getElementById('edit_pm_date_start').value = request.date_start || '';
                    document.getElementById('edit_pm_date_end').value = request.date_end || '';
                    document.getElementById('edit_pm_hospital_order_no').value = request.hospital_order_no || '';
                    document.getElementById('edit_pm_amount').value = request.amount || 0;
                    document.getElementById('edit_pm_late_filing').checked = request.late_filing == 1;
                    document.getElementById('edit_pm_remarks').value = request.remarks || '';
                    
                    if (document.getElementById('edit_pm_committee')) {
                        document.getElementById('edit_pm_committee').value = request.committee_id || '';
                    }
                    
                    document.getElementById('adminAction').value = '';
                    document.getElementById('updateRequestBtn').innerHTML = '<i class="fas fa-save me-1"></i> Update Request';
                    
                    const ptrHtml = request.ptr_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${request.ptr_file}" target="_blank">📄 View Current PTR File</a>` : '<span class="text-muted">No PTR file uploaded</span>';
                    const attendanceHtml = request.attendance_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${request.attendance_file}" target="_blank">📄 View Current Attendance File</a>` : '<span class="text-muted">No attendance file uploaded</span>';
                    document.getElementById('current_ptr_file').innerHTML = ptrHtml;
                    document.getElementById('current_attendance_file').innerHTML = attendanceHtml;
                    
                    const adminActionsContainer = document.getElementById('adminActionsButtons')?.parentElement?.parentElement;
                    if (adminActionsContainer) {
                        adminActionsContainer.style.display = request.status === 'pending' ? 'block' : 'none';
                    }
                    
                    const currentDate = new Date();
                    const endDate = new Date(request.date_end);
                    const isPastEndDate = currentDate > endDate;
                    const canShowAttachments = isPastEndDate && (request.status === 'approved' || request.status === 'conditional');
                    const attachmentsSection = document.getElementById('attachmentsSection');
                    if (attachmentsSection) {
                        attachmentsSection.style.display = canShowAttachments ? 'block' : 'none';
                    }
                    
                    const completeContainer = document.getElementById('completeButtonContainer');
                    if (completeContainer && request.ptr_status === 'submitted' && isPastEndDate) {
                        completeContainer.style.display = 'block';
                        const completeBtn = document.getElementById('markCompleteBtn');
                        if (completeBtn) {
                            const newCompleteBtn = completeBtn.cloneNode(true);
                            completeBtn.parentNode.replaceChild(newCompleteBtn, completeBtn);
                            newCompleteBtn.onclick = () => markAsComplete(request.id);
                        }
                    } else if (completeContainer) {
                        completeContainer.style.display = 'none';
                    }
                    
                    new bootstrap.Modal(document.getElementById('editPmTrainingModal')).show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }
    
    function markAsComplete(id) {
        if (!confirm('Mark this training as complete? This will make the request uneditable.')) return;
        
        const formData = new FormData();
        formData.append('complete_pm_request_ajax', '1');
        formData.append('id', id);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editPmTrainingModal'))?.hide();
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
    
    // ========== ADMIN ACTION FUNCTIONS (NO REMARKS) ==========
    
    function confirmApprove() {
        const adminAction = 'approve';
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) {
            adminButtons.style.display = 'none';
        }
        submitAdminAction(adminAction, '');
    }
    
    function confirmConditional() {
        const adminAction = 'conditional';
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) {
            adminButtons.style.display = 'none';
        }
        submitAdminAction(adminAction, '');
    }
    
    function confirmDisapprove() {
        const adminAction = 'disapprove';
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) {
            adminButtons.style.display = 'none';
        }
        submitAdminAction(adminAction, '');
    }
    
    function submitAdminAction(action, remark) {
        const formData = new FormData(document.getElementById('editPmTrainingForm'));
        formData.append('edit_pm_request_ajax', '1');
        formData.append('admin_action', action);
        formData.append('action_remark', remark);
        
        const submitBtn = document.getElementById('updateRequestBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
        
        fetch(window.location.href, { 
            method: 'POST', 
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', text.substring(0, 200));
                throw new Error('Server returned invalid response');
            }
        })
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editPmTrainingModal'))?.hide();
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
                const adminButtons = document.getElementById('adminActionsButtons');
                if (adminButtons) {
                    adminButtons.style.display = 'flex';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
            const adminButtons = document.getElementById('adminActionsButtons');
            if (adminButtons) {
                adminButtons.style.display = 'flex';
            }
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // ========== VIEW MODAL FUNCTIONS ==========
    
    function openViewPmModal(id) {
        const modal = new bootstrap.Modal(document.getElementById('viewPmTrainingModal'));
        const modalBody = document.getElementById('viewPmModalBody');
        modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading details...</p></div>';
        modal.show();
        
        fetch(`${window.location.href}?get_pm_request_view=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const r = data.request;
                    const hasPtr = !!r.ptr_file;
                    const hasAttendance = !!r.attendance_file;
                    
                    let attachmentsHtml = '';
                    if (hasPtr || hasAttendance) {
                        attachmentsHtml = '<div class="view-details-card"><h6><i class="fas fa-paperclip me-2"></i>Attachments</h6><div class="attachment-list-view">';
                        if (hasPtr) {
                            attachmentsHtml += `<div class="attachment-item-view"><i class="fas fa-file-alt"></i><div class="file-info"><p class="file-name">PTR (Post Training Report)</p><p class="file-size">${r.ptr_file}</p></div><a href="<?= BASE_URL ?>/uploads/pm_training/${r.ptr_file}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download"></i> Download</a></div>`;
                        }
                        if (hasAttendance) {
                            attachmentsHtml += `<div class="attachment-item-view"><i class="fas fa-users"></i><div class="file-info"><p class="file-name">Attendance File</p><p class="file-size">${r.attendance_file}</p></div><a href="<?= BASE_URL ?>/uploads/pm_training/${r.attendance_file}" class="btn btn-sm btn-primary" target="_blank" download><i class="fas fa-download"></i> Download</a></div>`;
                        }
                        attachmentsHtml += '</div></div>';
                    }
                    
                    fetch(`${window.location.href}?get_training_batches=1&id=${id}`)
                        .then(res => res.json())
                        .then(batchData => {
                            let batchesHtml = '<div class="view-details-card"><h6><i class="fas fa-layer-group me-2"></i>Batches</h6>';
                            if (batchData.success && batchData.batches.length > 0) {
                                batchData.batches.forEach((batch, idx) => {
                                    batchesHtml += `<div class="mb-3"><strong>Batch ${idx + 1}</strong><br>
                                    <small>Dates: ${formatDate(batch.start_date)} - ${formatDate(batch.end_date)}</small><br>
                                    <small>Times: ${batch.start_time || 'N/A'} - ${batch.end_time || 'N/A'}</small><br>
                                    <strong>Attendees (${batch.attendee_count}):</strong><ul>`;
                                    batch.attendees.forEach(att => {
                                        batchesHtml += `<li>${escapeHtml(att.fullname)} (${escapeHtml(att.username)})</li>`;
                                    });
                                    batchesHtml += `</ul></div>`;
                                });
                            } else {
                                batchesHtml += '<p class="text-muted">No batches found</p>';
                            }
                            batchesHtml += '</div>';
                            
                            modalBody.innerHTML = `
                                <div class="row">
                                    <div class="col-md-6"><div class="view-details-card"><h6>Training Information</h6><p><strong>Title:</strong> ${escapeHtml(r.title)}</p><p><strong>Venue:</strong> ${escapeHtml(r.venue)}</p><p><strong>Committee:</strong> ${escapeHtml(r.committee_name || '-')}</p><p><strong>Hospital Order No.:</strong> ${escapeHtml(r.hospital_order_no || '-')}</p></div></div>
                                    <div class="col-md-6"><div class="view-details-card"><h6>Schedule</h6><p><strong>Date Start:</strong> ${formatDate(r.date_start)}</p><p><strong>Date End:</strong> ${formatDate(r.date_end)}</p><p><strong>Amount:</strong> ₱${parseFloat(r.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p><p><strong>Late Filing:</strong> ${r.late_filing ? 'Yes' : 'No'}</p></div></div>
                                    <div class="col-md-6"><div class="view-details-card"><h6>Requester</h6><p><strong>Name:</strong> ${escapeHtml(r.requester_name)}</p><p><strong>Status:</strong> <span class="status-badge status-${r.status}">${ucfirst(r.status)}</span></p><p><strong>PTR Status:</strong> <span class="badge ptr-${r.ptr_status}">${ucfirst(r.ptr_status)}</span></p></div></div>
                                    <div class="col-md-6"><div class="view-details-card"><h6>Remarks</h6><p>${escapeHtml(r.remarks) || '<em>No remarks</em>'}</p></div></div>
                                    <div class="col-12">${batchesHtml}</div>
                                    <div class="col-12">${attachmentsHtml}</div>
                                </div>
                            `;
                        });
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Error loading details'}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
            });
    }
    
    // ========== RESCHEDULE MODAL FUNCTIONS ==========
    
    function openReschedulePmModal(id) {
        fetch(`${window.location.href}?get_pm_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    document.getElementById('reschedule_pm_id').value = request.id;
                    document.getElementById('reschedule_pm_date_start').value = request.date_start;
                    document.getElementById('reschedule_pm_date_end').value = request.date_end;
                    document.getElementById('reschedule_pm_reason').value = '';
                    new bootstrap.Modal(document.getElementById('reschedulePmModal')).show();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }
    
    document.getElementById('reschedulePmForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('reschedule_pm_request_ajax', '1');
        const submitBtn = document.getElementById('reschedulePmBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('reschedulePmModal'))?.hide();
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
    
    // ========== DELETE REQUEST ==========
    
    function deletePmRequest(id) {
        if (!confirm('Are you sure you want to delete this training request?')) return;
        fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `delete_pm_request=1&id=${id}` })
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
    
    // ========== PTR ATTACHMENT MODAL ==========
    
    let currentPtrRequestId = null;
    
    function openPtrAttachmentModal(id) {
        currentPtrRequestId = id;
        fetch(`${window.location.href}?get_pm_request=1&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const request = data.request;
                    const displayDiv = document.getElementById('currentPtrDisplay');
                    if (request.ptr_file) {
                        displayDiv.innerHTML = `<a href="<?= BASE_URL ?>/uploads/pm_training/${request.ptr_file}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download me-1"></i> Download Current PTR</a>`;
                    } else {
                        displayDiv.innerHTML = '<span class="text-muted">No PTR file uploaded yet</span>';
                    }
                    document.getElementById('ptrFileInput').value = '';
                    new bootstrap.Modal(document.getElementById('ptrAttachmentModal')).show();
                }
            });
    }
    
    document.getElementById('savePtrAttachmentBtn')?.addEventListener('click', function() {
        if (!currentPtrRequestId) return;
        const fileInput = document.getElementById('ptrFileInput');
        if (!fileInput.files.length) {
            showToast('Please select a file to upload', 'warning');
            return;
        }
        const formData = new FormData();
        formData.append('edit_pm_request_ajax', '1');
        formData.append('id', currentPtrRequestId);
        formData.append('ptr_file', fileInput.files[0]);
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('ptrAttachmentModal'))?.hide();
                    showToast('PTR uploaded successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Upload failed', 'danger');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Attachment';
            });
    });
    
    // ========== FILTER FUNCTIONS ==========
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // ========== REPORT MODAL FUNCTIONS ==========
    
    <?php if ($is_admin): ?>
    function loadPmFilterOptions() {
        fetch(`${window.location.href}?get_pm_filter_options=1`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const yearSelect = document.getElementById('reportYear');
                    yearSelect.innerHTML = '<option value="">All Years</option>';
                    data.years.forEach(year => {
                        yearSelect.innerHTML += `<option value="${year}">${year}</option>`;
                    });
                }
            });
    }
    
    function loadPmReportData() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
        let url = `${window.location.href}?get_pm_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        fetch(url).then(response => response.json()).then(data => {
            const tbody = document.getElementById('pmReportTableBody');
            if (data.success && data.reports.length > 0) {
                tbody.innerHTML = '';
                data.reports.forEach(report => {
                    tbody.innerHTML += `<tr>
                        <td><strong>${escapeHtml(report.title)}</strong></div>
                        <td>${escapeHtml(report.venue)}</div>
                        <td>${escapeHtml(report.date_start)}</div>
                        <td>${escapeHtml(report.date_end)}</div>
                        <td>${escapeHtml(report.requester_name)}</div>
                        <td>${escapeHtml(report.hospital_order_no || '-')}</div>
                        <td class="amount-cell">₱${parseFloat(report.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                        <td><span class="badge ptr-${report.ptr_status}">${ucfirst(report.ptr_status)}</span></div>
                    </tr>`;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-2"></i><p>No completed trainings found</p></div><tr>`;
            }
        });
    }
    
    function exportPmReportToCSV() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
        let url = `${window.location.href}?get_pm_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        fetch(url).then(response => response.json()).then(data => {
            if (data.success && data.reports.length > 0) {
                let csvContent = "Title,Venue,From,To,Program Manager,Hospital Order No.,Amount,PTR Status\n";
                data.reports.forEach(report => {
                    csvContent += `"${escapeCsv(report.title)}","${escapeCsv(report.venue)}","${report.date_start}","${report.date_end}","${escapeCsv(report.requester_name)}","${escapeCsv(report.hospital_order_no || '-')}","${report.amount}","${report.ptr_status}"\n`;
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
        setTimeout(loadPmReportData, 100);
    });
    <?php endif; ?>
    
    // ========== VENUE HANDLING ==========
    
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
</script>
</body>
</html>
<?php
ob_end_flush();
?>