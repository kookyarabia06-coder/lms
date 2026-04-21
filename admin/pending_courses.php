<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';
require_login();

// Only admin and superadmin can access this page
if (!is_admin() && !is_superadmin()) {
    echo 'Admin only';
    exit;
}

// Handle approve action
if (isset($_GET['approve']) && isset($_GET['id'])) {
    $course_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE courses SET status = 'approve', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$course_id]);
        
        // Get course details for notification
        $stmt = $pdo->prepare("SELECT title, proponent_id FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if ($course) {
            // Get proponent email
            $stmt = $pdo->prepare("SELECT email, fname, lname FROM users WHERE id = ?");
            $stmt->execute([$course['proponent_id']]);
            $proponent = $stmt->fetch();
            
            if ($proponent && function_exists('sendCourseApprovalEmail')) {
                $fullName = $proponent['fname'] . ' ' . $proponent['lname'];
                sendCourseApprovalEmail($proponent['email'], $fullName, $course['title'], 'approved');
            }
        }
        
        $_SESSION['success_message'] = "Course '{$course['title']}' has been approved.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error approving course: " . $e->getMessage();
    }
    
    header('Location: pending_courses.php');
    exit;
}

// Handle reject action
if (isset($_GET['reject']) && isset($_GET['id'])) {
    $course_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE courses SET status = 'reject', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$course_id]);
        
        // Get course details for notification
        $stmt = $pdo->prepare("SELECT title, proponent_id FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if ($course) {
            // Get proponent email
            $stmt = $pdo->prepare("SELECT email, fname, lname FROM users WHERE id = ?");
            $stmt->execute([$course['proponent_id']]);
            $proponent = $stmt->fetch();
            
            if ($proponent && function_exists('sendCourseApprovalEmail')) {
                $fullName = $proponent['fname'] . ' ' . $proponent['lname'];
                sendCourseApprovalEmail($proponent['email'], $fullName, $course['title'], 'rejected');
            }
        }
        
        $_SESSION['success_message'] = "Course '{$course['title']}' has been rejected.";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error rejecting course: " . $e->getMessage();
    }
    
    header('Location: pending_courses.php');
    exit;
}

// Fetch pending courses
$pendingCourses = $pdo->query("
    SELECT c.*, u.username, u.fname, u.lname, u.email 
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    WHERE c.status = 'pending' 
    ORDER BY c.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved courses
$approvedCourses = $pdo->query("
    SELECT c.*, u.username, u.fname, u.lname, u.email 
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    WHERE c.status = 'approve' 
    ORDER BY c.updated_at DESC, c.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch rejected courses
$rejectedCourses = $pdo->query("
    SELECT c.*, u.username, u.fname, u.lname, u.email 
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    WHERE c.status = 'reject' 
    ORDER BY c.updated_at DESC, c.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Approval Panel - LMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #1e293b;
            line-height: 1.5;
        }

        /* Sidebar Container */
        .lms-sidebar-container {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        /* Card Styles */
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 15px 20px;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-danger {
            background-color: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 1.125rem;
        }

        .btn-close {
            margin-left: auto;
            background: transparent;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-header h3 i {
            color: #3b82f6;
            font-size: 1.125rem;
        }

        .badge-count {
            background: #e2e8f0;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-bottom: 2rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .table thead {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f8f9fa;
            border-top: none;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
        }

        .table tbody tr:hover {
            background-color: #fafcff;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-actions a {
            margin-right: 5px;
        }

        /* Course Title */
        .course-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .course-description {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* User Info */
        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: #0f172a;
        }

        .user-username {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Committee Tags */
        .committee-tag {
            display: inline-block;
            background: #eef2ff;
            color: #4338ca;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.125rem;
        }

        /* Badges - Using the exact colors from reference */
        .badge-pending {
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 10px;
            display: inline-block;
            font-weight: 500;
        }

        .badge-confirmed {
            background: #198754;
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 10px;
            display: inline-block;
            font-weight: 500;
        }

        /* Status Badges - Keep for compatibility */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background: #ffc107;
            color: #000;
        }

        .status-approved {
            background: #198754;
            color: white;
        }

        .status-rejected {
            background: #dc3545;
            color: white;
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-indicator.status-pending {
            background: #ffc107;
        }

        .status-indicator.status-confirmed {
            background: #28a745;
        }

        /* Action Buttons - Matching reference image style */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: nowrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.375rem 0.875rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            white-space: nowrap;
            cursor: pointer;
            line-height: 1.2;
        }

        .btn-action i {
            font-size: 0.75rem;
        }

        .btn-approve {
            background: #198754;
            color: white;
        }

        .btn-approve:hover {
            background: #157347;
            color: white;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            color: white;
            transform: translateY(-1px);
        }

        .btn-view {
            background: #6c757d;
            color: white;
        }

        .btn-view:hover {
            background: #5a6268;
            color: white;
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
            text-align: center;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.875rem;
            margin: 0;
            text-align: center;
        }

        /* Table cell alignment */
        .table td:last-child {
            text-align: left;
            vertical-align: middle;
        }

        .table td.empty-state {
            text-align: center;
        }

        /* Fixed Table Layout */
        .fixed-table {
            table-layout: fixed;
            width: 100%;
        }

        /* Set all data text to 13px */
        .fixed-table td,
        .fixed-table-pending td,
        .table td,
        .status-indicator,
        .card-header,
        .table th,
        .table-actions a {
            font-size: 13px;
        }

        /* Fixed Table Column Widths */
        .fixed-table td:nth-child(1) {
            width: 30%;
        }

        .fixed-table th:nth-child(1) {
            width: 5%;
        }

        .fixed-table th:nth-child(2) {
            width: 10%;
        }

        .fixed-table th:nth-child(3) {
            width: 23%;
        }

        .fixed-table th:nth-child(4) {
            width: 23%;
        }

        .fixed-table th:nth-child(5) {
            width: 15%;
        }

        .fixed-table th:nth-child(6) {
            width: 10%;
        }

        .fixed-table th:nth-child(7) {
            width: 10%;
        }

        .fixed-table th:nth-child(8) {
            width: 10%;
        }

        .fixed-table th:nth-child(9) {
            width: 20%;
        }

        /* Text overflow handling */
        .fixed-table td,
        .fixed-table-pending td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 0;
        }

        /* Special handling for the department column */
        .fixed-table td:nth-child(4) {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            position: relative;
            cursor: help;
        }

        /* Ensure the badges inside department column also respect the overflow */
        .fixed-table td:nth-child(4) span.badge {
            display: inline-block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        /* Department Tooltip */
        .fixed-table td:nth-child(4):hover::before {
            content: attr(data-departments);
            position: fixed;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 10px;
            white-space: nowrap;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            pointer-events: none;
            top: var(--mouse-y, 50%);
            left: var(--mouse-x, 50%);
            transform: translate(-50%, -100%);
            margin-top: -10px;
        }

        .fixed-table td:nth-child(4):hover::after {
            content: '';
            position: fixed;
            border: 6px solid transparent;
            border-top-color: #333;
            z-index: 10000;
            pointer-events: none;
            top: var(--mouse-y, 50%);
            left: var(--mouse-x, 50%);
            transform: translate(-50%, -24px);
        }

        /* Pending Table Styles */
        .fixed-table-pending {
            table-layout: fixed;
            width: 100%;
        }

        /* Pending Table Column Widths */
        .fixed-table-pending th:nth-child(1),
        .fixed-table-pending td:nth-child(1) {
            width: 5%;
        }

        .fixed-table-pending td:nth-child(2) {
            width: 10%;
        }

        .fixed-table-pending td:nth-child(3) {
            width: 20%;
        }

        .fixed-table-pending td:nth-child(4) {
            width: 20%;
        }

        .fixed-table-pending td:nth-child(5) {
            width: 10%;
        }

        .fixed-table-pending td:nth-child(6) {
            width: 8%;
        }

        .fixed-table-pending td:nth-child(7) {
            width: 20%;
        }

        /* Fixed Height Tables with Internal Scrolling */
        .card-body.p-0 {
            max-height: 400px;
            overflow-y: auto;
            position: relative;
        }

        /* Different heights for each table */
        .card:first-of-type .card-body.p-0 {
            max-height: 300px;
        }

        .card:last-of-type .card-body.p-0 {
            max-height: 450px;
        }

        /* Keep table header sticky when scrolling */
        .card-body.p-0 table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }

        /* Ensure the department tooltip works with scrolling */
        .card-body.p-0 {
            position: relative;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .lms-sidebar-container {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                gap: 0.375rem;
            }
            
            .btn-action {
                flex: 1;
                min-width: 70px;
                padding: 0.375rem 0.5rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content">
    <div class="page-header">
        <h2>Course Approval Panel</h2>
    </div>
    
    <!-- Session Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Pending Courses Section -->
    <div class="section-header">
        <h3>
            <i class="fas fa-clock"></i>
            Pending Courses
        </h3>
        
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%">ID</th>
                    <th style="width: 25%">Course Title</th>
                    <th style="width: 15%">Created By</th>
                    <th style="width: 20%">Program Committee</th>
                    <th style="width: 10%">Created At</th>
                    <th style="width: 8%">Status</th>
                    <th style="width: 17%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingCourses)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No pending courses</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingCourses as $course): ?>
                        <?php
                        $comm_stmt = $pdo->prepare("
                            SELECT c.name 
                            FROM committees c
                            JOIN course_departments cd ON cd.committee_id = c.id
                            WHERE cd.course_id = ?
                        ");
                        $comm_stmt->execute([$course['id']]);
                        $committees = $comm_stmt->fetchAll();
                        ?>
                        <tr>
                            <td><?= $course['id'] ?></td>
                            <td>
                                <div class="course-title">
                                    <strong><?= htmlspecialchars($course['title']) ?></strong>
                                </div>
                                <div class="course-description">
                                    <?= htmlspecialchars(substr($course['description'], 0, 80)) ?>...
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name">
                                        <?= htmlspecialchars($course['fname'] ?? '') ?> <?= htmlspecialchars($course['lname'] ?? '') ?>
                                    </span>
                                    <span class="user-username">
                                        @<?= htmlspecialchars($course['username']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($committees)): ?>
                                    <?php foreach ($committees as $comm): ?>
                                        <span class="committee-tag"><?= htmlspecialchars($comm['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="committee-tag">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($course['created_at'])) ?></td>
                            <td>
                                <span class="badge-pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?approve=1&id=<?= $course['id'] ?>" 
                                       class="btn-action btn-approve"
                                       onclick="return confirm('Approve this course?')">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?reject=1&id=<?= $course['id'] ?>" 
                                       class="btn-action btn-reject"
                                       onclick="return confirm('Reject this course? This action cannot be undone.')">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                    <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $course['id'] ?>" 
                                       class="btn-action btn-view"
                                       target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Approved Courses Section -->
    <div class="section-header">
        <h3>
            <i class="fas fa-check-circle"></i>
            Recently Approved
        </h3>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course Title</th>
                    <th>Created By</th>
                    <th>Approved At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($approvedCourses)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No approved courses yet</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($approvedCourses as $course): ?>
                        <tr>
                            <td><?= $course['id'] ?></td>
                            <td>
                                <div class="course-title">
                                    <?= htmlspecialchars($course['title']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name">
                                        <?= htmlspecialchars($course['fname'] ?? '') ?> <?= htmlspecialchars($course['lname'] ?? '') ?>
                                    </span>
                                    <span class="user-username">
                                        @<?= htmlspecialchars($course['username']) ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= $course['updated_at'] ? date('M d, Y', strtotime($course['updated_at'])) : date('M d, Y', strtotime($course['created_at'])) ?></td>
                            <td>
                                <span class="badge-confirmed">Approved</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Rejected Courses Section -->
    <div class="section-header">
        <h3>
            <i class="fas fa-times-circle"></i>
            Recently Rejected
        </h3>
        
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course Title</th>
                    <th>Created By</th>
                    <th>Rejected At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rejectedCourses)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-times-circle"></i>
                            <p>No rejected courses</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rejectedCourses as $course): ?>
                        <tr>
                            <td><?= $course['id'] ?></td>
                            <td>
                                <div class="course-title">
                                    <?= htmlspecialchars($course['title']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name">
                                        <?= htmlspecialchars($course['fname'] ?? '') ?> <?= htmlspecialchars($course['lname'] ?? '') ?>
                                    </span>
                                    <span class="user-username">
                                        @<?= htmlspecialchars($course['username']) ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= $course['updated_at'] ? date('M d, Y', strtotime($course['updated_at'])) : date('M d, Y', strtotime($course['created_at'])) ?></td>
                            <td>
                                <span class="badge-confirmed" style="background: #dc3545;">Rejected</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        let alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
</script>

</body>
</html>