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
        $training_type = trim($_POST['training_type'] ?? '');
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
        if (empty($training_type)) $errors[] = "Training type is required";
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
        $training_type = trim($_POST['training_type'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $location_type = trim($_POST['location_type'] ?? '');
        $hospital_id = trim($_POST['hospital_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $late_filing = isset($_POST['late_filing']) ? 1 : 0;
        $official_business = isset($_POST['official_business']) ? 1 : 0;
        $remarks = trim($_POST['remarks'] ?? '');
        $approve_status = isset($_POST['approve_status']) ? 1 : 0;
        
        $errors = [];
        if (empty($training_type)) $errors[] = "Training type is required";
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
        
        // Handle file uploads
        $upload_dir = __DIR__ . '/../uploads/training/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        function uploadTrainingFile($file, $field_name) {
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
        
        $ptr_file = uploadTrainingFile($_FILES, 'ptr_file');
        $coc_file = uploadTrainingFile($_FILES, 'coc_file');
        $coa_file = uploadTrainingFile($_FILES, 'coa_file');
        $mom_file = uploadTrainingFile($_FILES, 'mom_file');
        
        $sql = "UPDATE training_requests SET 
            training_type = ?, title = ?, date_start = ?, date_end = ?, 
            location_type = ?, hospital_order_no = ?, amount = ?, 
            late_filing = ?, official_business = ?, remarks = ?";
        $params = [$training_type, $title, $date_start, $date_end, $location_type, 
                   $hospital_id, $amount, $late_filing, $official_business, $remarks];
        
        if ($ptr_file) {
            $sql .= ", ptr_file = ?";
            $params[] = $ptr_file;
        }
        if ($coc_file) {
            $sql .= ", coc_file = ?";
            $params[] = $coc_file;
        }
        if ($coa_file) {
            $sql .= ", coa_file = ?";
            $params[] = $coa_file;
        }
        if ($mom_file) {
            $sql .= ", mom_file = ?";
            $params[] = $mom_file;
        }
        
        // Update status if approved (only admin/superadmin can approve)
        if ($approve_status && (is_admin() || is_superadmin())) {
            $sql .= ", status = 'approved'";
        }
        
        $sql .= " WHERE id = ?";
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

// Handle filter
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$where_clause = [];
$params = [];

if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $where_clause[] = "MONTH(date_start) = ? AND YEAR(date_start) = YEAR(CURDATE())";
    $params[] = $filter_month;
}

$where_sql = !empty($where_clause) ? "WHERE " . implode(" AND ", $where_clause) : "";

// Fetch training requests
$query = "SELECT 
    tr.*,
    COALESCE(CONCAT(u.fname, ' ', u.lname), u.username, 'Unknown') as requester_name
    FROM training_requests tr
    LEFT JOIN users u ON tr.requester_id = u.id
    $where_sql
    ORDER BY tr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$training_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN training_type = 'Internal' THEN 1 ELSE 0 END) as internal_count,
    SUM(CASE WHEN training_type = 'External' THEN 1 ELSE 0 END) as external_count,
    COALESCE(SUM(amount), 0) as total_amount
    FROM training_requests
    WHERE YEAR(date_start) = YEAR(CURDATE())";
$stats_result = $pdo->query($stats_query);
$stats = $stats_result->fetch(PDO::FETCH_ASSOC);

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
                    <label class="form-label">Filter by Month</label>
                    <select name="filter_month" class="form-select" id="filterMonth">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="search-group">
                    <label class="form-label">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by title, type, order no., remarks, or resched reason...">
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
                <span class="stat-label">Internal Training:</span>
                <span class="stat-number"><?= number_format($stats['internal_count'] ?? 0) ?></span>
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
                <h4><i class="fas fa-list"></i> Training Requests List</h4>
            </div>
            <div class="table-responsive">
                <table class="table" id="trainingTable">
                    <thead>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Type</th>
        <th>From</th>
        <th>To</th>
        <th>Requester</th>
        <th>Hospital Order No.</th>
        <th>Amount</th>
        <th>Is OB</th>
        <th>Remarks</th>
        <th>Resched Reason</th>
        <th>Attachments</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody id="trainingTableBody">
    <?php if (!empty($training_requests)): ?>
        <?php foreach ($training_requests as $request): ?>
            <tr data-id="<?= $request['id'] ?>"
                data-title="<?= strtolower(htmlspecialchars($request['title'])) ?>" 
                data-type="<?= strtolower(htmlspecialchars($request['training_type'])) ?>"
                data-order="<?= strtolower(htmlspecialchars($request['hospital_order_no'])) ?>"
                data-remarks="<?= strtolower(htmlspecialchars($request['remarks'] ?? '')) ?>"
                data-resched="<?= strtolower(htmlspecialchars($request['resched_reason'] ?? '')) ?>">
                <td><?= $request['id'] ?></td>
                <td><strong><?= htmlspecialchars($request['title']) ?></strong></td>
                <td>
                    <span class="badge <?= $request['training_type'] == 'Internal' ? 'badge-info' : 'badge-warning' ?>">
                        <?= htmlspecialchars($request['training_type']) ?>
                    </span>
                </td>
                <td><?= date('M d, Y', strtotime($request['date_start'])) ?></td>
                <td><?= date('M d, Y', strtotime($request['date_end'])) ?></td>
                <td><?= htmlspecialchars($request['requester_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($request['hospital_order_no']) ?></td>
                <td>₱<?= number_format($request['amount'], 2) ?></td>
                <td>
                    <?= $request['official_business'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?>
                </td>
                <td class="truncated-cell" title="<?= htmlspecialchars($request['remarks'] ?? '') ?>">
                    <?php 
                    $remarks = $request['remarks'] ?? '';
                    echo htmlspecialchars(strlen($remarks) > 30 ? substr($remarks, 0, 30) . '...' : $remarks);
                    ?>
                </td>
                <td class="truncated-cell" title="<?= htmlspecialchars($request['resched_reason'] ?? '') ?>">
                    <?php 
                    $resched_reason = $request['resched_reason'] ?? '';
                    echo htmlspecialchars(strlen($resched_reason) > 30 ? substr($resched_reason, 0, 30) . '...' : $resched_reason);
                    ?>
                </td>
                <td class="attachment-buttons">
                    <?php
                    $files = [
                        'ptr' => $request['ptr_file'] ?? null,
                        'coc' => $request['coc_file'] ?? null,
                        'coa' => $request['coa_file'] ?? null,
                        'mom' => $request['mom_file'] ?? null
                    ];
                    $has_files = false;
                    foreach ($files as $type => $file) {
                        if ($file) {
                            $has_files = true;
                            $file_url = BASE_URL . '/uploads/training/' . $file;
                            $file_label = strtoupper($type);
                            echo "<a href='{$file_url}' class='btn-view-attachment' target='_blank' title='View {$file_label} file'><i class='fas fa-file-alt'></i> {$file_label}</a> ";
                        }
                    }
                    if (!$has_files) {
                        echo "<span class='text-muted'>No files</span>";
                    }
                    ?>
                </td>
                <td>
                    <span class="status-badge status-<?= $request['status'] ?>">
                        <?= ucfirst($request['status']) ?>
                    </span>
                </td>
                <td class="action-buttons">
                    <button class="btn-action btn-edit" onclick="openEditModal(<?= $request['id'] ?>)" title="Edit Request">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </button>
                    <?php if((is_admin() || is_superadmin())): ?>
                    <button class="btn-action btn-reschedule" onclick="openRescheduleModal(<?= $request['id'] ?>)" title="Reschedule Request">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Reschedule</span>
                    </button>
                    <?php endif; ?>
                    <button class="btn-action btn-delete" onclick="deleteRequest(<?= $request['id'] ?>, this)" title="Delete Request">
                        <i class="fas fa-trash"></i>
                        <span>Delete</span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>



        <tr id="emptyStateRow">
            <td colspan="14" class="text-center py-5">
                <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                <p class="text-muted mb-0">No training requests found</p>
            </td>
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
                            <select name="training_type" class="form-select" required>
                                <option value="">--Select One--</option>
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                            </select>
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
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Type <span class="text-danger">*</span></label>
                            <select name="training_type" id="edit_training_type" class="form-select" required>
                                <option value="">--Select One--</option>
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Training Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_start" id="edit_date_start" required  disabled style="background-color: #babdc1;">
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
                        <?php endif; ?>















                         <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        
                        <!-- Attachments Section -->
                        <div class="col-12">
                            <h6 class="mt-3 mb-3"><i class="fas fa-paperclip me-2"></i>Attachments</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">PTR (Post Training Report)</label>
                                    <input type="file" class="form-control" name="ptr_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_ptr" class="current-file"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">COC (Certificate of Completion)</label>
                                    <input type="file" class="form-control" name="coc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_coc" class="current-file"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">COA (Certificate of Attendance)</label>
                                    <input type="file" class="form-control" name="coa_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div id="current_coa" class="current-file"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">MOM (Minutes of the Meeting)</label>
                                    <input type="file" class="form-control" name="mom_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
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
                
                // Inside the add_request_ajax success handler, update the newRow creation
const newRow = document.createElement('tr');
newRow.setAttribute('data-id', data.request.id);
newRow.setAttribute('data-title', data.request.title.toLowerCase());
newRow.setAttribute('data-type', data.request.training_type.toLowerCase());
newRow.setAttribute('data-order', data.request.hospital_order_no.toLowerCase());
newRow.setAttribute('data-remarks', (data.request.remarks || '').toLowerCase());
newRow.setAttribute('data-resched', '');

// Create attachments HTML
let attachmentsHtml = '<span class="text-muted">No files</span>';

newRow.innerHTML = `
    <td>${data.request.id}</td>
    <td><strong>${escapeHtml(data.request.title)}</strong></td>
    <td>
        <span class="badge ${data.request.training_type === 'Internal' ? 'badge-info' : 'badge-warning'}">
            ${data.request.training_type}
        </span>
    </td>
    <td>${startDateFormatted}</td>
    <td>${endDateFormatted}</td>
    <td>${escapeHtml(data.request.requester_name)}</td>
    <td>${escapeHtml(data.request.hospital_order_no)}</td>
    <td>₱${parseFloat(data.request.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
    <td>
        ${data.request.official_business ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}
    </td>
    <td class="truncated-cell" title="${escapeHtml(data.request.remarks)}">
        ${data.request.remarks.length > 30 ? escapeHtml(data.request.remarks.substring(0, 30)) + '...' : escapeHtml(data.request.remarks)}
    </td>
    <td class="truncated-cell" title="">—</td>
    <td class="attachment-buttons">
        ${attachmentsHtml}
    </td>
    <td>
        <span class="status-badge status-pending">Pending</span>
    </td>
    <td class="action-buttons">
        <button class="btn-action btn-edit" onclick="openEditModal(${data.request.id})">
            <i class="fas fa-edit"></i> Edit
        </button>
        <button class="btn-action btn-reschedule" onclick="openRescheduleModal(${data.request.id})">
            <i class="fas fa-calendar-alt"></i> Reschedule
        </button>
        <button class="btn-action btn-delete" onclick="deleteRequest(${data.request.id}, this)">
            <i class="fas fa-trash"></i> Delete
        </button>
    </td>
`;
                
                tableBody.insertBefore(newRow, tableBody.firstChild);
                
                // Update total count
                const totalCountSpan = document.getElementById('totalCount');
                const currentTotal = parseInt(totalCountSpan.textContent) || 0;
                totalCountSpan.textContent = currentTotal + 1;
                
                // Update statistics
                const internalSpan = document.querySelector('.stat-item:nth-child(2) .stat-number');
                const externalSpan = document.querySelector('.stat-item:nth-child(3) .stat-number');
                if (data.request.training_type === 'Internal') {
                    internalSpan.textContent = parseInt(internalSpan.textContent) + 1;
                } else {
                    externalSpan.textContent = parseInt(externalSpan.textContent) + 1;
                }
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('trainingRequestModal'));
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
                document.getElementById('edit_training_type').value = request.training_type;
                document.getElementById('edit_title').value = request.title;
                document.getElementById('edit_date_start').value = request.date_start;
                document.getElementById('edit_date_end').value = request.date_end;
                document.getElementById('edit_location_type').value = request.location_type || '';
                document.getElementById('edit_hospital_id').value = request.hospital_order_no;
                document.getElementById('edit_amount').value = request.amount;
                
                // Only set checkbox values if elements exist (admin/superadmin only)
                const lateFilingCheckbox = document.getElementById('edit_late_filing');
                const officialBusinessCheckbox = document.getElementById('edit_official_business');
                
                if (lateFilingCheckbox) {
                    lateFilingCheckbox.checked = request.late_filing == 1;
                }
                if (officialBusinessCheckbox) {
                    officialBusinessCheckbox.checked = request.official_business == 1;
                }
                
                document.getElementById('edit_remarks').value = request.remarks || '';
                
                // Show current files
                if (request.ptr_file) {
                    document.getElementById('current_ptr').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.ptr_file}" target="_blank">Current PTR File</a>`;
                } else {
                    document.getElementById('current_ptr').innerHTML = '';
                }
                if (request.coc_file) {
                    document.getElementById('current_coc').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.coc_file}" target="_blank">Current COC File</a>`;
                } else {
                    document.getElementById('current_coc').innerHTML = '';
                }
                if (request.coa_file) {
                    document.getElementById('current_coa').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.coa_file}" target="_blank">Current COA File</a>`;
                } else {
                    document.getElementById('current_coa').innerHTML = '';
                }
                if (request.mom_file) {
                    document.getElementById('current_mom').innerHTML = `<a href="<?= BASE_URL ?>/uploads/training/${request.mom_file}" target="_blank">Current MOM File</a>`;
                } else {
                    document.getElementById('current_mom').innerHTML = '';
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
    const tableBody = document.getElementById('trainingTableBody');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            const rows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
            
            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const type = row.getAttribute('data-type') || '';
                const order = row.getAttribute('data-order') || '';
                const remarks = row.getAttribute('data-remarks') || '';
                const resched = row.getAttribute('data-resched') || '';
                
                if (title.includes(searchTerm) || 
                    type.includes(searchTerm) || 
                    order.includes(searchTerm) || 
                    remarks.includes(searchTerm) ||
                    resched.includes(searchTerm) ||
                    searchTerm === '') {
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
    </td>
                `;
                tableBody.appendChild(emptyRow);
            } else if (visibleCount === 0 && rows.length > 0 && !document.querySelector('.no-results-row')) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="14" class="text-center py-5">
        <i class="fas fa-search fa-2x mb-2" style="color: #dee2e6;"></i>
        <p class="text-muted mb-0">No matching training requests found</p>
    </td>
                `;
                tableBody.appendChild(noResultsRow);
            } else if (visibleCount > 0) {
                const noResultsRow = tableBody.querySelector('.no-results-row');
                if (noResultsRow) noResultsRow.remove();
            }
        });
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
                if (trainingType === 'Internal') {
                    const internalSpan = document.querySelector('.stat-item:nth-child(2) .stat-number');
                    internalSpan.textContent = parseInt(internalSpan.textContent) - 1;
                } else if (trainingType === 'External') {
                    const externalSpan = document.querySelector('.stat-item:nth-child(3) .stat-number');
                    externalSpan.textContent = parseInt(externalSpan.textContent) - 1;
                }
                
                const remainingRows = tableBody.querySelectorAll('tr:not(#emptyStateRow)');
                if (remainingRows.length === 0 && !document.getElementById('emptyStateRow')) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.id = 'emptyStateRow';
                    emptyRow.innerHTML = `
                        <td colspan="13" class="text-center py-5">
                            <i class="fas fa-inbox fa-2x mb-2" style="color: #dee2e6;"></i>
                            <p class="text-muted mb-0">No training requests found</p>
                         </td>
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
</script>

</body>
</html>