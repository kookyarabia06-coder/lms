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

// Get all course activities with audit trail
if ($audit_table_exists) {
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
        au.username as editor_username,
        au.fname as editor_fname,
        au.lname as editor_lname,
        au.role as editor_role,
        CASE 
            WHEN a.action = 'UPDATE' THEN 'EDITED'
            WHEN a.action = 'INSERT' THEN 'ADDED'
            WHEN a.action = 'DELETE' THEN 'DELETED'
            ELSE 'VIEWED'
        END as action_type
    FROM courses c
    LEFT JOIN users u ON c.proponent_id = u.id
    LEFT JOIN audit_log a ON a.table_name = 'courses' AND a.record_id = c.id
    LEFT JOIN users au ON a.user_id = au.id
    ORDER BY COALESCE(a.created_at, c.updated_at, c.created_at) DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    // Fallback query without audit_log
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
        NULL as old_data,
        NULL as new_data,
        NULL as changed_fields,
        NULL as audit_action,
        NULL as audit_time,
        NULL as editor_id,
        NULL as editor_username,
        NULL as editor_fname,
        NULL as editor_lname,
        NULL as editor_role,
        CASE 
            WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
            ELSE 'ADDED'
        END as action_type
    FROM courses c
    LEFT JOIN users u ON c.proponent_id = u.id
    ORDER BY c.updated_at DESC, c.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format changes nicely
function formatChanges($old_data, $new_data, $changed_fields) {
    if (!$old_data || !$new_data || !$changed_fields) {
        return 'No detailed changes';
    }
    
    $old = json_decode($old_data, true);
    $new = json_decode($new_data, true);
    $fields = json_decode($changed_fields, true);
    
    if (!is_array($fields) || empty($fields)) {
        return 'No field changes recorded';
    }
    
    $changes = [];
    foreach ($fields as $field) {
        $old_val = $old[$field] ?? 'NULL';
        $new_val = $new[$field] ?? 'NULL';
        
        // Format based on field type
        if (in_array($field, ['thumbnail', 'file_pdf', 'file_video'])) {
            if ($old_val != $new_val) {
                $changes[] = "$field: " . basename($old_val) . " → " . basename($new_val);
            }
        } else {
            if ($old_val != $new_val) {
                $changes[] = "$field: '" . substr($old_val, 0, 30) . "' → '" . substr($new_val, 0, 30) . "'";
            }
        }
    }
    
    return empty($changes) ? 'No visible changes' : implode('<br>', $changes);
}
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

<style>
    body {
        background: #f4f6f9;
    }
    
    .main-content-wrapper {
        margin-left: 280px;
        padding: 20px;
    }
    
    /* Card Styles - Updated to match second CSS */
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
    
    /* Badge Styles - Updated to match second CSS pattern */
    .action-badge {
        padding: 5px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
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
    
    /* Table Styles - Updated to match second CSS */
    .table th {
        background: #f8f9fa;
        border-top: none;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        padding: 12px 8px;
        color: #495057;
    }
    
    .table td {
        font-size: 13px;
        vertical-align: middle;
        padding: 12px 8px;
        border-top: 1px solid #dee2e6;
    }
    
    /* Status Indicator - New from second CSS */
    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 8px;
    }
    
    .status-pending {
        background: #ffc107;
    }
    
    .status-confirmed {
        background: #28a745;
    }
    
    .status-deleted {
        background: #dc3545;
    }
    
    .status-edited {
        background: #ffc107;
    }
    
    /* Role Badges - Updated colors to match second CSS pattern */
    .role-badge {
        padding: 5px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
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
    
    /* User Badge - Updated styling */
    .user-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8f9fa;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        border: 1px solid #dee2e6;
    }
    
    .user-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #6c757d;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    /* Changes Cell - Updated */
    .changes-cell {
        max-width: 300px;
        font-size: 12px;
        line-height: 1.5;
        color: #495057;
    }
    
    /* Audit Time - Updated */
    .audit-time {
        font-size: 11px;
        color: #6c757d;
        white-space: nowrap;
    }
    
    /* Table Responsive - Updated */
    .table-responsive {
        max-height: 500px;
        overflow-y: auto;
        border-radius: 0 0 12px 12px;
        position: relative;
    }
    
    /* Sticky Header - Updated to match second CSS */
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }
    
    /* Search Box - Updated */
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
    
    /* DB Warning - Updated */
    .db-warning {
        background: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    
    /* Stats Cards - New from second CSS pattern */
    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        display: inline-block;
        margin-bottom: 20px;
    }
    
    .stats-number {
        font-size: 24px;
        font-weight: bold;
        color: #343a40;
        line-height: 1.2;
    }
    
    .stats-label {
        font-size: 13px;
        color: #6c757d;
        margin-top: 5px;
    }
    
    /* Table Actions - New */
    .table-actions a {
        margin-right: 8px;
        font-size: 13px;
        color: #007bff;
        text-decoration: none;
    }
    
    .table-actions a:hover {
        text-decoration: underline;
    }
    
    .table-actions a.text-danger:hover {
        color: #bd2130 !important;
    }
    
    /* Badge variants - New */
    .badge-pending {
        background: #ffc107;
        color: #000;
        padding: 5px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .badge-confirmed {
        background: #28a745;
        color: white;
        padding: 5px 10px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
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
                Audit Trail
            </h4>
            <span class="badge bg-secondary"><?= count($course_actions) ?> total records</span>
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
            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search by title, user, action...">
        </div>

        <!-- Audit Table -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list-alt me-2 text-primary"></i>Audit Trail</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="auditTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Course</th>
                                <th>Action</th>
                                <th>Changes</th>
                                <th>User</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_actions as $course): ?>
                            <?php
                                // Determine who performed the action
                                if (!empty($course['editor_username'])) {
                                    $user_name = trim($course['editor_fname'] . ' ' . $course['editor_lname']);
                                    $user_name = $user_name ?: $course['editor_username'];
                                    $user_role = $course['editor_role'];
                                    $user_avatar = substr($course['editor_fname'] ?: $course['editor_username'], 0, 1);
                                } elseif ($course['action_type'] == 'ADDED' && !empty($course['creator_username'])) {
                                    $user_name = trim($course['creator_fname'] . ' ' . $course['creator_lname']);
                                    $user_name = $user_name ?: $course['creator_username'];
                                    $user_role = $course['creator_role'];
                                    $user_avatar = substr($course['creator_fname'] ?: $course['creator_username'], 0, 1);
                                } else {
                                    $user_name = 'System';
                                    $user_role = 'system';
                                    $user_avatar = 'S';
                                }

                                // Format date/time
                                $display_time = $course['audit_time'] ?? $course['updated_at'] ?? $course['created_at'];
                                $formatted_time = date('M d, Y h:i A', strtotime($display_time));

                                // Get changes description
                                $changes = formatChanges($course['old_data'], $course['new_data'], $course['changed_fields']);
                                
                                // Role badge class
                                $role_class = 'role-' . strtolower(str_replace(' ', '', $user_role));
                            ?>
                            <tr>
                                <td class="audit-time"><?= htmlspecialchars($formatted_time) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($course['title'] ?: 'Untitled') ?></strong>
                                    <br><small class="text-muted">ID: <?= $course['course_id'] ?></small>
                                </td>
                                <td>
                                    <span class="action-badge action-<?= strtolower($course['action_type']) ?>">
                                        <?php if ($course['action_type'] == 'ADDED'): ?>
                                            <i class="fas fa-plus-circle me-1"></i>Added
                                        <?php elseif ($course['action_type'] == 'EDITED'): ?>
                                            <i class="fas fa-edit me-1"></i>Edited
                                        <?php elseif ($course['action_type'] == 'DELETED'): ?>
                                            <i class="fas fa-trash me-1"></i>Deleted
                                        <?php else: ?>
                                            <i class="fas fa-eye me-1"></i><?= $course['action_type'] ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="changes-cell"><?= $changes ?></td>
                                <td>
                                    <div class="user-badge">
                                        <span class="user-avatar"><?= strtoupper($user_avatar) ?></span>
                                        <span><?= htmlspecialchars($user_name) ?></span>
                                    </div>
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
                                <td colspan="6" class="text-center py-4">
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