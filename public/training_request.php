<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();



function getDbConnection() {
    global $conn;
    if (!isset($conn) || $conn === null) {
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'lms_db';
        
        $new_conn = new mysqli($host, $user, $pass, $dbname);
        
        if ($new_conn->connect_error) {
            die("Connection failed: " . $new_conn->connect_error);
        }
        
        $new_conn->set_charset("utf8mb4");
        return $new_conn;
    }
    
    if (!$conn->ping()) {
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'lms_db';
        
        $conn = new mysqli($host, $user, $pass, $dbname);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Get the database connection
$db = getDbConnection();

// SIMPLE FIX: Set a default user ID
$user_check = $db->query("SELECT id FROM users LIMIT 1");
if ($user_check && $user_check->num_rows > 0) {
    $user_row = $user_check->fetch_assoc();
    $default_user_id = $user_row['id'];
} else {
    $default_user_id = 1;
    $create_user = "INSERT INTO users (id, username) VALUES (1, 'admin') ON DUPLICATE KEY UPDATE id=id";
    $db->query($create_user);
}

$current_user_id = $_SESSION['user_id'] ?? 0;

if ($current_user_id > 0) {
    $verify = $db->prepare("SELECT id FROM users WHERE id = ?");
    $verify->bind_param("i", $current_user_id);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows == 0) {
        $current_user_id = $default_user_id;
    }
} else {
    $current_user_id = $default_user_id;
}

// Initialize variables
$title = $date_start = $date_end = $hospital_id = $amount = $remarks = '';
$training_type = '';
$location_type = '';
$late_filing = 0;
$official_business = 0;
$success_message = '';
$error_message = '';
$filter_month = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
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
        if (empty($date_start)) $errors[] = "Start date is required";
        if (empty($date_end)) $errors[] = "End date is required";
        
        if (!empty($date_start) && !empty($date_end)) {
            if (strtotime($date_end) < strtotime($date_start)) {
                $errors[] = "End date cannot be earlier than start date";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(", ", $errors));
        }
        
        $requester_id = $current_user_id;
        
        $stmt = $db->prepare("INSERT INTO training_requests (
            training_type, title, date_start, date_end, location_type, 
            hospital_order_no, amount, late_filing, official_business, 
            remarks, requester_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        if ($stmt === false) {
            throw new Exception('Database prepare failed: ' . $db->error);
        }
        
        $stmt->bind_param(
            "ssssssdisis",
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
        );
        
        if ($stmt->execute()) {
            $success_message = "Training request submitted successfully!";
            $title = $date_start = $date_end = $hospital_id = $amount = $remarks = '';
            $training_type = $location_type = '';
            $late_filing = $official_business = 0;
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle filter
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : '';
$where_clause = "";
$params = [];
$types = "";

if (!empty($filter_month) && $filter_month >= 1 && $filter_month <= 12) {
    $where_clause = "WHERE MONTH(date_start) = ? AND YEAR(date_start) = YEAR(CURDATE())";
    $params[] = $filter_month;
    $types .= "i";
}

// Fetch training requests
$query = "SELECT 
    tr.*,
    COALESCE(u.username, 'Unknown') as requester_name
    FROM training_requests tr
    LEFT JOIN users u ON tr.requester_id = u.id
    $where_clause
    ORDER BY tr.created_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$training_requests = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN training_type = 'Internal' THEN 1 ELSE 0 END) as internal_count,
    SUM(CASE WHEN training_type = 'External' THEN 1 ELSE 0 END) as external_count,
    COALESCE(SUM(amount), 0) as total_amount
    FROM training_requests
    WHERE YEAR(date_start) = YEAR(CURDATE())";
$stats_result = $db->query($stats_query);

if (!$stats_result) {
    $stats = ['total' => 0, 'internal_count' => 0, 'external_count' => 0, 'total_amount' => 0];
} else {
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Request Management - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 20px;
        }

        /* Sidebar adjustment */
        .lms-sidebar-container {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            z-index: 1000;
        }

        /* Main container - LEFT ALIGNED */
        .main-content {
            margin-left: 270px;
            padding: 0;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0;
            padding: 0 20px;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .container-fluid {
                padding: 0 15px;
            }
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
            width: 100%;
        }

        .form-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }

        .form-card-header h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a2634;
        }

        .form-card-header h4 i {
            color: #0d6efd;
            margin-right: 8px;
        }

        .form-card-body {
            padding: 24px;
        }

        /* Form Elements */
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }

        .form-check {
            margin-right: 24px;
        }

        .form-check-input {
            cursor: pointer;
        }

        .form-check-label {
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 6px;
        }

        /* Buttons */
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #0d6efd;
            border: none;
        }

        .btn-primary:hover {
            background: #0b5ed7;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
        }

        .btn-secondary:hover {
            background: #5c636a;
        }

        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        /* Stats Row - Text Only with Dividers */
        .stats-row {
            background: transparent;
            padding: 0;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0;
        }

        .stat-item {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 8px 20px;
        }

        .stat-item:not(:last-child) {
            border-right: 1px solid #dee2e6;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .stat-number {
            color: #1a2634;
            font-weight: 600;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .stats-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 8px 0;
            }
            
            .stat-item {
                padding: 8px 16px;
                width: 100%;
            }
            
            .stat-item:not(:last-child) {
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            width: 100%;
        }

        .table-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .table-card-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1a2634;
        }

        .table-card-header h4 i {
            color: #0d6efd;
            margin-right: 8px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            margin: 0;
            font-size: 0.85rem;
            width: 100%;
        }

        .table thead th {
            background: #f8f9fa;
            padding: 12px 16px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.7rem;
        }

        .badge-info {
            background: #e3f2fd;
            color: #0d6efd;
        }

        .badge-warning {
            background: #fff3e0;
            color: #fd7e14;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-secondary {
            background: #e9ecef;
            color: #6c757d;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-rejected {
            background: #f8d7da;
            color: #842029;
        }

        /* Action Buttons */
        .btn-icon {
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
        }

        .btn-icon i {
            font-size: 0.8rem;
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            
            .form-card-body {
                padding: 16px;
            }
            
            .btn {
                padding: 6px 16px;
                margin-bottom: 8px;
            }
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
        
        <!-- Training Request Form -->
        <div class="form-card">
            <div class="form-card-header">
                <h4><i class="fas fa-calendar-alt"></i> Training Request</h4>
            </div>
            <div class="form-card-body">
                <form method="POST" action="" id="trainingForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Type <span class="text-danger">*</span></label>
                            <select name="training_type" class="form-select" required>
                                <option value="">--Select One--</option>
                                <option value="Internal" <?= ($training_type == 'Internal') ? 'selected' : '' ?>>Internal</option>
                                <option value="External" <?= ($training_type == 'External') ? 'selected' : '' ?>>External</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Training Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($title) ?>" 
                                   required placeholder="Enter training title">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_start" value="<?= htmlspecialchars($date_start) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_end" value="<?= htmlspecialchars($date_end) ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Location Type</label>
                            <select name="location_type" class="form-select">
                                <option value="">--Select One--</option>
                                <option value="local" <?= ($location_type == 'local') ? 'selected' : '' ?>>Local</option>
                                <option value="international" <?= ($location_type == 'international') ? 'selected' : '' ?>>International</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Hospital Order No. <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="hospital_id" value="<?= htmlspecialchars($hospital_id) ?>" 
                                    placeholder="e.g., HOSP-2024-001">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Amount (PHP)</label>
                            <input type="number" class="form-control" name="amount" value="<?= htmlspecialchars($amount) ?>" 
                                   step="0.01" placeholder="0.00">
                        </div>
                        
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="late_filing" name="late_filing" value="1" <?= $late_filing ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="late_filing">Late Filing</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="official_business" name="official_business" value="1" <?= $official_business ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="official_business">Official Business</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" placeholder="Additional remarks..."><?= htmlspecialchars($remarks) ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" name="submit_request" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Submit Request
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-eraser me-1"></i> Clear Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label">Filter by Month</label>
                    <select name="filter_month" class="form-select">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Statistics - Text Only with Dividers -->
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-label">Total Training Requests:</span>
                <span class="stat-number"><?= number_format($stats['total'] ?? 0) ?></span>
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
                <table class="table">
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
                            <th>Status</th>
                            <th>Action</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($training_requests) && $training_requests->num_rows > 0): ?>
                            <?php while ($request = $training_requests->fetch_assoc()): ?>
                                <tr>
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
                                    <td><?= htmlspecialchars(substr($request['remarks'] ?? '', 0, 40)) ?><?= strlen($request['remarks'] ?? '') > 40 ? '...' : '' ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $request['status'] ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-icon" onclick="viewRequest(<?= $request['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (is_admin() || is_superadmin()): ?>
                                            <button class="btn btn-sm btn-warning btn-icon" onclick="editRequest(<?= $request['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Date validation
    document.getElementById('trainingForm').addEventListener('submit', function(e) {
        const startDate = document.querySelector('[name="date_start"]').value;
        const endDate = document.querySelector('[name="date_end"]').value;
        
        if (startDate && endDate) {
            if (new Date(endDate) < new Date(startDate)) {
                e.preventDefault();
                alert('End date cannot be earlier than start date');
                return false;
            }
        }
    });
    
    function viewRequest(id) {
        window.location.href = 'view_training.php?id=' + id;
    }
    
    function editRequest(id) {
        window.location.href = 'edit_training.php?id=' + id;
    }
</script>

</body>
</html>