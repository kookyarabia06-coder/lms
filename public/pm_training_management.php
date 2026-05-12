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

// Helper function to handle file uploads for PM Training
function uploadPmTrainingFile($field_name, $upload_dir) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            $error_codes = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
            ];
            $err_msg = $error_codes[$_FILES[$field_name]['error']] ?? 'Unknown upload error';
            error_log("PM Training upload error for {$field_name}: {$err_msg} (code: {$_FILES[$field_name]['error']})");
        }
        return null;
    }
    
    // Validate file extension
    $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xlsx', 'csv'];
    if (!in_array($ext, $allowed)) {
        error_log("PM Training upload error for {$field_name}: Invalid file extension '{$ext}'");
        return null;
    }
    
    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("PM Training upload error: Failed to create directory '{$upload_dir}'");
            return null;
        }
        error_log("PM Training upload: Created directory '{$upload_dir}'");
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        error_log("PM Training upload error: Directory '{$upload_dir}' is not writable");
        return null;
    }
    
    // Generate unique filename
    $filename = 'pm_training_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $destination)) {
        error_log("PM Training upload success: {$field_name} saved as '{$filename}'");
        return $filename;
    }
    
    error_log("PM Training upload error: move_uploaded_file() failed for '{$field_name}' to '{$destination}'");
    return null;
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
        
        // ALSO handle exclude_ids passed from JavaScript for unsaved batch selections
        if (isset($_GET['exclude_ids']) && !empty($_GET['exclude_ids'])) {
            $decoded = json_decode($_GET['exclude_ids'], true);
            if (is_array($decoded)) {
                $decoded = array_map('intval', $decoded);
                $existing_user_ids = array_unique(array_merge($existing_user_ids, $decoded));
            }
        }
        
        // Original query - removed the status filter that might not exist
        $query = "SELECT id, username, CONCAT(fname, ' ', lname) as fullname 
                  FROM users 
                  WHERE role NOT IN ('admin', 'superadmin', 'proponent')";
        
        $params = [];
        
        if (!empty($existing_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($existing_user_ids), '?'));
            $query .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, array_values($existing_user_ids));
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
        error_log("get_users_for_attendance error: " . $e->getMessage());
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
        
        // ALSO update batch_data JSON in pm_training_batches
        $batch_data = json_encode(['attendees' => array_map('intval', $attendees)]);
        $stmt = $pdo->prepare("UPDATE pm_training_batches SET batch_data = ? WHERE id = ?");
        $stmt->execute([$batch_data, $batch_id]);
        
        echo json_encode(['success' => true, 'message' => 'Batch updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Update Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance_ajax'])) {
    header('Content-Type: application/json');
    try {
        $batch_id = (int)$_POST['batch_id'];
        $user_id = (int)$_POST['user_id'];
        $attended = (int)$_POST['attended'];
        
        $stmt = $pdo->prepare("UPDATE pm_training_attendance SET attended = ?, updated_at = NOW() WHERE batch_id = ? AND user_id = ?");
        $stmt->execute([$attended, $batch_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Attendance updated']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}


// Handle AJAX Get Attendance Status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_attendance_status'])) {
    header('Content-Type: application/json');
    try {
        $batch_ids = array_map('intval', explode(',', $_GET['batch_ids'] ?? ''));
        if (empty($batch_ids)) {
            echo json_encode(['success' => true, 'attendance' => []]);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($batch_ids), '?'));
        $stmt = $pdo->prepare("SELECT batch_id, user_id, attended FROM pm_training_attendance WHERE batch_id IN ($placeholders)");
        $stmt->execute($batch_ids);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'attendance' => $attendance]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX Get Attendance Report
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_attendance_report'])) {
    header('Content-Type: application/json');
    try {
        $batch_id = (int)$_GET['batch_id'];
        
        // Get batch info
        $stmt = $pdo->prepare("SELECT b.*, ptr.title as training_title FROM pm_training_batches b LEFT JOIN pm_training_requests ptr ON b.pm_training_request_id = ptr.id WHERE b.id = ?");
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch();
        
        if (!$batch) {
            echo json_encode(['success' => false, 'message' => 'Batch not found']);
            exit;
        }
        
        // Get attendees with attendance status
        $batch_data = json_decode($batch['batch_data'], true);
        $attendees = $batch_data['attendees'] ?? [];
        
        $attendee_details = [];
        if (!empty($attendees)) {
            $placeholders = implode(',', array_fill(0, count($attendees), '?'));
            $stmt = $pdo->prepare("
                SELECT u.id, CONCAT(u.fname, ' ', u.lname) as fullname, u.username,
                       COALESCE(a.attended, 0) as attended
                FROM users u
                LEFT JOIN pm_training_attendance a ON u.id = a.user_id AND a.batch_id = ?
                WHERE u.id IN ($placeholders)
            ");
            $params = array_merge([$batch_id], $attendees);
            $stmt->execute($params);
            $attendee_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'batch_name' => $batch['batch_name'],
            'training_title' => $batch['training_title'],
            'attendees' => $attendee_details
        ]);
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

            // Get attendee details AND attendance status
            $attendee_details = [];
            if (!empty($attendees)) {
                $placeholders = implode(',', array_fill(0, count($attendees), '?'));
                $stmt = $pdo->prepare("
                    SELECT u.id, CONCAT(u.fname, ' ', u.lname) as fullname, u.username,
                           COALESCE(a.attended, 0) as attended
                    FROM users u
                    LEFT JOIN pm_training_attendance a ON u.id = a.user_id AND a.batch_id = ?
                    WHERE u.id IN ($placeholders)
                ");
                $params = array_merge([$batch['id']], $attendees);
                $stmt->execute($params);
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
            $allowed_actions = ['approve', 'conditional', 'disapprove'];
            $revert_actions = ['revert'];
            
            if (in_array($_POST['admin_action'], $allowed_actions)) {
                if ($current_data['status'] !== 'pending' && $current_data['status'] !== 'disapproved') {
                    echo json_encode(['success' => false, 'message' => 'This request has already been reviewed.']);
                    exit;
                }
            }
        }
        
        $title = trim($_POST['title'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $hospital_order_no = trim($_POST['hospital_order_no'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : NULL;
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $remarks_input = trim($_POST['remarks'] ?? '');
        
        // Define upload directory for PM training files
        $upload_dir = __DIR__ . '/../uploads/pm_training/';
        
        $current_date = new DateTime();
        $end_date = new DateTime($current_data['date_end']);
        $has_training_ended = $current_date >= $end_date;
        
        $ptr_file = null;
        $attendance_file = null;
        
        if ($has_training_ended && ($current_data['status'] === 'approved' || $current_data['status'] === 'conditional')) {
            $ptr_file = uploadPmTrainingFile('ptr_file', $upload_dir);
            $attendance_file = uploadPmTrainingFile('attendance_file', $upload_dir);
            
            if ($ptr_file !== null) {
                error_log("PM Training: PTR file uploaded for request ID {$id}: {$ptr_file}");
            }
            if ($attendance_file !== null) {
                error_log("PM Training: Attendance file uploaded for request ID {$id}: {$attendance_file}");
            }
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
                case 'revert':
                    $new_status = 'pending';
                    $status_prefix = 'Reverted to Pending';
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
        
        // Recalculate late filing based on new dates
        $created_at = date('Y-m-d H:i:s');
        $auto_late_filing = calculateLateFiling($date_start, $created_at);
        
        // Recalculate late filing based on new dates
        $created_at = date('Y-m-d H:i:s');
        $auto_late_filing = calculateLateFiling($date_start, $created_at);
        
        $stmt = $pdo->prepare("UPDATE pm_training_requests SET 
            date_start = ?, date_end = ?, 
            late_filing = ?,
            remarks = CONCAT(COALESCE(remarks, ''), '\n[Rescheduled: ', ?, ']'), 
            ptr_status = 'pending', 
            ptr_file = NULL, attendance_file = NULL, updated_at = NOW()
            WHERE id = ?");
        $result = $stmt->execute([$date_start, $date_end, $auto_late_filing, $resched_reason, $id]);
        
        if ($result) {
            error_log("PM Training: Request ID {$id} rescheduled successfully");
            echo json_encode(['success' => true, 'message' => 'Training request rescheduled successfully!']);
        } else {
            error_log("PM Training: Failed to reschedule request ID {$id}");
            echo json_encode(['success' => false, 'message' => 'Failed to reschedule request.']);
        }
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
        $status = $_GET['status'] ?? '';
        $ptr_status = $_GET['ptr_status'] ?? '';
        $committee = isset($_GET['committee']) && !empty($_GET['committee']) ? (int)$_GET['committee'] : null;
        
        $where_clauses = [];
        $params = [];
        
        if ($year) { $where_clauses[] = "YEAR(ptr.date_start) = ?"; $params[] = $year; }
        if ($month) { $where_clauses[] = "MONTH(ptr.date_start) = ?"; $params[] = $month; }
        if ($status) { $where_clauses[] = "ptr.status = ?"; $params[] = $status; }
        if ($ptr_status) { $where_clauses[] = "ptr.ptr_status = ?"; $params[] = $ptr_status; }
        if ($committee) { $where_clauses[] = "ptr.committee_id = ?"; $params[] = $committee; }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $query = "
            SELECT ptr.id, ptr.title, ptr.venue,
                DATE_FORMAT(ptr.date_start, '%M %d, %Y') as date_start,
                DATE_FORMAT(ptr.date_end, '%M %d, %Y') as date_end,
                CONCAT(u.fname, ' ', u.lname) as requester_name, u.username,
                ptr.hospital_order_no, ptr.amount, ptr.status, ptr.ptr_status
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

// Get committees for dropdown
$all_committees = [];
if ($is_admin) {
    // Admin sees all committees
    $stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
    $all_committees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Proponent only sees committees they belong to
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM committees c
        INNER JOIN user_departments ud ON ud.committee_id = c.id
        WHERE ud.user_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$current_user_id]);
    $all_committees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get filter parameters
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : '';
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$filter_committee = isset($_GET['filter_committee']) && !empty($_GET['filter_committee']) ? (int)$_GET['filter_committee'] : '';
$filter_ptr_status = isset($_GET['filter_ptr_status']) ? $_GET['filter_ptr_status'] : '';
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

if (!empty($filter_ptr_status)) {
    $where_clause[] = "ptr.ptr_status = ?";
    $main_params[] = $filter_ptr_status;
    $count_where_clause[] = "ptr.ptr_status = ?";
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

if (!empty($filter_ptr_status)) {
    $count_params[] = $filter_ptr_status;
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
                    <label class="form-label"> Request Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">All Request Status</option>
                        <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($filter_status == 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="conditional" <?= ($filter_status == 'conditional') ? 'selected' : '' ?>>Conditional</option>
                        <option value="disapproved" <?= ($filter_status == 'disapproved') ? 'selected' : '' ?>>Disapproved</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">PTR Status</label>
                    <select name="filter_ptr_status" class="form-select">
                        <option value="">All PTR Status</option>
                        <option value="pending" <?= (isset($filter_ptr_status) && $filter_ptr_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="submitted" <?= (isset($filter_ptr_status) && $filter_ptr_status == 'submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="complete" <?= (isset($filter_ptr_status) && $filter_ptr_status == 'complete') ? 'selected' : '' ?>>Complete</option>
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
                <span class="stat-number">
                    <?php 
                    $pending_sql = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id " . 
                        (!empty($count_where_sql) ? $count_where_sql . " AND ptr.status = 'pending'" : "WHERE ptr.status = 'pending'");
                    $pending_stmt = $pdo->prepare($pending_sql);
                    $pending_stmt->execute($count_params);
                    echo number_format($pending_stmt->fetchColumn());
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Completed:</span>
                <span class="stat-number">
                    <?php 
                    $complete_sql = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id " . 
                        (!empty($count_where_sql) ? $count_where_sql . " AND ptr.ptr_status = 'complete'" : "WHERE ptr.ptr_status = 'complete'");
                    $complete_stmt = $pdo->prepare($complete_sql);
                    $complete_stmt->execute($count_params);
                    echo number_format($complete_stmt->fetchColumn());
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label text-warning">⚠ Warning:</span>
                <span class="stat-number text-warning">
                    <?php 
                    $warning_sql = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id " . 
                        (!empty($count_where_sql) ? $count_where_sql . " AND ptr.ptr_status = 'pending' AND ptr.date_end < NOW() AND DATEDIFF(NOW(), ptr.date_end) BETWEEN 20 AND 31" : "WHERE ptr.ptr_status = 'pending' AND ptr.date_end < NOW() AND DATEDIFF(NOW(), ptr.date_end) BETWEEN 20 AND 31");
                    $warning_stmt = $pdo->prepare($warning_sql);
                    $warning_stmt->execute($count_params);
                    echo number_format($warning_stmt->fetchColumn());
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label text-danger">❌ Expired:</span>
                <span class="stat-number text-danger">
                    <?php 
                    $expired_sql = "SELECT COUNT(DISTINCT ptr.id) FROM pm_training_requests ptr LEFT JOIN users u ON ptr.requester_id = u.id " . 
                        (!empty($count_where_sql) ? $count_where_sql . " AND ptr.ptr_status = 'pending' AND ptr.date_end < NOW() AND DATEDIFF(NOW(), ptr.date_end) >= 32" : "WHERE ptr.ptr_status = 'pending' AND ptr.date_end < NOW() AND DATEDIFF(NOW(), ptr.date_end) >= 32");
                    $expired_stmt = $pdo->prepare($expired_sql);
                    $expired_stmt->execute($count_params);
                    echo number_format($expired_stmt->fetchColumn());
                    ?>
                </span>
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
                            <th>Request Status</th>
                            <th>Actions</th>
                         </tr>
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
                                    <td>
                                        <strong><?= htmlspecialchars($request['title']) ?></strong>
                                        <?php if ($warning_message): ?>
                                            <br><span class="badge <?= $row_class === 'danger-row' ? 'badge-danger' : 'badge-warning' ?>" title="<?= $warning_message ?>"><i class="fas fa-exclamation-circle me-1"></i><?= $warning_message ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($request['venue']) ?></td>
                                    <td><?= date('M d, Y', strtotime($request['date_start'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($request['date_end'])) ?></td>
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
                                    <td class="truncated-cell" title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                                        <?= htmlspecialchars(strlen($request['remarks'] ?? '') > 30 ? substr($request['remarks'] ?? '', 0, 30) . '...' : $request['remarks'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <?php
                                        $ptr_badge_class = 'ptr-' . $ptr_status;
                                        $ptr_icon = $ptr_status === 'pending' ? 'fa-hourglass-half' : ($ptr_status === 'submitted' ? 'fa-upload' : 'fa-check-circle');
                                        ?>
                                        <span class="badge <?= $ptr_badge_class ?>">
                                            <i class="fas <?= $ptr_icon ?> me-1"></i><?= ucfirst($ptr_status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] == 'conditional'): ?>
                                            <span class="status-badge status-conditional">Conditional</span>
                                        <?php elseif ($request['status'] == 'disapproved'): ?>
                                            <span class="status-badge status-disapproved">Disapproved</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyStateRow">
                                <td colspan="14" class="text-center py-5">
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editPmTrainingLabel">
                    <i class="fas fa-edit me-2"></i>Edit PM Training Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-3" id="editModalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" type="button" onclick="switchEditModalTab('details')">Details</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="batches-tab" type="button" onclick="switchEditModalTab('batches')">Batches</button>
                    </li>
                </ul>
                
                <!-- Tab Panels -->
                <div class="tab-content">
                    <!-- Details Panel -->
                    <div class="tab-pane fade show active" id="details-panel" role="tabpanel">
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
                                            <button type="button" class="btn btn-success btn-approve" onclick="confirmApprove()">
                                                <i class="fas fa-check-circle me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-conditional" onclick="confirmConditional()">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Conditional
                                            </button>
                                            <button type="button" class="btn btn-danger btn-disapprove" onclick="confirmDisapprove()">
                                                <i class="fas fa-times-circle me-1"></i> Disapprove
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-revert" onclick="confirmRevert()" style="display: none;">
                                                <i class="fas fa-undo me-1"></i> Revert to Pending
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
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
                    
                    <!-- Batches Panel -->
                    <div id="batches-panel" style="display: none;">
                        <div id="editBatchTabsContainer"></div>
                        <div id="editBatchPanelsContainer"></div>
                    </div>
                </div>
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


<!-- Attendance Preview Modal -->
<div class="modal fade" id="attendancePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Attendance Report Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attendancePreviewBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="printAttendanceReport()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="saveAttendancePdf()">
                    <i class="fas fa-file-pdf me-1"></i> Save as PDF
                </button>
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
                <h5 class="modal-title" id="pmReportLabel"><i class="fas fa-chart-line me-2"></i>Generate Training Report</h5>
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
                    <div class="col-md-2">
                        <label class="form-label">Request Status</label>
                        <select id="reportStatus" class="form-select">
                            <option value="">All Request Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="conditional">Conditional</option>
                            <option value="disapproved">Disapproved</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">PTR Status</label>
                        <select id="reportPtrStatus" class="form-select">
                            <option value="">All PTR</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="complete">Complete</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Committee</label>
                        <select id="reportCommittee" class="form-select">
                            <option value="">All Committees</option>
                            <?php foreach ($all_committees as $comm): ?>
                                <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="generateReportPreviewBtn" class="btn btn-success w-100">
                            <i class="fas fa-search me-1"></i> Generate
                        </button>
                    </div>
                </div>
                <div id="reportPreviewContainer" style="display: none;">
                    <div id="reportPreviewContent"></div>
                    <div class="d-flex gap-2 mt-3 justify-content-end">
                        <button class="btn btn-outline-secondary" onclick="printGeneratedReport()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                        <button class="btn btn-outline-secondary" onclick="saveGeneratedReportPdf()">
                            <i class="fas fa-file-pdf me-1"></i> Save as PDF
                        </button>
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
        
        const activeTab = document.querySelector('.batch-tab.active');
        const activeTabIndex = activeTab ? parseInt(activeTab.getAttribute('data-batch-index')) : 0;
        
        let html = '';
        batches.forEach((batch, index) => {
            const isActive = index === activeTabIndex;
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
                <div class="batch-panel ${isActive ? 'active' : ''}" data-batch-panel="${index}">
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
        const excludedUserIds = getSelectedUserIdsFromOtherBatches(batchIndex);
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', 0);
        if (search) url.searchParams.set('search', search);
        if (excludedUserIds.length > 0) {
            url.searchParams.set('exclude_ids', JSON.stringify(excludedUserIds));
        }
        
        const activeTab = document.querySelector('.batch-tab.active');
        const activeTabIndex = activeTab ? parseInt(activeTab.getAttribute('data-batch-index')) : batchIndex;
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    const filteredUsers = data.users.filter(user => !excludedUserIds.includes(user.id));
                    batches[batchIndex].attendees = filteredUsers;
                    renderBatchPanels();
                    setTimeout(() => {
                        switchBatchTab(activeTabIndex);
                    }, 50);
                } else {
                    showToast(data.message || 'Failed to load users', 'danger');
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                showToast('Error loading users', 'danger');
            });
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
        
        const activeTab = document.querySelector('.batch-tab.active');
        const activeTabIndex = activeTab ? parseInt(activeTab.getAttribute('data-batch-index')) : 0;
        
        for (let i = 0; i < batches.length; i++) {
            if (i !== index) {
                loadUsersForBatch(i);
            }
        }
        
        setTimeout(() => {
            switchBatchTab(activeTabIndex);
        }, 100);
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
    
    // ========== BATCHES MODAL (VIEW ONLY) ==========
    
    function openBatchesModal(trainingId, trainingStartDate) {
        currentTrainingId = trainingId;
        currentTrainingStartDate = trainingStartDate;
        
        const modalElement = document.getElementById('batchesModal');
        if (!modalElement) {
            showToast('Error: Modal not found', 'danger');
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        const modalBody = document.getElementById('batchesModalBody');
        modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading batches...</p></div>';
        modal.show();
        
        fetch(`${window.location.href}?get_training_batches=1&id=${trainingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.batches.length > 0) {
                    currentBatchesData = data.batches;
                    renderBatchesModal();
                } else if (data.success && data.batches.length === 0) {
                    modalBody.innerHTML = '<div class="alert alert-info">No batches found for this training.</div>';
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error loading batches') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading batches:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading batches</div>';
            });
    }
    
    function renderBatchesModal() {
        const modalBody = document.getElementById('batchesModalBody');
        
        let tabsHtml = '<div class="batch-modal-tabs">';
        let panelsHtml = '<div>';
        
        currentBatchesData.forEach((batch, index) => {
            const isActive = index === 0;
            const startDateFormatted = formatDate(batch.start_date);
            const endDateFormatted = formatDate(batch.end_date);
            const startWeekday = batch.start_date ? new Date(batch.start_date).toLocaleDateString('en-US', { weekday: 'short' }) : '';
            const endWeekday = batch.end_date ? new Date(batch.end_date).toLocaleDateString('en-US', { weekday: 'short' }) : '';
            
            // Check if attendance has been saved
            const hasAttendance = batch.attendees && batch.attendees.length > 0 && batch.attendees.some(att => att.attended !== undefined && att.attended !== null);
            
            tabsHtml += `
                <div class="batch-modal-tab ${isActive ? 'active' : ''}" onclick="switchBatchModalTab(${index})">
                    Batch ${index + 1}
                </div>
            `;
            
            let attendanceHtml = '';
            if (batch.attendees && batch.attendees.length > 0) {
                if (hasAttendance) {
                    // Split into attended and absent
                    const attended = batch.attendees.filter(att => att.attended == 1);
                    const absent = batch.attendees.filter(att => att.attended != 1);
                    
                    const attendedList = attended.length > 0 
                        ? attended.map(att => `<li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>${escapeHtml(att.fullname)} <small class="text-muted">(${escapeHtml(att.username)})</small></li>`).join('')
                        : '<li class="text-muted">None</li>';
                    
                    const absentList = absent.length > 0
                        ? absent.map(att => `<li class="mb-1"><i class="fas fa-times-circle text-danger me-2"></i>${escapeHtml(att.fullname)} <small class="text-muted">(${escapeHtml(att.username)})</small></li>`).join('')
                        : '<li class="text-muted">None</li>';
                    
                    attendanceHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success"><i class="fas fa-check-circle me-1"></i> Attended (${attended.length})</h6>
                                <ul class="list-unstyled mb-0">${attendedList}</ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i> Absent (${absent.length})</h6>
                                <ul class="list-unstyled mb-0">${absentList}</ul>
                            </div>
                        </div>
                    `;
                } else {
                    attendanceHtml = '<ul class="list-unstyled mb-0">';
                    batch.attendees.forEach(att => {
                        attendanceHtml += `<li class="mb-1"><i class="fas fa-user me-2 text-muted"></i>${escapeHtml(att.fullname)} <small class="text-muted">(${escapeHtml(att.username)})</small></li>`;
                    });
                    attendanceHtml += '</ul>';
                }
            } else {
                attendanceHtml = '<p class="text-muted mb-0">No attendees assigned</p>';
            }
            
            panelsHtml += `
                <div class="batch-modal-panel ${isActive ? 'active' : ''}">
                    <div class="mb-3">
                        <h6><i class="fas fa-calendar me-2"></i>Schedule</h6>
                        <p class="mb-1"><strong>Dates:</strong> ${startDateFormatted} ${startWeekday ? `<span class="weekday-badge">${startWeekday}</span>` : ''} - ${endDateFormatted} ${endWeekday ? `<span class="weekday-badge">${endWeekday}</span>` : ''}</p>
                        <p class="mb-0"><strong>Times:</strong> ${batch.start_time || 'N/A'} - ${batch.end_time || 'N/A'}</p>
                    </div>
                    <div>
                        <h6><i class="fas fa-${hasAttendance ? 'clipboard-check' : 'users'} me-2"></i>${hasAttendance ? 'Attendance' : 'Attendees'} (${batch.attendee_count})</h6>
                        ${attendanceHtml}
                    </div>
                </div>
            `;
        });
        
        tabsHtml += '</div>';
        panelsHtml += '</div>';
        
        modalBody.innerHTML = tabsHtml + panelsHtml;
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
    
    // ========== BATCH EDITING IN EDIT MODAL ==========
    let editBatches = [];
    let editTrainingStart = '';
    let editTrainingEnd = '';
    let editRequestId = null;
    
    function getSelectedUserIdsFromOtherEditBatches(currentBatchIndex) {
        let selectedIds = [];
        for (let i = 0; i < editBatches.length; i++) {
            if (i !== currentBatchIndex) {
                selectedIds = selectedIds.concat(editBatches[i].selectedAttendees);
            }
        }
        return selectedIds;
    }
    
    function initEditBatchTabs(requestId, trainingStart, trainingEnd, isAttendanceMode = false) {
        editRequestId = requestId;
        editTrainingStart = trainingStart;
        editTrainingEnd = trainingEnd;
        
        fetch(`${window.location.href}?get_training_batches=1&id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fetch attendance status for each batch
                    const batchIds = data.batches.map(b => b.id);
                    fetch(`${window.location.href}?get_attendance_status=1&batch_ids=${batchIds.join(',')}`)
                        .then(res => res.json())
                        .then(attData => {
                            const attendanceMap = {};
                            if (attData.success && attData.attendance) {
                                attData.attendance.forEach(row => {
                                    if (!attendanceMap[row.batch_id]) attendanceMap[row.batch_id] = {};
                                    attendanceMap[row.batch_id][row.user_id] = row.attended == 1;
                                });
                            }
                            
                            editBatches = data.batches.map(batch => ({
                                id: batch.id,
                                name: batch.name,
                                start_date: batch.start_date,
                                end_date: batch.end_date,
                                start_time: batch.start_time,
                                end_time: batch.end_time,
                                attendees: batch.attendees || [],
                                selectedAttendees: (batch.attendees || []).map(a => parseInt(a.id)),
                                attendance: attendanceMap[batch.id] || {}
                            }));
                            
                            renderEditBatchTabs(isAttendanceMode);
                            renderEditBatchPanels(isAttendanceMode);
                            if (!isAttendanceMode) {
                                editBatches.forEach((_, index) => {
                                    loadUsersForEditBatch(index);
                                });
                            }
                        });
                } else {
                    editBatches = [];
                    renderEditBatchTabs(isAttendanceMode);
                    renderEditBatchPanels(isAttendanceMode);
                }
            });
    }
    
    function renderEditBatchTabs(isAttendanceMode = false) {
        const container = document.getElementById('editBatchTabsContainer');
        if (!container) return;
        
        let html = '<div class="batch-tabs">';
        editBatches.forEach((batch, index) => {
            html += `
                <div class="batch-tab ${index === 0 ? 'active' : ''}" onclick="switchEditBatchTab(${index})">
                    ${batch.name}
                    ${!isAttendanceMode && editBatches.length > 1 ? `<span class="batch-tab-remove" onclick="event.stopPropagation(); deleteEditBatch(${batch.id}, ${index})">&times;</span>` : ''}
                </div>
            `;
        });
        if (!isAttendanceMode && editBatches.length < 10) {
            html += `<div class="batch-tab add-batch-tab" onclick="addBatchToEditTraining()">+ Add Batch</div>`;
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function addBatchToEditTraining() {
        if (editBatches.length >= 10) {
            showToast('Maximum 10 batches allowed', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('add_batch_to_training', '1');
        formData.append('training_id', editRequestId);
        formData.append('batch_name', `Batch ${editBatches.length + 1}`);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Batch added!', 'success');
                    // Reload batch tabs
                    initEditBatchTabs(editRequestId, editTrainingStart, editTrainingEnd);
                } else {
                    showToast(data.message, 'danger');
                }
            });
    }

    function deleteEditBatch(batchId, index) {
    if (!confirm(`Are you sure you want to delete ${editBatches[index].name}?`)) return;
    
    const formData = new FormData();
    formData.append('delete_batch_from_training', '1');
    formData.append('batch_id', batchId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Batch deleted!', 'success');
                initEditBatchTabs(editRequestId, editTrainingStart, editTrainingEnd);
            } else {
                showToast(data.message, 'danger');
            }
        });
    }
    
    function renderEditBatchPanels(isAttendanceMode = false) {
        const container = document.getElementById('editBatchPanelsContainer');
        if (!container) return;
        
        const activeTab = document.querySelector('#editBatchTabsContainer .batch-tab.active');
        let activeIndex = 0;
        if (activeTab) {
            const tabs = document.querySelectorAll('#editBatchTabsContainer .batch-tab');
            tabs.forEach((tab, i) => {
                if (tab === activeTab) activeIndex = i;
            });
        }
        
        let html = '';
        editBatches.forEach((batch, index) => {
            const isActive = index === activeIndex;
            
            let attendeesSection = '';
            
            if (isAttendanceMode) {
                // Attendance mode - show checkboxes for attended/absent
                const attendanceHtml = (batch.attendees || []).map(att => {
                    const isPresent = batch.attendance && batch.attendance[att.id] === true;
                    return `
                        <div class="batch-attendee-item">
                            <input type="checkbox" class="attendance-checkbox" value="${att.id}" ${isPresent ? 'checked' : ''} onchange="toggleAttendance(${index}, ${att.id}, this.checked)">
                            <div class="attendee-info">
                                <div class="attendee-name">${escapeHtml(att.fullname)}</div>
                                <div class="attendee-username">${escapeHtml(att.username)}</div>
                            </div>
                            <span class="badge ms-auto ${isPresent ? 'bg-success' : 'bg-secondary'}">${isPresent ? 'Present' : 'Absent'}</span>
                        </div>
                    `;
                }).join('');
                
                attendeesSection = `
                    <div class="mt-3">
                        <label class="form-label">Attendance List</label>
                        <div class="batch-attendee-list">
                            ${attendanceHtml || '<div class="text-center py-3 text-muted">No attendees</div>'}
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-success" onclick="submitAttendance(${batch.id}, ${index})">
                                <i class="fas fa-save me-1"></i> Submit Attendance
                            </button>
                            <button class="btn btn-info" onclick="previewAttendanceReport(${batch.id})">
                                <i class="fas fa-eye me-1"></i> Preview Report
                            </button>
                        </div>
                    </div>
                `;
            } else {
                // Edit mode - show attendee checkboxes for selection
                const attendeesHtml = (batch.attendees || []).map(att => {
                    const isChecked = batch.selectedAttendees.includes(parseInt(att.id));
                    return `
                        <div class="batch-attendee-item">
                            <input type="checkbox" class="batch-attendee-checkbox" value="${att.id}" ${isChecked ? 'checked' : ''} onchange="toggleEditBatchAttendee(${index}, ${att.id})">
                            <div class="attendee-info">
                                <div class="attendee-name">${escapeHtml(att.fullname)}</div>
                                <div class="attendee-username">${escapeHtml(att.username)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                attendeesSection = `
                    <div class="mt-3">
                        <label class="form-label">Attendees</label>
                        <div class="search-box">
                            <input type="text" class="form-control edit-batch-attendee-search" placeholder="Search attendees..." onkeyup="searchEditBatchAttendees(${index}, this.value)">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearEditBatchSearch(${index})">Clear</button>
                        </div>
                        <div class="batch-attendee-list" id="edit-batch-attendee-list-${index}">
                            ${attendeesHtml || '<div class="text-center py-3 text-muted">No attendees available</div>'}
                        </div>
                        <small class="text-muted">Selected: <span id="edit-batch-selected-count-${index}">${batch.selectedAttendees.length}</span> attendees</small>
                    </div>
                `;
            }
            
            html += `
                <div class="batch-panel ${isActive ? 'active' : ''}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" value="${batch.start_date || ''}" ${!isAttendanceMode ? '' : 'disabled'}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" value="${batch.end_date || ''}" ${!isAttendanceMode ? '' : 'disabled'}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" value="${batch.start_time || ''}" ${!isAttendanceMode ? '' : 'disabled'}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" value="${batch.end_time || ''}" ${!isAttendanceMode ? '' : 'disabled'}>
                        </div>
                    </div>
                    ${attendeesSection}
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function generateAttendanceReport(batchId) {
        fetch(`${window.location.href}?get_attendance_report=1&batch_id=${batchId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create CSV content
                    let csv = 'Name,Username,Status\n';
                    
                    data.attendees.forEach(att => {
                        const status = att.attended == 1 ? 'Present' : 'Absent';
                        csv += `"${att.fullname}","${att.username}","${status}"\n`;
                    });
                    
                    // Download CSV
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `attendance_batch_${batchId}_${new Date().toISOString().slice(0,10)}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    showToast('Report downloaded!', 'success');
                } else {
                    showToast(data.message || 'Error generating report', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error generating report', 'danger');
            });
    }

    function toggleAttendance(batchIndex, userId, attended) {
        if (!editBatches[batchIndex].attendance) {
            editBatches[batchIndex].attendance = {};
        }
        editBatches[batchIndex].attendance[userId] = attended;
        
        // Update badge
        const item = event.target.closest('.batch-attendee-item');
        const badge = item.querySelector('.badge');
        if (attended) {
            badge.className = 'badge bg-success ms-auto';
            badge.textContent = 'Present';
        } else {
            badge.className = 'badge bg-secondary ms-auto';
            badge.textContent = 'Absent';
        }
    }
    
    function submitAttendance(batchId, batchIndex) {
        const panel = document.querySelector(`#editBatchPanelsContainer .batch-panel:nth-child(${batchIndex + 1})`);
        if (!panel) {
            showToast('Error: Panel not found', 'danger');
            return;
        }
        
        const checkboxes = panel.querySelectorAll('.attendance-checkbox');
        if (checkboxes.length === 0) {
            showToast('No attendees to save', 'warning');
            return;
        }
        
        const promises = [];
        
        checkboxes.forEach(cb => {
            const userId = parseInt(cb.value);
            const attended = cb.checked ? 1 : 0;
            
            console.log('Saving attendance:', { batchId, userId, attended });
            
            const formData = new FormData();
            formData.append('update_attendance_ajax', '1');
            formData.append('batch_id', batchId);
            formData.append('user_id', userId);
            formData.append('attended', attended);
            
            promises.push(
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(r => r.json())
            );
        });
        
        Promise.all(promises)
            .then(results => {
                console.log('Attendance results:', results);
                const allSuccess = results.every(r => r.success);
                if (allSuccess) {
                    showToast('Attendance saved!', 'success');
                } else {
                    const errors = results.filter(r => !r.success).map(r => r.message).join(', ');
                    showToast('Failed: ' + errors, 'danger');
                }
            })
            .catch(error => {
                console.error('Attendance error:', error);
                showToast('Error saving attendance', 'danger');
            });
    }
    
    function loadUsersForEditBatch(batchIndex, search = '') {
        const excludedUserIds = getSelectedUserIdsFromOtherEditBatches(batchIndex);
        
        const url = new URL(window.location.href);
        url.searchParams.set('get_users_for_attendance', '1');
        url.searchParams.set('request_id', editRequestId);
        url.searchParams.set('current_batch_id', editBatches[batchIndex].id || 0);
        if (search) url.searchParams.set('search', search);
        if (excludedUserIds.length > 0) {
            url.searchParams.set('exclude_ids', JSON.stringify(excludedUserIds));
        }
        
        fetch(url.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    const filteredUsers = data.users.filter(user => !excludedUserIds.includes(parseInt(user.id)));
                    editBatches[batchIndex].attendees = filteredUsers;
                    renderEditBatchPanels();
                }
            });
    }
    
    function searchEditBatchAttendees(batchIndex, search) {
        if (search.length >= 2 || search.length === 0) {
            loadUsersForEditBatch(batchIndex, search);
        }
    }
    
    function clearEditBatchSearch(batchIndex) {
        const searchInput = document.querySelector(`#editBatchPanelsContainer .edit-batch-attendee-search`);
        if (searchInput) {
            searchInput.value = '';
            loadUsersForEditBatch(batchIndex, '');
        }
    }
    
    function switchEditBatchTab(index) {
        const tabs = document.querySelectorAll('#editBatchTabsContainer .batch-tab');
        const panels = document.querySelectorAll('#editBatchPanelsContainer .batch-panel');
        tabs.forEach((tab, i) => {
            if (i === index) tab.classList.add('active');
            else tab.classList.remove('active');
        });
        panels.forEach((panel, i) => {
            if (i === index) panel.classList.add('active');
            else panel.classList.remove('active');
        });
    }
    
    function toggleEditBatchAttendee(index, userId) {
        const id = parseInt(userId);
        const idx = editBatches[index].selectedAttendees.indexOf(id);
        if (idx === -1) {
            editBatches[index].selectedAttendees.push(id);
        } else {
            editBatches[index].selectedAttendees.splice(idx, 1);
        }
        
        const countSpan = document.getElementById(`edit-batch-selected-count-${index}`);
        if (countSpan) countSpan.innerText = editBatches[index].selectedAttendees.length;
        
        for (let i = 0; i < editBatches.length; i++) {
            if (i !== index) {
                loadUsersForEditBatch(i);
            }
        }
    }
    
    function updateEditBatchStartDate(index, date) {
        editBatches[index].start_date = date;
    }
    
    function updateEditBatchEndDate(index, date) {
        editBatches[index].end_date = date;
    }
    
    function updateEditBatchStartTime(index, time) {
        editBatches[index].start_time = time;
    }
    
    function updateEditBatchEndTime(index, time) {
        editBatches[index].end_time = time;
    }
    
    function saveAllBatchChanges() {
        const promises = editBatches.map(batch => {
            const formData = new FormData();
            formData.append('update_batch_ajax', '1');
            formData.append('batch_id', batch.id);
            formData.append('start_date', batch.start_date);
            formData.append('end_date', batch.end_date);
            formData.append('start_time', batch.start_time || '');
            formData.append('end_time', batch.end_time || '');
            formData.append('attendees', JSON.stringify(batch.selectedAttendees));
            
            return fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json());
        });
        
        return Promise.all(promises);
    }

    function previewAttendanceReport(batchId) {
        const modal = new bootstrap.Modal(document.getElementById('attendancePreviewModal'));
        const modalBody = document.getElementById('attendancePreviewBody');
        modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>';
        modal.show();
        
        // Fetch ALL batches for this training to show full report
        fetch(`${window.location.href}?get_training_batches=1&id=${editRequestId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.batches.length > 0) {
                    // Get training info
                    fetch(`${window.location.href}?get_pm_request=1&id=${editRequestId}`)
                        .then(res => res.json())
                        .then(reqData => {
                            const training = reqData.success ? reqData.request : {};
                            renderAttendancePreview(training, data.batches, batchId);
                        });
                } else {
                    modalBody.innerHTML = '<div class="alert alert-info">No batches found</div>';
                }
            });
    }
    
    function renderAttendancePreview(training, batches, highlightBatchId) {
        const modalBody = document.getElementById('attendancePreviewBody');
        
        let html = '';
        
        // Header
        html += `
            <div style="text-align: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #000;">
                <h4 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">${escapeHtml(training.title || 'Training')}</h4>
                <p style="margin: 0 0 4px 0; font-size: 13px;">${escapeHtml(training.venue || '')}</p>
                <p style="margin: 0 0 4px 0; font-size: 13px;">${formatDate(training.date_start)} — ${formatDate(training.date_end)}</p>
                <p style="margin: 0; font-size: 12px; color: #555;">Program Manager: ${escapeHtml(training.requester_name || '')}</p>
            </div>
        `;
        
        batches.forEach(batch => {
            const isHighlighted = batch.id == highlightBatchId;
            const hasAttendance = batch.attendees && batch.attendees.length > 0 && batch.attendees.some(att => att.attended !== undefined && att.attended !== null);
            
            const attended = hasAttendance ? (batch.attendees || []).filter(att => att.attended == 1) : [];
            const absent = hasAttendance ? (batch.attendees || []).filter(att => att.attended != 1) : [];
            const maxRows = Math.max(attended.length, absent.length, 1);
            
            let tableRows = '';
            for (let i = 0; i < maxRows; i++) {
                const attUser = attended[i] ? `${escapeHtml(attended[i].fullname)}` : '';
                const absUser = absent[i] ? `${escapeHtml(absent[i].fullname)}` : '';
                tableRows += `
                    <tr>
                        <td style="width: 50%; padding: 6px 12px; border-right: 1px solid #ccc;">${attUser}</td>
                        <td style="width: 50%; padding: 6px 12px;">${absUser}</td>
                    </tr>
                `;
            }
            
            html += `
                <div style="margin-bottom: 25px; border: 1px solid #ccc;">
                    <div style="padding: 8px 12px; font-weight: 600; font-size: 13px; background: #f5f5f5; border-bottom: 1px solid #ccc;">
                        ${escapeHtml(batch.name)}
                        <span style="font-weight: 400; font-size: 12px; margin-left: 10px;">${formatDate(batch.start_date)} — ${formatDate(batch.end_date)}</span>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #ccc;">
                                <th style="width: 50%; padding: 6px 12px; text-align: left; font-weight: 600; border-right: 1px solid #ccc;">Attended (${attended.length})</th>
                                <th style="width: 50%; padding: 6px 12px; text-align: left; font-weight: 600;">Absent (${absent.length})</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;
        });
        
        // Footer
        html += `
            <div style="text-align: right; font-size: 11px; color: #888; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc;">
                Generated on ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
            </div>
        `;
        
        modalBody.innerHTML = html;
    }
    
    function printAttendanceReport() {
        const modalBody = document.getElementById('attendancePreviewBody').innerHTML;
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Attendance Report</title>
                <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; color: #333; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #dee2e6; }
                    .text-success { color: #198754; }
                    .text-danger { color: #dc3545; }
                    .text-muted { color: #6c757d; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>${modalBody}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 300);
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
                    
                    const ptrHtml = request.ptr_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${request.ptr_file}" target="_blank">View Current PTR File</a>` : '<span class="text-muted">No PTR file uploaded</span>';
                    const attendanceHtml = request.attendance_file ? `<a href="<?= BASE_URL ?>/uploads/pm_training/${request.attendance_file}" target="_blank">View Current Attendance File</a>` : '<span class="text-muted">No attendance file uploaded</span>';
                    document.getElementById('current_ptr_file').innerHTML = ptrHtml;
                    document.getElementById('current_attendance_file').innerHTML = attendanceHtml;
                    
                    const adminActionsContainer = document.getElementById('adminActionsButtons')?.parentElement?.parentElement;
                    if (adminActionsContainer) {
                        adminActionsContainer.style.display = 'block';
                        // Show/hide buttons based on current status
                        const btnApprove = document.querySelector('.btn-approve');
                        const btnConditional = document.querySelector('.btn-conditional');
                        const btnDisapprove = document.querySelector('.btn-disapprove');
                        const btnRevert = document.querySelector('.btn-revert');
                        
                        if (request.status === 'pending') {
                            // Pending: show approve/conditional/disapprove, hide revert
                            if (btnApprove) btnApprove.style.display = '';
                            if (btnConditional) btnConditional.style.display = '';
                            if (btnDisapprove) btnDisapprove.style.display = '';
                            if (btnRevert) btnRevert.style.display = 'none';
                        } else {
                            // Approved, conditional, or disapproved: hide all except revert
                            if (btnApprove) btnApprove.style.display = 'none';
                            if (btnConditional) btnConditional.style.display = 'none';
                            if (btnDisapprove) btnDisapprove.style.display = 'none';
                            if (btnRevert) btnRevert.style.display = '';
                        }
                    }
                    
                    // Declare date variables ONCE
                    const currentDate = new Date();
                    const startDate = new Date(request.date_start);
                    const endDate = new Date(request.date_end);
                    const isPastEndDate = currentDate > endDate;
                    const isBeforeStart = currentDate < startDate;
                    
                    // Attachments section
                    const canShowAttachments = isPastEndDate && (request.status === 'approved' || request.status === 'conditional');
                    const attachmentsSection = document.getElementById('attachmentsSection');
                    if (attachmentsSection) {
                        attachmentsSection.style.display = canShowAttachments ? 'block' : 'none';
                    }
                    
                    // Complete button
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
                    
                    // Handle batches tab visibility
                    const showBatchesTab = isBeforeStart || isPastEndDate;
                    
                    const batchesTabLi = document.getElementById('batches-tab')?.parentElement;
                    if (batchesTabLi) {
                        if (showBatchesTab) {
                            batchesTabLi.style.display = '';
                            initEditBatchTabs(request.id, request.date_start, request.date_end, isPastEndDate);
                        } else {
                            batchesTabLi.style.display = 'none';
                        }
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('editPmTrainingModal'));
                    modal.show();
                    
                    setTimeout(() => {
                        switchEditModalTab('details');
                    }, 200);
                    
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading request data', 'danger');
            });
    }

    function confirmRevert() {
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) adminButtons.style.display = 'none';
        submitAdminAction('revert', '');
    }

    function switchEditModalTab(tabName) {
        const detailsTab = document.getElementById('details-tab');
        const batchesTab = document.getElementById('batches-tab');
        const detailsPanel = document.getElementById('details-panel');
        const batchesPanel = document.getElementById('batches-panel');
        
        if (!detailsPanel || !batchesPanel) return;
        
        if (detailsTab) detailsTab.classList.remove('active');
        if (batchesTab) batchesTab.classList.remove('active');
        
        detailsPanel.style.display = 'none';
        batchesPanel.style.display = 'none';
        
        if (tabName === 'details') {
            if (detailsTab) detailsTab.classList.add('active');
            detailsPanel.style.display = 'block';
        } else if (tabName === 'batches') {
            if (batchesTab) batchesTab.classList.add('active');
            batchesPanel.style.display = 'block';
        }
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
    
    // ========== ADMIN ACTION FUNCTIONS ==========
    
    function confirmApprove() {
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) adminButtons.style.display = 'none';
        submitAdminAction('approve', '');
    }
    
    function confirmConditional() {
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) adminButtons.style.display = 'none';
        submitAdminAction('conditional', '');
    }
    
    function confirmDisapprove() {
        const adminButtons = document.getElementById('adminActionsButtons');
        if (adminButtons) adminButtons.style.display = 'none';
        submitAdminAction('disapprove', '');
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
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
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
                if (adminButtons) adminButtons.style.display = 'flex';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'danger');
            const adminButtons = document.getElementById('adminActionsButtons');
            if (adminButtons) adminButtons.style.display = 'flex';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    // Edit form submission - saves batches first, then submits form
    document.getElementById('editPmTrainingForm')?.addEventListener('submit', function(e) {
        const adminAction = document.getElementById('adminAction').value;
        if (adminAction) return;
        
        e.preventDefault();
        
        saveAllBatchChanges()
            .then(results => {
                const allSuccess = results.every(r => r.success);
                if (!allSuccess) {
                    const errors = results.filter(r => !r.success).map(r => r.message).join(', ');
                    showToast('Batch update failed: ' + errors, 'danger');
                    return;
                }
                
                const formData = new FormData(this);
                formData.append('edit_pm_request_ajax', '1');
                
                const submitBtn = document.getElementById('updateRequestBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
                
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
                        showToast('An error occurred', 'danger');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
            });
    });

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
                                    // Check if attendance has been saved
                                    const hasViewAttendance = batch.attendees.some(att => att.attended !== undefined && att.attended !== null);
                                    if (hasViewAttendance) {
                                        const viewAttended = batch.attendees.filter(att => att.attended == 1);
                                        const viewAbsent = batch.attendees.filter(att => att.attended != 1);
                                        batchesHtml += `<div class="row mt-2"><div class="col-md-6"><small class="text-success"><strong>Attended (${viewAttended.length})</strong></small><ul class="list-unstyled mb-0 small">`;
                                        viewAttended.forEach(att => { batchesHtml += `<li><i class="fas fa-check-circle text-success me-1"></i>${escapeHtml(att.fullname)} (${escapeHtml(att.username)})</li>`; });
                                        batchesHtml += `</ul></div><div class="col-md-6"><small class="text-danger"><strong>Absent (${viewAbsent.length})</strong></small><ul class="list-unstyled mb-0 small">`;
                                        viewAbsent.forEach(att => { batchesHtml += `<li><i class="fas fa-times-circle text-danger me-1"></i>${escapeHtml(att.fullname)} (${escapeHtml(att.username)})</li>`; });
                                        batchesHtml += `</ul></div></div>`;
                                    } else {
                                        batchesHtml += `<ul>`;
                                        batch.attendees.forEach(att => {
                                            batchesHtml += `<li>${escapeHtml(att.fullname)} (${escapeHtml(att.username)})</li>`;
                                        });
                                        batchesHtml += `</ul>`;
                                    }
                                    batchesHtml += `</ul></div>`;
                                });
                            } else {
                                batchesHtml += '<p class="text-muted">No batches found</p>';
                            }
                            batchesHtml += '</div>';
                            
                            modalBody.innerHTML = `
                                <div class="row">
                                    <div class="col-md-6"><div class="view-details-card"><h6>Training Information</h6><p><strong>Title:</strong> ${escapeHtml(r.title)}</p><p><strong>Venue:</strong> ${escapeHtml(r.venue)}</p><p><strong>Committee:</strong> ${escapeHtml(r.committee_name || '-')}</p><p><strong>Hospital Order No.:</strong> ${escapeHtml(r.hospital_order_no || '-')}</p></div></div>
                                    <div class="col-md-6"><div class="view-details-card"><h6>Schedule</h6><p><strong>Date Start:</strong> ${formatDate(r.date_start)}</p><p><strong>Date End:</strong> ${formatDate(r.date_end)}</p><p><strong>Amount:</strong> ${parseFloat(r.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p><p><strong>Late Filing:</strong> ${r.late_filing ? 'Yes' : 'No'}</p></div></div>
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
    
    // ========== GENERATE REPORT MODAL ==========
    
    <?php if ($is_admin): ?>
    function loadReportFilterOptions() {
        fetch(`${window.location.href}?get_pm_filter_options=1`)
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const ys = document.getElementById('reportYear');
                    ys.innerHTML = '<option value="">All Years</option>';
                    d.years.forEach(y => { ys.innerHTML += `<option value="${y}">${y}</option>`; });
                }
            });
    }
    
function generateReportPreview() {
        const year = document.getElementById('reportYear').value;
        const month = document.getElementById('reportMonth').value;
        const status = document.getElementById('reportStatus').value;
        const ptrStatus = document.getElementById('reportPtrStatus').value;
        const committee = document.getElementById('reportCommittee').value;
        
        let url = `${window.location.href}?get_pm_report_data=1`;
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        if (status) url += `&status=${status}`;
        if (ptrStatus) url += `&ptr_status=${ptrStatus}`;
        if (committee) url += `&committee=${committee}`;
        
        fetch(url).then(r => r.json()).then(d => {
            const container = document.getElementById('reportPreviewContainer');
            const content = document.getElementById('reportPreviewContent');
            container.style.display = 'block';
            
            if (d.success && d.reports.length > 0) {
                // Build filter summary — show ALL filters with names, not IDs
                let filtersUsed = [];
                
                // Year
                filtersUsed.push('Year: ' + (year || 'All'));
                
                // Month
                filtersUsed.push('Month: ' + (month ? new Date(2000, month - 1).toLocaleString('en-US', { month: 'long' }) : 'All'));
                
                // Request Status
                filtersUsed.push('Request Status: ' + (status ? ucfirst(status) : 'All'));
                
                // PTR Status
                filtersUsed.push('PTR Status: ' + (ptrStatus ? ucfirst(ptrStatus) : 'All'));
                
                // Committee — show name, not ID
                const commSelect = document.getElementById('reportCommittee');
                let committeeName = 'All';
                if (committee && commSelect) {
                    const commOption = commSelect.querySelector(`option[value="${committee}"]`);
                    if (commOption) committeeName = commOption.textContent;
                }
                filtersUsed.push('Committee: ' + committeeName);
                
                let html = `<div style="text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #ccc;">
                    <h4 style="margin:0 0 5px;font-size:16px;font-weight:600;">Training Report</h4>
                    <p style="margin:0;font-size:12px;color:#666;">${filtersUsed.join(' | ')}</p>
                </div>`;
                html += `<table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead><tr style="background:#f5f5f5;border-bottom:1px solid #ccc;">
                        <th style="padding:6px 8px;text-align:left;">Title</th>
                        <th style="padding:6px 8px;text-align:left;">Venue</th>
                        <th style="padding:6px 8px;text-align:left;">From</th>
                        <th style="padding:6px 8px;text-align:left;">To</th>
                        <th style="padding:6px 8px;text-align:left;">Program Manager</th>
                        <th style="padding:6px 8px;text-align:left;">HO No.</th>
                        <th style="padding:6px 8px;text-align:right;">Amount</th>
                        <th style="padding:6px 8px;text-align:left;">Status</th>
                        <th style="padding:6px 8px;text-align:left;">PTR</th>
                    </tr></thead><tbody>`;
                d.reports.forEach(r => {
                    html += `<tr style="border-bottom:1px solid #eee;">
                        <td style="padding:4px 8px;">${escapeHtml(r.title)}</td>
                        <td style="padding:4px 8px;">${escapeHtml(r.venue)}</td>
                        <td style="padding:4px 8px;">${escapeHtml(r.date_start)}</td>
                        <td style="padding:4px 8px;">${escapeHtml(r.date_end)}</td>
                        <td style="padding:4px 8px;">${escapeHtml(r.requester_name)}</td>
                        <td style="padding:4px 8px;">${escapeHtml(r.hospital_order_no||'-')}</td>
                        <td style="padding:4px 8px;text-align:right;">₱${parseFloat(r.amount).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                        <td style="padding:4px 8px;">${ucfirst(r.status)}</td>
                        <td style="padding:4px 8px;">${ucfirst(r.ptr_status)}</td>
                    </tr>`;
                });
                html += `</tbody></table>`;
                html += `<p style="text-align:right;font-size:11px;color:#888;margin-top:10px;">Total Records: ${d.reports.length}</p>`;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2"></i><p>No records found</p></div>';
            }
        });
    }
    
    function printGeneratedReport() {
        const content = document.getElementById('reportPreviewContent').innerHTML;
        const w = window.open('', '_blank', 'width=1000,height=700');
        w.document.write(`<!DOCTYPE html><html><head><title>Training Report</title><style>body{font-family:Arial,sans-serif;padding:30px;color:#222;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:6px 8px;}th{background:#f5f5f5;}@media print{body{padding:15px;}}</style></head><body>${content}</body></html>`);
        w.document.close(); w.focus(); setTimeout(() => w.print(), 300);
    }
    
    function saveGeneratedReportPdf() {
        printGeneratedReport();
    }
    
    document.getElementById('generateReportPreviewBtn')?.addEventListener('click', generateReportPreview);
    document.getElementById('pmReportModal')?.addEventListener('show.bs.modal', () => {
        loadReportFilterOptions();
        document.getElementById('reportPreviewContainer').style.display = 'none';
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
    
    // Reset edit modal tabs when modal opens
    document.getElementById('editPmTrainingModal')?.addEventListener('show.bs.modal', function() {
        setTimeout(() => {
            if (typeof switchEditModalTab === 'function') {
                switchEditModalTab('details');
            }
        }, 100);
    });
</script>
</body>
</html>
<?php
ob_end_flush();
?>