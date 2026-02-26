<!-- done -->

<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';
require_login();

// Only admin can access this page
if (!is_admin() && !is_superadmin()) {
echo 'Admin only';
exit;
}

$act = $_GET['act'] ?? '';

// Fetch all departments for dropdown/checkboxes
$deptStmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// ADD USER WITH EMAIL NOTIFICATION
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
session_start();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fname    = trim($_POST['fname'] ?? '');
$lname    = trim($_POST['lname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = $_POST['role'] ?? 'user';

// Handle departments
$selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
if (!is_array($selectedDepts)) {
$selectedDepts = [$selectedDepts];
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$_SESSION['error'] = "Invalid email format";
header('Location: users_crud.php?act=addform');
exit;
}

// FIXED: Check if username OR email already exists - separate checks for better error message
$checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$checkUsername->execute([$username]);
if ($checkUsername->fetch()) {
$_SESSION['error'] = "Username already exists";
header('Location: users_crud.php?act=addform');
exit;
}

$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$checkEmail->execute([$email]);
if ($checkEmail->fetch()) {
$_SESSION['error'] = "Email already exists";
header('Location: users_crud.php?act=addform');
exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
// Start transaction
$pdo->beginTransaction();

// Insert user
$stmt = $pdo->prepare(
"INSERT INTO users (username, password, fname, lname, email, role, status, created_at)
VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())"
);
$stmt->execute([$username, $hash, $fname, $lname, $email, $role]);

// Get the new user ID
$newUserId = $pdo->lastInsertId();

// Insert into user_departments junction table
if (!empty($selectedDepts)) {
$deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
foreach ($selectedDepts as $deptId) {
$deptStmt->execute([$newUserId, $deptId]);
}
}

// Prepare recipient name
$recipientName = !empty($fname) ? $fname : $username;
if (!empty($lname)) {
$recipientName .= ' ' . $lname;
}

// SEND WELCOME EMAIL
if (function_exists('sendConfirmationEmail')) {
$emailResult = sendConfirmationEmail($email, $recipientName, $username, $password);

if ($emailResult['success']) {
$pdo->commit();
$_SESSION['success'] = "User added successfully and welcome email sent to $email";
} else {
$pdo->commit();
$_SESSION['warning'] = "User added but email failed: " . $emailResult['message'];
}
} else {
$pdo->commit();
$_SESSION['success'] = "User added successfully";
}

} catch (Exception $e) {
$pdo->rollBack();
$_SESSION['error'] = "Failed to add user: " . $e->getMessage();
error_log("Add user error: " . $e->getMessage());
}

header('Location: users_crud.php');
exit;
}

// UPDATE USER
if ($act === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
session_start();

$id       = (int)$_POST['id'];
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fname    = trim($_POST['fname'] ?? '');
$lname    = trim($_POST['lname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = $_POST['role'] ?? 'user';

// Handle departments
$selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
if (!is_array($selectedDepts)) {
$selectedDepts = [$selectedDepts];
}

// FIXED: Check if email already exists for OTHER users
$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$checkEmail->execute([$email, $id]);
if ($checkEmail->fetch()) {
$_SESSION['error'] = "Email already exists for another user";
header('Location: users_crud.php?act=edit&id=' . $id);
exit;
}

// Check if username already exists for OTHER users
$checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$checkUsername->execute([$username, $id]);
if ($checkUsername->fetch()) {
$_SESSION['error'] = "Username already exists for another user";
header('Location: users_crud.php?act=edit&id=' . $id);
exit;
}

try {
$pdo->beginTransaction();

if (!empty($password)) {
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "UPDATE users
SET username=?, fname=?, lname=?, email=?, role=?, password=?
WHERE id=?";
$params = [$username, $fname, $lname, $email, $role, $hash, $id];
} else {
$sql = "UPDATE users
SET username=?, fname=?, lname=?, email=?, role=?
WHERE id=?";
$params = [$username, $fname, $lname, $email, $role, $id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Update departments - delete old ones and insert new
$pdo->prepare("DELETE FROM user_departments WHERE user_id = ?")->execute([$id]);

if (!empty($selectedDepts)) {
$deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
foreach ($selectedDepts as $deptId) {
$deptStmt->execute([$id, $deptId]);
}
}

$pdo->commit();
$_SESSION['success'] = "User updated successfully";

} catch (Exception $e) {
$pdo->rollBack();
$_SESSION['error'] = "Failed to update user: " . $e->getMessage();
}

header('Location: users_crud.php');
exit;
}

// DELETE USER
if ($act === 'delete' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];

try {
// Foreign key cascade will delete user_departments automatically
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
$_SESSION['success'] = "User deleted successfully";
} catch (Exception $e) {
$_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
}

header('Location: users_crud.php');
exit;
}

// FETCH USER FOR EDIT
if ($act === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
$id = (int)$_GET['id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
exit('User not found');
}

// Get user's department IDs
$deptStmt = $pdo->prepare("SELECT department_id FROM user_departments WHERE user_id = ?");
$deptStmt->execute([$id]);
$userDepts = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
$user['department_ids'] = $userDepts;
}

// CONFIRM USER STATUS
if (isset($_GET['act']) && $_GET['act'] === 'confirm' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current) {
if ($current['status'] !== 'confirmed') {
$update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$update->execute(['confirmed', $id]);
$_SESSION['success'] = "User confirmed successfully";
}

header('Location: users_crud.php');
exit;
} else {
exit('User not found');
}
}

// REJECT USER (Delete pending user)
if (isset($_GET['act']) && $_GET['act'] === 'reject' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];
$pdo->prepare('DELETE FROM users WHERE id = ? AND status = "pending"')->execute([$id]);
$_SESSION['success'] = "User rejected and deleted";
header('Location: users_crud.php');
exit;
}

// Get all users
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch confirmed users with their departments using JOIN
$confirmedUsers = $pdo->query("
SELECT u.*, 
GROUP_CONCAT(d.name SEPARATOR '||') as department_names
FROM users u
LEFT JOIN user_departments ud ON u.id = ud.user_id
LEFT JOIN departments d ON ud.department_id = d.id
WHERE u.status = 'confirmed'
GROUP BY u.id
ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending users
$pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

       // Count stats
$totalUsers = count($allUsers);
$totalConfirmed = count($confirmedUsers);
$totalPending = count($pendingUsers);

?>

<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<title>User Management - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/profile.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/manager.css" rel="stylesheet">
</head>

<body>

<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
<div class="container py-9">

                  <!-- Session Messages -->
<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
<i class="fas fa-check-circle me-2"></i>
<?= $_SESSION['success']; unset($_SESSION['success']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
<i class="fas fa-exclamation-circle me-2"></i>
<?= $_SESSION['error']; unset($_SESSION['error']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['warning'])): ?>
<div class="alert alert-warning alert-dismissible fade show">
<i class="fas fa-exclamation-triangle me-2"></i>
<?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

                  <!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
<h3 class="m-0">User Management</h3>
<?php if($act !== 'addform'): ?>
<a href="?act=addform" class="btn btn-success">
<i class="fas fa-plus"></i> Add New User
</a>
<?php endif; ?>
</div>

                   <!-- Statistics Cards -->
<div class="row mb-4 justify-content-start">
<div class="col-auto">
<div class="stats-card">
<span class="stats-label">Total Users:</span>
<span class="stats-number"><?= $totalUsers ?></span>
</div>
</div>

<div class="col-auto">
<div class="stats-card">
<span class="stats-label">Confirmed Accounts:</span>
<span class="stats-number"><?= $totalConfirmed ?></span>
</div>
</div>

<div class="col-auto">
<div class="stats-card">
<span class="stats-label">Pending Confirmation:</span>
<span class="stats-number"><?= $totalPending ?></span>
</div>
</div>
</div>

<!-- Add User Form -->
<?php if ($act === 'addform'): ?>
<div class="card p-4 mb-4">
<h5 class="mb-3">Add New User</h5>
<form method="post" action="?act=add">
<div class="row">
<div class="col-md-6 mb-3">
<label>Username</label>
<input name="username" class="form-control" placeholder="Username" required>
</div>
<div class="col-md-6 mb-3">
<label>Password</label>
<input type="password" name="password" class="form-control" placeholder="Password" required>
</div>
<div class="col-md-6 mb-3">
<label>First Name</label>
<input name="fname" class="form-control" placeholder="First Name">
</div>
<div class="col-md-6 mb-3">
<label>Last Name</label>
<input name="lname" class="form-control" placeholder="Last Name">
</div>
<div class="col-md-6 mb-3">
<label>Email</label>
<input name="email" type="email" class="form-control" placeholder="Email" required>
</div>
<div class="col-md-6 mb-3">
<label>Role</label>
<select name="role" class="form-control" id="roleSelect" required>
<option value="user">Student</option>
<option value="proponent">Proponent</option>
<option value="admin">Admin</option>
</select>
</div>
</div>

<!-- Department Checkboxes with Search - Hidden by default -->
<div class="mb-3" id="departmentsSection" style="display: none;">
    <label>Departments</label>
    
    <!-- Search Bar -->
    <div class="mb-2">
        <input type="text" id="departmentSearch" class="form-control" placeholder="Search departments..." style="margin-bottom: 10px;">
    </div>
    
    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;" id="departmentContainer">
        <?php foreach($departments as $dept): ?>
        <div style="margin-bottom: 8px;" class="department-item" data-department-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>">
            <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" id="dept_<?= $dept['id'] ?>">
            <label for="dept_<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></label>
        </div>
        <?php endforeach; ?>
    </div>
    <small class="text-muted">Select all departments the user belongs to</small>
</div>

<div class="mt-3">
<button type="submit" class="btn btn-primary">Create User</button>
<a href="users_crud.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<?php endif; ?>

<!-- Edit User Form -->
<?php if ($act === 'edit' && isset($user)): ?>
<div class="card p-4 mb-4">
<h5 class="mb-3">Edit User - <?= htmlspecialchars($user['username']) ?></h5>
<form method="post" action="?act=edit">
<input type="hidden" name="id" value="<?= $user['id'] ?>">

<div class="row">
<div class="col-md-6 mb-3">
<label>Username</label>
<input class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
</div>
<div class="col-md-6 mb-3">
<label>New Password (leave empty to keep current)</label>
<div class="input-group">
<input class="form-control" type="password" name="password" id="passwordField" placeholder="Enter new password" disabled>
<button type="button" class="btn btn-outline-secondary" onclick="enablePassword()">Change</button>
</div>
</div>
<div class="col-md-6 mb-3">
<label>First Name</label>
<input class="form-control" name="fname" value="<?= htmlspecialchars($user['fname']) ?>">
</div>
<div class="col-md-6 mb-3">
<label>Last Name</label>
<input class="form-control" name="lname" value="<?= htmlspecialchars($user['lname']) ?>">
</div>
<div class="col-md-6 mb-3">
<label>Email</label>
<input class="form-control" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
</div>
<div class="col-md-6 mb-3">
<label>Role</label>
<select name="role" class="form-control" id="roleSelect">
<option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Student</option>
<option value="proponent" <?= $user['role'] === 'proponent' ? 'selected' : '' ?>>Proponent</option>
<option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</div>
</div>

<!-- Department Checkboxes with Search - Hidden by default -->
<div class="mb-3" id="departmentsSection" style="display: none;">
    <label>Departments</label>
    
    <!-- Search Bar -->
    <div class="mb-2">
        <input type="text" id="departmentSearch" class="form-control" placeholder="Search departments..." style="margin-bottom: 10px;">
    </div>
    
    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;" id="departmentContainer">
        <?php foreach($departments as $dept): ?>
        <div style="margin-bottom: 8px;" class="department-item" data-department-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>">
            <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" id="dept_<?= $dept['id'] ?>">
            <label for="dept_<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></label>
        </div>
        <?php endforeach; ?>
    </div>
    <small class="text-muted">Select all departments the user belongs to</small>
</div>

<div class="mt-3">
<button type="submit" class="btn btn-primary">Create User</button>
<a href="users_crud.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<?php endif; ?>

                  <!-- Pending Users Table -->
<div class="card shadow-sm mb-4">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="m-0">
<span class="status-indicator status-pending"></span> 
Pending Confirmation (<?= count($pendingUsers) ?>)
</h5>
<span class="badge bg-warning">Waiting for email verification</span>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0 fixed-table-pending">
<thead class="table-light">
<tr>
<th>ID</th>
<th>Username</th>
<th>Full Name</th>
<th>Email</th>
<th>Registered</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($pendingUsers)): ?>
<tr>
<td colspan="8" class="text-center py-4 text-muted">
<i class="fas fa-check-circle"></i> No pending users found
</td>
</tr>
<?php else: ?>
<?php foreach ($pendingUsers as $u): ?>
<tr>
<td><span class="fw-bold">#<?= $u['id'] ?></span></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= date('M d, Y H:i', strtotime($u['created_at'])) ?></td>
<td>
<span class="badge-pending">
<i class="fas fa-clock"></i> Pending
</span>
</td>
<td class="table-actions">
<a href="?act=confirm&id=<?= $u['id'] ?>" 
onclick="return confirm('Confirm <?= htmlspecialchars($u['username']) ?>?')" 
class="btn btn-success btn-sm">
<i class="fas fa-check"></i> Approve
</a>
<a href="?act=reject&id=<?= $u['id'] ?>" 
onclick="return confirm('Reject and delete <?= htmlspecialchars($u['username']) ?>?')" 
class="btn btn-danger btn-sm">
<i class="fas fa-times"></i> Reject
</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

                      <!-- Confirmed Users Table - WITH DELETE BUTTON ADDED -->
<div class="card shadow-sm">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="m-0">
<span class="status-indicator status-confirmed"></span> 
Confirmed Users (<?= count($confirmedUsers) ?>)
</h5>
<span class="badge bg-success">Email verified</span>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0 fixed-table">
<thead class="table-light">
<tr>
<th>ID</th>
<th>Username</th>
<th>Full Name</th>
<th>Department</th>
<th>Email</th>
<th>Role</th>
<th>Joined</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($confirmedUsers)): ?>
<tr>
<td colspan="9" class="text-center py-4 text-muted">
<i class="fas fa-users"></i> No confirmed users yet
</td>
</tr>
<?php else: ?>
<?php foreach ($confirmedUsers as $u): 
$deptNames = !empty($u['department_names']) ? explode('||', $u['department_names']) : [];
?>
<tr>
<td><span class="fw-bold">#<?= $u['id'] ?></span></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
<td data-departments="<?= htmlspecialchars(implode(', ', $deptNames)) ?>">
    <?php if (!empty($deptNames)): ?>
        <?php foreach(array_slice($deptNames, 0, 1) as $dept): ?>
            <span class="badge" style="background-color: #667eea; color: white; margin: 2px; padding: 5px 8px;">
                <?= htmlspecialchars($dept) ?>
            </span>
        <?php endforeach; ?>
        <?php if (count($deptNames) > 1): ?>
            <span class="badge bg-secondary">+<?= count($deptNames) - 1 ?> more</span>
        <?php endif; ?>
    <?php else: ?>
        <span class="text-muted">No Department</span>
    <?php endif; ?>
</td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td>
<?php if ($u['role'] === 'admin'): ?>
<span class="badge bg-danger">Admin</span>
<?php elseif ($u['role'] === 'proponent'): ?>
<span class="badge bg-info">Proponent</span>
<?php elseif ($u['role'] === 'superadmin'): ?>
<span class="badge bg-secondary">SuperAdmin</span>
<?php else: ?>
<span class="badge bg-success">Student</span>
<?php endif; ?>
</td>
<td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
<td>
<span class="badge-confirmed">
<i class="fas fa-check-circle"></i> Confirmed
</span>
</td>
<td class="table-actions">
<a href="?act=edit&id=<?= $u['id'] ?>" class="btn btn-primary btn-sm">
<i class="fas fa-edit"></i> Edit
</a>
                       <!-- ADDED: Delete button for confirmed users -->
<a href="?act=delete&id=<?= $u['id'] ?>" 
onclick="return confirm('Are you sure you want to delete user <?= htmlspecialchars($u['username']) ?>? This action cannot be undone.')" 
class="btn btn-danger btn-sm">
<i class="fas fa-trash"></i> Delete
</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function enablePassword() {
document.getElementById('passwordField').disabled = false;
document.getElementById('passwordField').focus();
}

setTimeout(function() {
let alerts = document.querySelectorAll('.alert');
alerts.forEach(alert => {
alert.style.transition = 'opacity 0.5s';
alert.style.opacity = '0';
setTimeout(() => alert.remove(), 500);
});
}, 3000);

// Department search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('departmentSearch');
    if (searchInput) {
        const departmentItems = document.querySelectorAll('.department-item');
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            departmentItems.forEach(function(item) {
                const departmentName = item.getAttribute('data-department-name');
                
                if (searchTerm === '' || departmentName.includes(searchTerm)) {
                    item.style.display = ''; // Show
                } else {
                    item.style.display = 'none'; // Hide
                }
            });
        });
    }

    // Role-based department visibility
    const roleSelect = document.getElementById('roleSelect');
    const departmentsSection = document.getElementById('departmentsSection');
    
    if (roleSelect && departmentsSection) {
        function toggleDepartments() {
            const selectedRole = roleSelect.value;
            if (selectedRole === 'proponent' || selectedRole === 'admin') {
                departmentsSection.style.display = 'block';
            } else {
                departmentsSection.style.display = 'none';
            }
        }
        
        // Initial check
        toggleDepartments();
        
        // Listen for changes
        roleSelect.addEventListener('change', toggleDepartments);
    }
});
</script>

</body>
</html>