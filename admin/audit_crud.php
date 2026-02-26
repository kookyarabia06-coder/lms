<!-- done -->

<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Check if user is superadmin and admin
if (!is_superadmin() && !is_admin()) {
echo 'Authorized Role Only';
exit;
}

// Get all proponents and admins
$stmt = $pdo->prepare("
SELECT u.id, u.username, u.fname, u.lname, u.role 
FROM users u 
WHERE u.role IN ('admin', 'proponent', 'superadmin') 
ORDER BY u.role, u.username
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courses with their creator info and actions
$stmt = $pdo->prepare("
SELECT 
c.id as course_id,
c.title,
c.created_at,
c.updated_at,
u.id as user_id,
u.username,
u.fname,
u.lname,
u.role,
CASE 
WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
ELSE 'ADDED'
END as action
FROM courses c
LEFT JOIN users u ON c.proponent_id = u.id
WHERE u.role IN ('admin', 'proponent', 'superadmin') OR u.role IS NULL
ORDER BY c.updated_at DESC, c.created_at DESC
");
$stmt->execute();
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Activity - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    
.action-badge {
padding: 4px 10px;
border-radius: 20px;
font-size: 11px;
font-weight: 600;
display: inline-block;
}
.action-added {
background: #28a73b;
color: white;
}
.action-deleted {
background: #e00000;
color: white;
}
.action-edited {
background: #ffc107;
color: black;
}

table {
  table-layout: fixed; /* Helps with width/height control */
}

.table th {
  background: #343a40;
  color: white;
  font-size: 16px;
  white-space: nowrap;
  height: 50px;           /* fixed height */
  max-height: 60px;       /* optional max */
  overflow: hidden;       /* hide excess content */
}

.table td {
  font-size: 13px;
  vertical-align: middle;
  height: 40px;
  max-height: 80px;
  overflow-y: auto;       /* show scrollbar if needed */
}


/* Add this to your existing CSS */
.card {
    max-height: 600px; /* Adjust this value as needed */
    display: flex;
    flex-direction: column;
}

.card-body {
    flex: 1;
    overflow-y: auto;
    min-height: 0; /* Important for flexbox overflow */
}

.table-responsive {
    max-height: 500px; /* Adjust this value as needed */
    overflow-y: auto;
}

/* Keep the table header fixed */
.table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #b8b9bb; /* Match your existing header color */
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
<div class="container-fluid py-4">
<h3 class="mb-4">Audit Trail</h3>

<!-- Statistics Cards -->
<div class="row mb-4"> 
</div>

<!-- Course Activity Table -->
<div class="card">
<div class="card-header text-black d-flex justify-content-between align-items-center">
<h5 class="mb-0">Course Creation & Edit History</h5>
<span class="badge bg-success"><?= count($course_actions) ?> records</span>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
<th>Course ID</th>
<th>Course Title</th>
<th>Created At</th>
<th>Last Updated</th>
<th>Action</th>
<th>Done By</th>
<th>Role</th>
</tr>
</thead>
<tbody>
<?php if (empty($course_actions)): ?>
<tr>
<td colspan="7" class="text-center py-5 text-muted">
<i class="fas fa-info-circle fa-2x mb-3"></i>
<p>No course activities found</p>
</td>
</tr>
<?php else: ?>
<?php foreach ($course_actions as $course): ?>
<tr>
<td><span class="fw-bold">#<?= $course['course_id'] ?></span></td>
<td><?= htmlspecialchars($course['title']) ?></td>
<td>
<small>
<?= date('M d, Y', strtotime($course['created_at'])) ?>
<br>
<span class="text-muted"><?= date('h:i A', strtotime($course['created_at'])) ?></span>
</small>
</td>
<td>
<?php if ($course['updated_at']): ?>
<small>
<?= date('M d, Y', strtotime($course['updated_at'])) ?>
<br>
<span class="text-muted"><?= date('h:i A', strtotime($course['updated_at'])) ?></span>
</small>
<?php else: ?>
<span class="text-muted">Never updated</span>
<?php endif; ?>
</td>
<td>
<?php if ($course['action'] == 'ADDED'): ?>
<span class="action-badge action-added">
<i class="fas fa-plus-circle me-1"></i>Added
</span>
<?php else: ?>
<span class="action-badge action-edited">
<i class="fas fa-edit me-1"></i>Edited
</span>
<?php endif; ?>
</td>
<td>
<?php if ($course['username']): ?>
<div class="d-flex align-items-center">
<div>
<strong><?= htmlspecialchars($course['username']) ?></strong>
<br>
<small class="text-muted"><?= htmlspecialchars($course['fname'] ?? '') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></small>
</div>
</div>
<?php else: ?>
<span class="text-muted">Unknown</span>
<?php endif; ?>
</td>
<td>
<?php if ($course['role'] == 'admin'): ?>
    <span class="badge bg-primary">Admin</span>
<?php elseif ($course['role'] == 'proponent'): ?>
    <span class="badge bg-info">Proponent</span>
<?php elseif ($course['role'] == 'superadmin'): ?>
    <span class="badge bg-secondary">Super Admin</span>
<?php else: ?>
    <span class="badge bg-secondary"><?= htmlspecialchars($course['role'] ?? 'Unknown') ?></span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Creators List (Optional) -->

</div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>