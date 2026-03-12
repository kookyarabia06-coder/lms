<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Check if user is superadmin
if (!is_superadmin() && !is_admin()) {
    echo '<div class="alert alert-danger m-4">Authorized Access Only</div>';
    exit;
}

// Check if audit_log table exists
try {
    $pdo->query("SELECT 1 FROM audit_log LIMIT 1");
    $audit_table_exists = true;
} catch (Exception $e) {
    $audit_table_exists = false;
}

// Set current user ID for triggers
if ($audit_table_exists) {
    $pdo->exec("SET @current_user_id = " . intval($_SESSION['user']['id']));
}

// Get all course activities with audit trail and expand changes into individual rows
if ($audit_table_exists) {
    // First get all audit records including DELETEs
    $sql = "
    SELECT 
        c.id as course_id,
        c.title,
        c.description,
        c.created_at,
        c.updated_at,
        c.expires_at,
        u.id as creator_id,
        u.username as creator_username,
        u.fname as creator_fname,
        u.lname as creator_lname,
        u.role as creator_role,
        a.id as audit_id,
        a.old_data,
        a.new_data,
        a.changed_fields,
        a.action as audit_action,
        a.created_at as audit_time,
        a.user_id as editor_id,
        a.record_id,  /* Add record_id from audit_log */
        au.username as editor_username,
        au.fname as editor_fname,
        au.lname as editor_lname,
        au.role as editor_role
    FROM audit_log a
    LEFT JOIN courses c ON a.record_id = c.id AND a.table_name = 'courses'
    LEFT JOIN users u ON c.proponent_id = u.id
    LEFT JOIN users au ON a.user_id = au.id
    WHERE a.table_name = 'courses'
    AND a.action IN ('INSERT', 'UPDATE', 'DELETE')
    
    UNION
    
    -- Also include current courses that might not have audit records
    SELECT 
        c.id as course_id,
        c.title,
        c.description,
        c.created_at,
        c.updated_at,
        c.expires_at,
        u.id as creator_id,
        u.username as creator_username,
        u.fname as creator_fname,
        u.lname as creator_lname,
        u.role as creator_role,
        NULL as audit_id,
        NULL as old_data,
        NULL as new_data,
        NULL as changed_fields,
        NULL as audit_action,
        NULL as audit_time,
        NULL as editor_id,
        NULL as record_id,  /* Add NULL record_id for non-audit records */
        NULL as editor_username,
        NULL as editor_fname,
        NULL as editor_lname,
        NULL as editor_role
    FROM courses c
    LEFT JOIN users u ON c.proponent_id = u.id
    
    ORDER BY audit_time DESC, updated_at DESC, created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    // Fallback query without audit_log (limited functionality)
    $sql = "
    SELECT 
        c.id as course_id,
        c.title,
        c.description,
        c.created_at,
        c.updated_at,
        c.expires_at,
        c.proponent_id,
        u.id as creator_id,
        u.username as creator_username,
        u.fname as creator_fname,
        u.lname as creator_lname,
        u.role as creator_role,
        NULL as audit_id,
        NULL as old_data,
        NULL as new_data,
        NULL as changed_fields,
        NULL as audit_action,
        NULL as audit_time,
        NULL as editor_id,
        NULL as record_id,
        NULL as editor_username,
        NULL as editor_fname,
        NULL as editor_lname,
        NULL as editor_role
    FROM courses c
    LEFT JOIN users u ON c.proponent_id = u.id
    ORDER BY c.updated_at DESC, c.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

$raw_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expand records to show each field change as a separate row
$expanded_actions = [];

foreach ($raw_records as $record) {
    // Handle DELETE actions specially
    if (isset($record['audit_action']) && $record['audit_action'] == 'DELETE') {
        // Parse old_data for deleted course info
        $old = json_decode($record['old_data'], true);
        
        $expanded_record = $record;
        $expanded_record['changed_field'] = 'course';
        $expanded_record['old_value'] = $old['title'] ?? 'Unknown course';
        $expanded_record['new_value'] = 'DELETED';
        $expanded_record['action_type'] = 'DELETED';
        $expanded_record['title'] = $old['title'] ?? 'Deleted Course';
        $expanded_record['course_id'] = $record['record_id'] ?? $record['course_id'] ?? 'N/A';
        
        $expanded_actions[] = $expanded_record;
    }
    // Handle regular records with field changes
    elseif ($audit_table_exists && !empty($record['old_data']) && !empty($record['new_data']) && !empty($record['changed_fields'])) {
        // Parse JSON data
        $old = json_decode($record['old_data'], true);
        $new = json_decode($record['new_data'], true);
        $fields = json_decode($record['changed_fields'], true);
        
        if (is_array($fields) && !empty($fields)) {
            // Create a separate row for each changed field
            foreach ($fields as $field) {
                $old_val = $old[$field] ?? 'NULL';
                $new_val = $new[$field] ?? 'NULL';
                
                // Format based on field type
                if (in_array($field, ['thumbnail', 'file_pdf', 'file_video'])) {
                    $old_display = $old_val != 'NULL' ? basename($old_val) : 'NULL';
                    $new_display = $new_val != 'NULL' ? basename($new_val) : 'NULL';
                } else {
                    $old_display = $old_val != 'NULL' ? substr($old_val, 0, 50) : 'NULL';
                    $new_display = $new_val != 'NULL' ? substr($new_val, 0, 50) : 'NULL';
                }
                
                $expanded_record = $record;
                $expanded_record['changed_field'] = $field;
                $expanded_record['old_value'] = $old_display;
                $expanded_record['new_value'] = $new_display;
                $expanded_record['action_type'] = $record['audit_action'] == 'INSERT' ? 'ADDED' : 'EDITED';
                
                $expanded_actions[] = $expanded_record;
            }
        } else {
            // If no fields data but we have an audit record
            $expanded_record = $record;
            $expanded_record['changed_field'] = 'unknown';
            $expanded_record['old_value'] = 'No data';
            $expanded_record['new_value'] = 'No data';
            $expanded_record['action_type'] = $record['audit_action'] == 'INSERT' ? 'ADDED' : 'EDITED';
            
            $expanded_actions[] = $expanded_record;
        }
    } else {
        // For records without audit data (fallback or INSERT without field details)
        $expanded_record = $record;
        $expanded_record['changed_field'] = 'course';
        $expanded_record['old_value'] = 'N/A';
        $expanded_record['new_value'] = 'Course ' . (($record['audit_action'] ?? '') == 'INSERT' ? 'created' : 'exists');
        $expanded_record['action_type'] = isset($record['audit_action']) ? 
            ($record['audit_action'] == 'INSERT' ? 'ADDED' : 'EDITED') : 
            (isset($record['updated_at']) && $record['updated_at'] != $record['created_at'] ? 'EDITED' : 'ADDED');
        
        $expanded_actions[] = $expanded_record;
    }
}

// Sort by audit time descending
usort($expanded_actions, function($a, $b) {
    $time_a = $a['audit_time'] ?? $a['updated_at'] ?? $a['created_at'] ?? '1970-01-01';
    $time_b = $b['audit_time'] ?? $b['updated_at'] ?? $b['created_at'] ?? '1970-01-01';
    return strtotime($time_b) - strtotime($time_a);
});

$course_actions = $expanded_actions;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Audit Trail - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
<link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">

<style>
    body {
        background: #f4f6f9;
    }
    
    .main-content-wrapper {
        margin-left: 280px;
        padding: 20px;
    }
    
    /* Card Styles */
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 20px;
    }
    
    .card-header {
        background: white;
        border-bottom: 2px solid #f0f0f0;
        padding: 15px 20px;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }
    
    /* Badge Styles */
    .action-badge {
        padding: 4px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .action-added {
        background: #28a745;
        color: white;
        border: none;
    }
    
    .action-edited {
        background: #ffc107;
        color: #000;
        border: none;
    }
    
    .action-deleted {
        background: #dc3545;
        color: white;
        border: none;
    }
    
    /* Table Styles */
    .table {
        table-layout: fixed;
        width: 100%;
    }
    
    .table th {
        background: #f8f9fa;
        border-top: none;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        padding: 12px 4px;
        color: #495057;
        text-align: center;
    }
    
    .table td {
        font-size: 12px;
        vertical-align: middle;
        padding: 10px 4px;
        border-top: 1px solid #dee2e6;
        word-wrap: break-word;
    }
    
    /* Center all columns by default */
    .table td {
        text-align: center;
    }
    
    /* Left-align course column */
    .table td:nth-child(2) {
        text-align: left;
    }
    
    /* Column width allocations - more compact */
    .table th:nth-child(1), .table td:nth-child(1) { width: 75px; }  /* Date/Time - smaller */
    .table th:nth-child(2), .table td:nth-child(2) { width: 160px; } /* Course */
    .table th:nth-child(3), .table td:nth-child(3) { width: 65px; }  /* Action */
    .table th:nth-child(4), .table td:nth-child(4) { width: 95px; }  /* Field Changed */
    .table th:nth-child(5), .table td:nth-child(5) { width: 140px; } /* Old Value */
    .table th:nth-child(6), .table td:nth-child(6) { width: 140px; } /* New Value */
    .table th:nth-child(7), .table td:nth-child(7) { width: 90px; }  /* User - reduced */
    .table th:nth-child(8), .table td:nth-child(8) { width: 65px; }  /* Role - reduced */
    
    /* Date/Time column - stacked layout without separator */
    .audit-time {
        font-size: 11px;
        color: #6c757d;
        line-height: 1.4;
        text-align: center;
        white-space: normal;
        word-break: keep-all;
    }
    
    .audit-date {
        font-weight: 600;
        color: #495057;
        display: block;
        font-size: 10px;
    }
    
    .audit-clock {
        display: block;
        font-size: 9px;
        color: #6c757d;
    }
    
    .audit-clock i {
        font-size: 8px;
        margin-right: 2px;
    }
    
    /* Course column */
    .table td:nth-child(2) {
        white-space: normal;
        word-break: break-word;
    }
    
    .table td:nth-child(2) strong {
        display: block;
        font-size: 12px;
        line-height: 1.3;
        white-space: normal;
        word-break: break-word;
    }
    
    /* Action column */
    .table td:nth-child(3) {
        text-align: center;
        vertical-align: middle;
    }
    
    /* Field badge */
    .field-badge {
        background: #e9ecef;
        color: #495057;
        padding: 3px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Role Badges - centered */
    .table td:nth-child(8) {
        text-align: center;
        vertical-align: middle;
    }
    
    .role-badge {
        padding: 4px 6px;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .role-superadmin {
        background: #6c5ce7;
        color: white;
    }
    
    .role-admin {
        background: #0984e3;
        color: white;
    }
    
    .role-proponent {
        background: #00b894;
        color: white;
    }
    
    .role-student {
        background: #fdcb6e;
        color: #2d3436;
    }
    
    /* User column - centered, no avatar, just username */
    .table td:nth-child(7) {
        text-align: center;
        vertical-align: middle;
    }
    
    .username-display {
        font-size: 11px;
        font-weight: 500;
        color: #495057;
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: 4px 0;
    }
    
    /* Value cells */
    .old-value,
    .new-value,
    .null-value {
        padding: 3px 6px;
        border-radius: 4px;
        font-size: 11px;
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .old-value {
        background: #fff3cd;
        color: #856404;
    }
    
    .new-value {
        background: #d4edda;
        color: #155724;
    }
    
    .new-value.deleted {
        background: #f8d7da;
        color: #721c24;
    }
    
    .null-value {
        color: #6c757d;
        font-style: italic;
        background: #f8f9fa;
    }
    
    /* Deleted course row styling */
    .deleted-course-row {
        background-color: #fff5f5;
    }
    
    .deleted-course-row:hover {
        background-color: #ffe8e8 !important;
    }
    
    /* Table Responsive */
    .table-responsive {
        max-height: 600px;
        overflow-y: auto;
        overflow-x: auto;
        border-radius: 0 0 12px 12px;
        position: relative;
    }
    
    /* Sticky Header */
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }
    
    /* Search Box */
    .search-box {
        margin-bottom: 20px;
        max-width: 300px;
    }
    
    .search-box .form-control {
        border-radius: 20px;
        border: 1px solid #dee2e6;
        padding: 8px 15px;
        font-size: 13px;
    }
    
    .search-box .form-control:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        border-color: #80bdff;
    }
    
    /* DB Warning */
    .db-warning {
        background: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    
    /* Arrow indicator */
    .arrow-indicator {
        color: #6c757d;
        margin: 0 5px;
        font-size: 12px;
    }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<!-- Main Content -->
<div class="main-content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>
                <i class="fas fa-history text-primary me-2"></i>
                Course Audit Trail
            </h4>
            <span class="badge bg-secondary"><?= count($course_actions) ?> field changes</span>
        </div>

        <?php if (!$audit_table_exists): ?>
        <div class="db-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Limited Functionality:</strong> audit_log table not found. Showing basic course data only.
            <button class="btn btn-sm btn-warning ms-3" onclick="document.getElementById('sqlScript').style.display='block'">Show Setup SQL</button>
            <div id="sqlScript" style="display: none; margin-top: 15px;">
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 11px;">
-- Run this SQL to create audit_log table and triggers
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_data` longtext DEFAULT NULL,
  `new_data` longtext DEFAULT NULL,
  `changed_fields` longtext DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `table_name` (`table_name`,`record_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create triggers (see original code for full trigger definitions)
                </pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- Simple Search -->
        <div class="search-box">
            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search by title, user, field...">
        </div>

        <!-- Audit Table -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list-alt me-2 text-primary"></i>Field-Level Audit Trail</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="auditTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Course</th>
                                <th>Action</th>
                                <th>Field Changed</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                                <th>User</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_actions as $course): ?>
                            <?php
                                // Determine who performed the action - use username only
                                if ($course['action_type'] == 'DELETED') {
                                    // For deleted courses, use the editor who deleted it
                                    $user_name = $course['editor_username'] ?? 'Unknown';
                                    $user_role = $course['editor_role'] ?? 'system';
                                }
                                elseif ($course['action_type'] == 'EDITED' && !empty($course['editor_username'])) {
                                    // This is an edit - show the editor who made the change
                                    $user_name = $course['editor_username'];
                                    $user_role = $course['editor_role'];
                                }
                                // For ADDED actions, show the creator
                                elseif ($course['action_type'] == 'ADDED' && !empty($course['creator_username'])) {
                                    $user_name = $course['creator_username'];
                                    $user_role = $course['creator_role'];
                                }
                                // Fallback to editor if available (for any other case)
                                elseif (!empty($course['editor_username'])) {
                                    $user_name = $course['editor_username'];
                                    $user_role = $course['editor_role'];
                                }
                                // Fallback to creator
                                elseif (!empty($course['creator_username'])) {
                                    $user_name = $course['creator_username'];
                                    $user_role = $course['creator_role'];
                                } else {
                                    $user_name = 'System';
                                    $user_role = 'system';
                                }

                                // Format date/time
                                $display_time = $course['audit_time'] ?? $course['updated_at'] ?? $course['created_at'] ?? '';
                                
                                // Role badge class
                                $role_class = 'role-' . strtolower(str_replace(' ', '', $user_role));
                                
                                // Format field name for display
                                $field_display = $course['action_type'] == 'DELETED' ? 'Course Deletion' : str_replace('_', ' ', ucfirst($course['changed_field'] ?? 'course'));
                                
                                // Check if values are NULL
                                $is_old_null = ($course['old_value'] == 'NULL' || $course['old_value'] == 'N/A' || $course['old_value'] == 'No data');
                                $is_new_null = ($course['new_value'] == 'NULL' || $course['new_value'] == 'N/A' || $course['new_value'] == 'No data');
                                
                                // Add special class for deleted rows
                                $row_class = $course['action_type'] == 'DELETED' ? 'deleted-course-row' : '';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="audit-time">
                                    <?php if ($display_time): 
                                        $timestamp = strtotime($display_time);
                                        $date = date('M d, Y', $timestamp);
                                        $time = date('h:i A', $timestamp);
                                    ?>
                                        <span class="audit-date"><?= $date ?></span>
                                        <span class="audit-clock"><i class="far fa-clock"></i> <?= $time ?></span>
                                    <?php else: ?>
                                        <span class="audit-date">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($course['title'] ?: 'Untitled') ?></strong>
                                </td>
                                <td>
                                    <span class="action-badge action-<?= strtolower($course['action_type']) ?>">
                                        <?php if ($course['action_type'] == 'ADDED'): ?>
                                            <i class="fas fa-plus-circle me-1"></i>Added
                                        <?php elseif ($course['action_type'] == 'EDITED'): ?>
                                            <i class="fas fa-edit me-1"></i>Edited
                                        <?php elseif ($course['action_type'] == 'DELETED'): ?>
                                            <i class="fas fa-trash-alt me-1"></i>Deleted
                                        <?php else: ?>
                                            <i class="fas fa-eye me-1"></i><?= $course['action_type'] ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="field-badge">
                                        <i class="fas fa-tag me-1"></i><?= htmlspecialchars($field_display) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_old_null): ?>
                                        <span class="null-value"><?= htmlspecialchars($course['old_value']) ?></span>
                                    <?php else: ?>
                                        <span class="old-value"><?= htmlspecialchars($course['old_value']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($course['action_type'] == 'DELETED'): ?>
                                        <span class="new-value deleted">
                                            <i class="fas fa-trash-alt me-1"></i><?= htmlspecialchars($course['new_value']) ?>
                                        </span>
                                    <?php elseif ($is_new_null): ?>
                                        <span class="null-value"><?= htmlspecialchars($course['new_value']) ?></span>
                                    <?php else: ?>
                                        <span class="new-value"><?= htmlspecialchars($course['new_value']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="username-display" title="<?= htmlspecialchars($user_name) ?>">
                                        <?= htmlspecialchars($user_name) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user_role && $user_role != 'system'): ?>
                                    <span class="role-badge <?= $role_class ?>">
                                        <?= ucfirst(htmlspecialchars($user_role)) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="role-badge bg-secondary text-white">System</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($course_actions)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-info-circle text-muted mb-2 fa-2x"></i>
                                    <p class="text-muted">No course activities found</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple client-side search
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('auditTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        let found = false;
        const cells = row.getElementsByTagName('td');
        
        for (let cell of cells) {
            if (cell.textContent.toLowerCase().includes(searchTerm)) {
                found = true;
                break;
            }
        }
        
        row.style.display = found ? '' : 'none';
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>