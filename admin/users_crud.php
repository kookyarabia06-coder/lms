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

// Handle add committee
if (isset($_POST['add_committee'])) {
    $name = $_POST['name'];
    $description = $_POST['description'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO committees (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);

    header('Location: ' . BASE_URL . '/admin/users_crud.php');
    exit;
}

// Handle ADD DIVISION (departments table)
if ($act === 'add_department' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    
    $dept_name = trim($_POST['department_name'] ?? '');
    
    if (empty($dept_name)) {
        $_SESSION['error'] = "Division name is required";
        header('Location: users_crud.php' . (isset($_GET['form']) ? '?act=' . $_GET['form'] : ''));
        exit;
    }
    
    // Check if division already exists
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
    $checkStmt->execute([$dept_name]);
    if ($checkStmt->fetch()) {
        $_SESSION['error'] = "Division already exists";
        header('Location: users_crud.php' . (isset($_GET['form']) ? '?act=' . $_GET['form'] : ''));
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->execute([$dept_name]);
        $_SESSION['success'] = "Division added successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to add division: " . $e->getMessage();
    }
    
    // Redirect back to the same form
    if (isset($_GET['form']) && $_GET['form'] === 'add') {
        header('Location: users_crud.php?act=addform');
    } elseif (isset($_GET['form']) && $_GET['form'] === 'edit' && isset($_GET['user_id'])) {
        header('Location: users_crud.php?act=edit&id=' . $_GET['user_id']);
    } else {
        header('Location: users_crud.php');
    }
    exit;
}

// Handle EDIT DIVISION (departments table)
if ($act === 'edit_department' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $dept_name = trim($_POST['department_name'] ?? '');
    
    if (empty($dept_name)) {
        $_SESSION['error'] = "Division name is required";
        header('Location: users_crud.php' . (isset($_GET['form']) ? '?act=' . $_GET['form'] : ''));
        exit;
    }
    
    if ($dept_id <= 0) {
        $_SESSION['error'] = "Invalid division ID";
        header('Location: users_crud.php' . (isset($_GET['form']) ? '?act=' . $_GET['form'] : ''));
        exit;
    }
    
    // Check if division name already exists (excluding current)
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
    $checkStmt->execute([$dept_name, $dept_id]);
    if ($checkStmt->fetch()) {
        $_SESSION['error'] = "Division name already exists";
        header('Location: users_crud.php' . (isset($_GET['form']) ? '?act=' . $_GET['form'] : ''));
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
        $stmt->execute([$dept_name, $dept_id]);
        $_SESSION['success'] = "Division updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update division: " . $e->getMessage();
    }
    
    // Redirect back to the same form
    if (isset($_GET['form']) && $_GET['form'] === 'add') {
        header('Location: users_crud.php?act=addform');
    } elseif (isset($_GET['form']) && $_GET['form'] === 'edit' && isset($_GET['user_id'])) {
        header('Location: users_crud.php?act=edit&id=' . $_GET['user_id']);
    } else {
        header('Location: users_crud.php');
    }
    exit;
}

// Fetch all committees for dropdown/checkboxes
$committeeStmt = $pdo->query("SELECT id, name, description FROM committees ORDER BY name ASC");
$committees = $committeeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all divisions from departments table
$divisionStmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$divisions = $divisionStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all departments from depts table (for the second dropdown)
$deptStmt = $pdo->query("SELECT d.*, dept.name as division_name FROM depts d LEFT JOIN departments dept ON d.department_id = dept.id ORDER BY d.name ASC");
$allDepartments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// ADD USER WITH EMAIL NOTIFICATION
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fname    = trim($_POST['fname'] ?? '');
    $lname    = trim($_POST['lname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'user';

    // Handle departments (depts table) - for students
    $selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
    if (!is_array($selectedDepts)) {
        $selectedDepts = [$selectedDepts];
    }
    // Filter out empty values and keep only numeric IDs
    $selectedDepts = array_filter($selectedDepts, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedDepts = array_values($selectedDepts);

    // Handle committee selection - for proponents/admins
    $selectedCommittees = isset($_POST['committees']) ? $_POST['committees'] : [];
    if (!is_array($selectedCommittees)){
        $selectedCommittees = [$selectedCommittees];
    }
    $selectedCommittees = array_filter($selectedCommittees, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedCommittees = array_values($selectedCommittees);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header('Location: users_crud.php?act=addform');
        exit;
    }

    // Check if username OR email already exists
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

        // Insert departments (from depts table) only if there are valid IDs
        if (!empty($selectedDepts)) {
            // First verify that all department IDs exist in depts table
            $placeholders = implode(',', array_fill(0, count($selectedDepts), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM depts WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedDepts);
            $validDeptIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Insert only valid department IDs
            if (!empty($validDeptIds)) {
                $deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, dept_id) VALUES (?, ?)");
                foreach ($validDeptIds as $deptId) {
                    $deptStmt->execute([$newUserId, $deptId]);
                }
            }
        }

        // Insert committees only if there are valid IDs
        if (!empty($selectedCommittees)) {
            // First verify that all committee IDs exist
            $placeholders = implode(',', array_fill(0, count($selectedCommittees), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM committees WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedCommittees);
            $validCommIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Insert only valid committee IDs
            if (!empty($validCommIds)) {
                $commStmt = $pdo->prepare("INSERT INTO user_departments (user_id, committee_id) VALUES (?, ?)");
                foreach ($validCommIds as $commId) {
                    $commStmt->execute([$newUserId, $commId]);
                }
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

    // Handle departments (from depts table) - Filter out empty values
    $selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
    if (!is_array($selectedDepts)) {
        $selectedDepts = [$selectedDepts];
    }
    $selectedDepts = array_filter($selectedDepts, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedDepts = array_values($selectedDepts);

    // Handle committees - Filter out empty values
    $selectedCommittees = isset($_POST['committees']) ? $_POST['committees'] : [];
    if (!is_array($selectedCommittees)) {
        $selectedCommittees = [$selectedCommittees];
    }
    $selectedCommittees = array_filter($selectedCommittees, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedCommittees = array_values($selectedCommittees);

    // Check if email already exists for OTHER users
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

        // Delete all existing assignments
        $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?")->execute([$id]);

        // Insert departments (from depts table) only if there are valid IDs
        if (!empty($selectedDepts)) {
            // Verify department IDs exist in depts table
            $placeholders = implode(',', array_fill(0, count($selectedDepts), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM depts WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedDepts);
            $validDeptIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($validDeptIds)) {
                $deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, dept_id) VALUES (?, ?)");
                foreach ($validDeptIds as $deptId) {
                    $deptStmt->execute([$id, $deptId]);
                }
            }
        }

        // Insert committees only if there are valid IDs
        if (!empty($selectedCommittees)) {
            // Verify committee IDs exist
            $placeholders = implode(',', array_fill(0, count($selectedCommittees), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM committees WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedCommittees);
            $validCommIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($validCommIds)) {
                $commStmt = $pdo->prepare("INSERT INTO user_departments (user_id, committee_id) VALUES (?, ?)");
                foreach ($validCommIds as $commId) {
                    $commStmt->execute([$id, $commId]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "User updated successfully";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to update user: " . $e->getMessage();
        error_log("Update user error: " . $e->getMessage());
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

    // Get user's department IDs (from depts table)
    $deptStmt = $pdo->prepare("SELECT dept_id FROM user_departments WHERE user_id = ? AND dept_id IS NOT NULL");
    $deptStmt->execute([$id]);
    $userDepts = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    $user['department_ids'] = $userDepts ?: [];

    // Get user's committee IDs
    $commStmt = $pdo->prepare("SELECT committee_id FROM user_departments WHERE user_id = ? AND committee_id IS NOT NULL");
    $commStmt->execute([$id]);
    $userCommittees = $commStmt->fetchAll(PDO::FETCH_COLUMN);
    $user['committee_ids'] = $userCommittees ?: [];

    // Get the division ID for the selected department (if any)
    if (!empty($userDepts)) {
        $firstDeptId = $userDepts[0];
        $divisionStmt = $pdo->prepare("SELECT department_id FROM depts WHERE id = ?");
        $divisionStmt->execute([$firstDeptId]);
        $divisionId = $divisionStmt->fetchColumn();
        $user['selected_division_id'] = $divisionId ?: '';
    } else {
        $user['selected_division_id'] = '';
    }
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

// Fetch confirmed users with their departments and committees
$confirmedUsers = $pdo->query("
    SELECT u.*, 
           GROUP_CONCAT(DISTINCT d.name SEPARATOR '||') as department_names,
           GROUP_CONCAT(DISTINCT dept.name SEPARATOR '||') as division_names,
           GROUP_CONCAT(DISTINCT c.name SEPARATOR '||') as committee_names
    FROM users u
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN depts d ON ud.dept_id = d.id
    LEFT JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN committees c ON ud.committee_id = c.id
    WHERE u.status = 'confirmed'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending users with their department and division information
$pendingUsers = $pdo->query("
    SELECT u.*, 
           d.id as dept_id,
           d.name as department_name,
           dept.id as division_id,
           dept.name as division_name,
           c.id as committee_id,
           c.name as committee_name
    FROM users u
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN depts d ON ud.dept_id = d.id
    LEFT JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN committees c ON ud.committee_id = c.id
    WHERE u.status = 'pending'
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
<link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
<style>
    .cascading-dropdown {
        transition: all 0.3s ease;
    }
    .badge-division {
        background-color: #6c757d;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 5px;
    }
    
    /* ADDED: Delete Confirmation Modal Styles */
    .delete-confirm-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }

    .delete-confirm-modal.active {
        display: flex;
    }

    .delete-confirm-content {
        background: white;
        border-radius: 12px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: modalFadeIn 0.2s ease-out;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .delete-confirm-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .delete-confirm-header i {
        font-size: 22px;
        color: #dc3545;
    }

    .delete-confirm-header h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        color: #111827;
    }

    .delete-confirm-body {
        padding: 20px 24px;
    }

    .delete-confirm-body p {
        color: #4b5563;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .delete-confirm-body .user-info {
        background: #f9fafb;
        padding: 12px;
        border-radius: 8px;
        margin: 12px 0;
        border-left: 3px solid #dc3545;
    }

    .delete-confirm-body .user-info strong {
        display: block;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .delete-confirm-body .user-info small {
        color: #64748b;
        font-size: 12px;
    }

    .warning-note {
        background: #fef3c7;
        padding: 12px;
        border-radius: 8px;
        margin-top: 16px;
        font-size: 13px;
        color: #92400e;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .warning-note i {
        color: #f59e0b;
    }

    .delete-confirm-footer {
        padding: 16px 24px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .delete-confirm-footer button {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .delete-confirm-footer .btn-cancel-delete {
        background: #f3f4f6;
        color: #374151;
    }

    .delete-confirm-footer .btn-cancel-delete:hover {
        background: #e5e7eb;
    }

    .delete-confirm-footer .btn-confirm-delete {
        background: #dc3545;
        color: white;
    }

    .delete-confirm-footer .btn-confirm-delete:hover {
        background: #c82333;
    }
</style>
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
<select name="role" class="form-control" id="roleSelectAdd" required>
<option value="user">Student</option>
<option value="proponent">Proponent</option>
<option value="admin">Admin</option>
</select>
</div>
</div>

<!-- Student Section: Division and Department Dropdowns -->
<div class="mb-3" id="studentSectionAdd" style="display: none;">
    <div class="card p-3 mb-3">
        <h6 class="card-title mb-3">Select Division and Department</h6>
        
        <!-- Division Dropdown -->
        <div class="mb-3">
            <label class="form-label fw-bold">Select Division</label>
            <select class="form-control" id="divisionSelectAdd" name="division_id">
                <option value="">-- Choose a Division --</option>
                <?php foreach($divisions as $division): ?>
                    <option value="<?= $division['id'] ?>"><?= htmlspecialchars($division['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Select a division to see its departments</small>
        </div>
        
        <!-- Department Dropdown (initially disabled) -->
        <div class="mb-3">
            <label class="form-label fw-bold">Select Department</label>
            <select class="form-control" id="departmentSelectAdd" name="departments[]" disabled>
                <option value="">-- First select a division --</option>
            </select>
            <small class="text-muted">Choose the department for this user</small>
        </div>
        
        <!-- Selected Department Display (for multiple selections if needed) -->
        <div class="mt-2" id="selectedDepartmentsAdd">
            <label class="form-label">Selected Departments:</label>
            <div class="d-flex flex-wrap gap-2" id="selectedDeptsListAdd"></div>
        </div>
        
        <!-- Add Department Button (for multiple selections) -->
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addDepartmentBtnAdd" style="display: none;">
            <i class="fas fa-plus"></i> Add Another Department
        </button>
    </div>
</div>

<!-- Proponent/Admin Section: Committee Checkboxes -->
<div class="mb-3" id="committeeSectionAdd" style="display: none;">
    <div class="card p-3">
        <h6 class="card-title mb-3">Select Committees</h6>
        
        <!-- Search Bar -->
        <div class="mb-2">
            <input type="text" id="committeeSearchAdd" class="form-control" placeholder="Search committees...">
        </div>
        
        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;" id="committeeContainerAdd">
            <?php if (empty($committees)): ?>
                <p class="text-muted text-center">No committees available.</p>
            <?php else: ?>
                <?php foreach($committees as $committee): ?>
                <div style="margin-bottom: 8px;" class="committee-item" data-committee-name="<?= strtolower(htmlspecialchars($committee['name'])) ?>">
                    <input type="checkbox" name="committees[]" value="<?= $committee['id'] ?>" id="committee_<?= $committee['id'] ?>">
                    <label for="committee_<?= $committee['id'] ?>"><?= htmlspecialchars($committee['name']) ?></label>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <small class="text-muted">Select all committees the user belongs to</small>
    </div>
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
<select name="role" class="form-control" id="roleSelectEdit">
<option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Student</option>
<option value="proponent" <?= $user['role'] === 'proponent' ? 'selected' : '' ?>>Proponent</option>
<option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</div>
</div>

<!-- Student Section: Division and Department Dropdowns -->
<div class="mb-3" id="studentSectionEdit" style="display: none;">
    <div class="card p-3 mb-3">
        <h6 class="card-title mb-3">Select Division and Department</h6>
        
        <!-- Division Dropdown -->
        <div class="mb-3">
            <label class="form-label fw-bold">Select Division</label>
            <select class="form-control" id="divisionSelectEdit" name="division_id">
                <option value="">-- Choose a Division --</option>
                <?php foreach($divisions as $division): ?>
                    <option value="<?= $division['id'] ?>" <?= ($division['id'] == ($user['selected_division_id'] ?? '')) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($division['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Select a division to see its departments</small>
        </div>
        
        <!-- Department Dropdown (initially disabled) -->
        <div class="mb-3">
            <label class="form-label fw-bold">Select Department</label>
            <select class="form-control" id="departmentSelectEdit" name="departments[]" <?= empty($user['selected_division_id']) ? 'disabled' : '' ?>>
                <option value="">-- First select a division --</option>
                <?php if (!empty($user['selected_division_id'])): ?>
                    <?php
                    $deptStmt = $pdo->prepare("SELECT id, name FROM depts WHERE department_id = ? ORDER BY name ASC");
                    $deptStmt->execute([$user['selected_division_id']]);
                    $depts = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($depts as $dept):
                    ?>
                        <option value="<?= $dept['id'] ?>" <?= in_array($dept['id'], $user['department_ids'] ?? []) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <small class="text-muted">Choose the department for this user</small>
        </div>
        
        <!-- Selected Department Display -->
        <div class="mt-2" id="selectedDepartmentsEdit">
            <label class="form-label">Selected Departments:</label>
            <div class="d-flex flex-wrap gap-2" id="selectedDeptsListEdit">
                <?php if (!empty($user['department_ids'])): ?>
                    <?php
                    $deptStmt = $pdo->prepare("SELECT id, name FROM depts WHERE id IN (" . implode(',', array_fill(0, count($user['department_ids']), '?')) . ")");
                    $deptStmt->execute($user['department_ids']);
                    $selectedDepts = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($selectedDepts as $dept):
                    ?>
                        <span class="badge bg-primary p-2">
                            <?= htmlspecialchars($dept['name']) ?>
                            <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removeDepartment(this, <?= $dept['id'] ?>)"></button>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Proponent/Admin Section: Committee Checkboxes -->
<div class="mb-3" id="committeeSectionEdit" style="display: none;">
    <div class="card p-3">
        <h6 class="card-title mb-3">Select Committees</h6>
        
        <!-- Search Bar -->
        <div class="mb-2">
            <input type="text" id="committeeSearchEdit" class="form-control" placeholder="Search committees...">
        </div>
        
        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;" id="committeeContainerEdit">
            <?php if (empty($committees)): ?>
                <p class="text-muted text-center">No committees available.</p>
            <?php else: ?>
                <?php foreach($committees as $committee): ?>
                <div style="margin-bottom: 8px;" class="committee-item" data-committee-name="<?= strtolower(htmlspecialchars($committee['name'])) ?>">
                    <input type="checkbox" name="committees[]" value="<?= $committee['id'] ?>" id="committee_<?= $committee['id'] ?>"
                        <?= in_array($committee['id'], $user['committee_ids'] ?? []) ? 'checked' : '' ?>>
                    <label for="committee_<?= $committee['id'] ?>"><?= htmlspecialchars($committee['name']) ?></label>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <small class="text-muted">Select all committees the user belongs to</small>
    </div>
</div>

<div class="mt-3">
<button type="submit" class="btn btn-primary">Update User</button>
<a href="users_crud.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<?php endif; ?>

<!-- Add Division Modal (departments table) -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="addDepartmentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add New Division</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Division Name</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Division</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Division Modal (departments table) -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="editDepartmentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Division</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_department_select" class="form-label">Select Division</label>
                        <select class="form-control" id="edit_department_select" required>
                            <option value="">-- Select Division to Edit --</option>
                            <?php foreach($divisions as $dept): ?>
                            <option value="<?= $dept['id'] ?>" data-name="<?= htmlspecialchars($dept['name']) ?>">
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label">New Division Name</label>
                        <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
                        <input type="hidden" name="department_id" id="edit_department_id">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Division</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADDED: Custom Delete Confirmation Modal -->
<div class="delete-confirm-modal" id="deleteConfirmModal">
    <div class="delete-confirm-content">
        <div class="delete-confirm-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Delete User</h3>
        </div>
        <div class="delete-confirm-body">
            <p>Are you sure you want to delete this user?</p>
            <div class="user-info">
                <strong id="deleteUserName">User Name</strong>
                <small id="deleteUserRole">Role: User</small>
            </div>
            <div class="warning-note">
                <i class="fas fa-exclamation-circle"></i>
                <span>Warning: This action cannot be undone. All user data including training records will be permanently deleted.</span>
            </div>
        </div>
        <div class="delete-confirm-footer">
            <button class="btn-cancel-delete" id="cancelDeleteBtn">Cancel</button>
            <button class="btn-confirm-delete" id="confirmDeleteBtn">Delete User</button>
        </div>
    </div>
</div>

<!-- Pending Users Table -->
<div class="card shadow-sm mb-4">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="m-0">
<span class="status-indicator status-pending"></span> 
Pending Confirmation (<?= count($pendingUsers) ?>)
</h5>
<!-- Search Bar for Pending Table -->
<div style="width: 300px;">
<input type="text" id="pendingSearch" class="form-control form-control-sm" placeholder="Search pending users...">
</div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0 fixed-table-pending" id="pendingTable">
<thead class="table-light">
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Full Name</th>
    <th>Division</th>
    <th>Department/Committee</th>
    <th>Email</th>
    <th>Registered</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($pendingUsers)): ?>
<tr>
    <td colspan="9" class="text-center py-4 text-muted">
        <i class="fas fa-check-circle"></i> No pending users found
    </td>
</tr>
<?php else: ?>
<?php foreach ($pendingUsers as $u): 
    // Determine if this is a student (has department) or proponent/admin (has committee)
    $isStudent = !empty($u['dept_id']);
    $division = $u['division_name'] ?? '—';
    $department = $u['department_name'] ?? '—';
    $committee = $u['committee_name'] ?? '—';
    
    // Combine department and committee for the Dept./Committee column
    $combinedItems = [];
    if ($isStudent && $department !== '—') {
        $combinedItems[] = $department;
    } elseif (!$isStudent && $committee !== '—') {
        $combinedItems[] = $committee;
    }
?>
<tr>
    <td><span class="fw-bold">#<?= $u['id'] ?></span></td>
    <td class="text-truncate" title="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></td>
    <td class="text-truncate" title="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
    <td>
        <?php if ($isStudent && $division !== '—'): ?>
            <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden;">
                <?php 
                $displayDivisions = [$division];
                foreach ($displayDivisions as $div): 
                ?>
                    <span class="badge-item" style="background-color: #6610f2; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($div) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td class="dept-committee-cell" data-items="<?= htmlspecialchars(implode(', ', $combinedItems)) ?>">
        <?php if (!empty($combinedItems)): ?>
            <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden; max-width: 100%;">
                <?php 
                $firstItem = $combinedItems[0];
                $badgeClass = $isStudent ? 'badge-department' : 'badge-committee';
                $remainingCount = count($combinedItems) - 1;
                ?>
                
                <span class="badge-item <?= $badgeClass ?>" 
                    style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 5px 8px; border-radius: 4px; font-size: 11px;">
                    <?= htmlspecialchars($firstItem) ?>
                </span>
                
                <?php if ($remainingCount > 0): ?>
                    <span class="badge-count" style="background-color: #6c757d; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;">
                        +<?= $remainingCount ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td class="text-truncate" title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></td>
    <td><?= date('M d, Y H:i', strtotime($u['created_at'])) ?></td>
    <td>
        <span class="badge-pending">
            <i class="fas fa-clock"></i> Pending
        </span>
    </td>
    <td class="table-actions">
        <a href="javascript:void(0)" 
           onclick="showConfirmModal('Approve <?= htmlspecialchars($u['username']) ?>?', '?act=confirm&id=<?= $u['id'] ?>')" 
           class="btn btn-success btn-sm">
            <i class="fas fa-check"></i> Approve
        </a>
        <a href="javascript:void(0)" 
           onclick="showConfirmModal('Reject and delete <?= htmlspecialchars($u['username']) ?>?', '?act=reject&id=<?= $u['id'] ?>')" 
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

<!-- Confirmed Users Table -->
<div class="card shadow-sm">
<div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="m-0">
        <span class="status-indicator status-confirmed"></span> 
        Confirmed Users (<?= count($confirmedUsers) ?>)
    </h5>
    <!-- Search Bar for Confirmed Table -->
    <div style="width: 250px;">
        <input type="text" id="confirmedSearch" class="form-control form-control-sm" placeholder="Search users...">
    </div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0 fixed-table" id="confirmedTable">
<thead class="table-light">
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Full Name</th>
        <th>Division</th>
        <th>Dept./Committee</th>
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
        <td colspan="10" class="text-center py-4 text-muted">
            <i class="fas fa-users"></i> No confirmed users yet
        </td>
    </tr>
    <?php else: ?>
    <?php foreach ($confirmedUsers as $u): 
    $divisionNames = !empty($u['division_names']) ? explode('||', $u['division_names']) : [];
    $deptNames = !empty($u['department_names']) ? explode('||', $u['department_names']) : []; 
    $committeeNames = !empty($u['committee_names']) ? explode('||', $u['committee_names']) : [];
    
    // Combine department and committee for the new column
    $combinedItems = array_merge($deptNames, $committeeNames);
    ?>
    <tr>
        <td><span class="fw-bold">#<?= $u['id'] ?></span></td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
        <td>
            <?php if (!empty($divisionNames)): ?>
                <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden;">
                    <?php 
                    $displayDivisions = array_slice($divisionNames, 0, 2);
                    $remainingDivisions = count($divisionNames) - 2;
                    foreach ($displayDivisions as $division): 
                    ?>
                        <span class="badge-item" style="background-color: #6610f2; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($division) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($remainingDivisions > 0): ?>
                        <span class="badge-count" style="background-color: #6c757d; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;">
                            +<?= $remainingDivisions ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </td>
        <td class="dept-committee-cell" data-items="<?= htmlspecialchars(implode(', ', $combinedItems)) ?>">
            <?php if (!empty($combinedItems)): ?>
                <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden; max-width: 100%;">
                    <?php 
                    $firstItem = $combinedItems[0];
                    $isDepartment = in_array($firstItem, $deptNames);
                    $badgeClass = $isDepartment ? 'badge-department' : 'badge-committee';
                    $remainingCount = count($combinedItems) - 1;
                    ?>
                    
                    <span class="badge-item <?= $badgeClass ?>" 
                        style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 5px 8px; border-radius: 4px; font-size: 11px;">
                        <?= htmlspecialchars($firstItem) ?>
                    </span>
                    
                    <?php if ($remainingCount > 0): ?>
                        <span class="badge-count" style="background-color: #6c757d; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;">
                            +<?= $remainingCount ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></td>
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
            <a href="?act=edit&id=<?= $u['id'] ?>" class="btn btn-primary btn-sm" title="Edit user">
                <i class="fas fa-edit"></i>
            </a>
            <a href="javascript:void(0)" 
               class="btn btn-danger btn-sm delete-link" 
               title="Delete user"
               data-user-id="<?= $u['id'] ?>"
               data-user-name="<?= htmlspecialchars($u['username']) ?>"
               data-user-role="<?= $u['role'] ?>">
                <i class="fas fa-trash"></i>
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
// ADDED: Custom Delete Modal Logic
const deleteModal = document.getElementById('deleteConfirmModal');
const deleteUserName = document.getElementById('deleteUserName');
const deleteUserRole = document.getElementById('deleteUserRole');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

let pendingDeleteUrl = null;
let pendingActionUrl = null;

// Function to close modal
function closeDeleteModal() {
    deleteModal.classList.remove('active');
    pendingDeleteUrl = null;
    pendingActionUrl = null;
}

// Function to show delete modal
function showDeleteModal(userId, userName, userRole) {
    deleteUserName.textContent = userName;
    deleteUserRole.textContent = 'Role: ' + (userRole === 'admin' ? 'Admin' : (userRole === 'proponent' ? 'Proponent' : 'Student'));
    pendingDeleteUrl = '?act=delete&id=' + userId;
    deleteModal.classList.add('active');
}

// Function to show confirmation modal for approve/reject actions
function showConfirmModal(message, url) {
    deleteUserName.textContent = message;
    deleteUserRole.textContent = 'This action cannot be undone.';
    pendingActionUrl = url;
    deleteModal.classList.add('active');
    // Change header for action confirmations
    const modalHeader = document.querySelector('#deleteConfirmModal .delete-confirm-header h3');
    if (modalHeader) {
        modalHeader.textContent = 'Confirm Action';
    }
    const deleteBtn = document.querySelector('#confirmDeleteBtn');
    if (deleteBtn) {
        deleteBtn.textContent = 'Confirm';
    }
}

// Reset modal to delete mode
function resetModalToDeleteMode() {
    const modalHeader = document.querySelector('#deleteConfirmModal .delete-confirm-header h3');
    if (modalHeader) {
        modalHeader.textContent = 'Delete User';
    }
    const deleteBtn = document.querySelector('#confirmDeleteBtn');
    if (deleteBtn) {
        deleteBtn.textContent = 'Delete User';
    }
}

// Confirm delete button
if (confirmDeleteBtn) {
    confirmDeleteBtn.onclick = function() {
        if (pendingDeleteUrl) {
            window.location.href = pendingDeleteUrl;
        } else if (pendingActionUrl) {
            window.location.href = pendingActionUrl;
        }
    };
}

// Cancel button
if (cancelDeleteBtn) {
    cancelDeleteBtn.onclick = function() {
        closeDeleteModal();
        resetModalToDeleteMode();
    };
}

// Close modal when clicking outside
deleteModal.addEventListener('click', function(e) {
    if (e.target === deleteModal) {
        closeDeleteModal();
        resetModalToDeleteMode();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
        closeDeleteModal();
        resetModalToDeleteMode();
    }
});

// Override delete links for confirmed users
document.querySelectorAll('.delete-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const userId = this.getAttribute('data-user-id');
        const userName = this.getAttribute('data-user-name');
        const userRole = this.getAttribute('data-user-role');
        resetModalToDeleteMode();
        showDeleteModal(userId, userName, userRole);
    });
});

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

// Role-based visibility for Add Form
document.addEventListener('DOMContentLoaded', function() {
    const roleSelectAdd = document.getElementById('roleSelectAdd');
    const studentSectionAdd = document.getElementById('studentSectionAdd');
    const committeeSectionAdd = document.getElementById('committeeSectionAdd');
    
    if (roleSelectAdd && studentSectionAdd && committeeSectionAdd) {
        function toggleSectionsAdd() {
            const selectedRole = roleSelectAdd.value;
            if (selectedRole === 'user') {
                studentSectionAdd.style.display = 'block';
                committeeSectionAdd.style.display = 'none';
            } else {
                studentSectionAdd.style.display = 'none';
                committeeSectionAdd.style.display = 'block';
            }
        }
        toggleSectionsAdd();
        roleSelectAdd.addEventListener('change', toggleSectionsAdd);
    }

    // Role-based visibility for Edit Form
    const roleSelectEdit = document.getElementById('roleSelectEdit');
    const studentSectionEdit = document.getElementById('studentSectionEdit');
    const committeeSectionEdit = document.getElementById('committeeSectionEdit');
    
    if (roleSelectEdit && studentSectionEdit && committeeSectionEdit) {
        function toggleSectionsEdit() {
            const selectedRole = roleSelectEdit.value;
            if (selectedRole === 'user') {
                studentSectionEdit.style.display = 'block';
                committeeSectionEdit.style.display = 'none';
            } else {
                studentSectionEdit.style.display = 'none';
                committeeSectionEdit.style.display = 'block';
            }
        }
        toggleSectionsEdit();
        roleSelectEdit.addEventListener('change', toggleSectionsEdit);
    }

    // Division change handler for Add Form
    const divisionSelectAdd = document.getElementById('divisionSelectAdd');
    const departmentSelectAdd = document.getElementById('departmentSelectAdd');
    
    if (divisionSelectAdd && departmentSelectAdd) {
        divisionSelectAdd.addEventListener('change', function() {
            const divisionId = this.value;
            
            if (divisionId) {
                departmentSelectAdd.disabled = false;
                departmentSelectAdd.innerHTML = '<option value="">Loading...</option>';
                
                fetch(`get_departments.php?division_id=${divisionId}`)
                    .then(response => response.json())
                    .then(data => {
                        departmentSelectAdd.innerHTML = '<option value="">-- Select a Department --</option>';
                        data.forEach(dept => {
                            departmentSelectAdd.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        departmentSelectAdd.innerHTML = '<option value="">Error loading departments</option>';
                    });
            } else {
                departmentSelectAdd.disabled = true;
                departmentSelectAdd.innerHTML = '<option value="">-- First select a division --</option>';
            }
        });
    }

    // Division change handler for Edit Form
    const divisionSelectEdit = document.getElementById('divisionSelectEdit');
    const departmentSelectEdit = document.getElementById('departmentSelectEdit');
    
    if (divisionSelectEdit && departmentSelectEdit) {
        divisionSelectEdit.addEventListener('change', function() {
            const divisionId = this.value;
            
            if (divisionId) {
                departmentSelectEdit.disabled = false;
                departmentSelectEdit.innerHTML = '<option value="">Loading...</option>';
                
                fetch(`get_departments.php?division_id=${divisionId}`)
                    .then(response => response.json())
                    .then(data => {
                        departmentSelectEdit.innerHTML = '<option value="">-- Select a Department --</option>';
                        data.forEach(dept => {
                            departmentSelectEdit.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        departmentSelectEdit.innerHTML = '<option value="">Error loading departments</option>';
                    });
            } else {
                departmentSelectEdit.disabled = true;
                departmentSelectEdit.innerHTML = '<option value="">-- First select a division --</option>';
            }
        });
    }

    // Committee search for Add Form
    const commSearchAdd = document.getElementById('committeeSearchAdd');
    if (commSearchAdd) {
        const commItemsAdd = document.querySelectorAll('#committeeContainerAdd .committee-item');
        
        commSearchAdd.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            commItemsAdd.forEach(item => {
                const commName = item.getAttribute('data-committee-name');
                item.style.display = (searchTerm === '' || commName.includes(searchTerm)) ? '' : 'none';
            });
        });
    }

    // Committee search for Edit Form
    const commSearchEdit = document.getElementById('committeeSearchEdit');
    if (commSearchEdit) {
        const commItemsEdit = document.querySelectorAll('#committeeContainerEdit .committee-item');
        
        commSearchEdit.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            commItemsEdit.forEach(item => {
                const commName = item.getAttribute('data-committee-name');
                item.style.display = (searchTerm === '' || commName.includes(searchTerm)) ? '' : 'none';
            });
        });
    }

    // Pending Table Search
    const pendingSearch = document.getElementById('pendingSearch');
    if (pendingSearch) {
        const pendingTable = document.getElementById('pendingTable');
        const pendingRows = pendingTable ? pendingTable.querySelectorAll('tbody tr') : [];
        
        pendingSearch.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            pendingRows.forEach(function(row) {
                if (row.querySelector('td[colspan="7"]')) return;
                
                const username = row.cells[1]?.textContent.toLowerCase() || '';
                const fullName = row.cells[2]?.textContent.toLowerCase() || '';
                const email = row.cells[3]?.textContent.toLowerCase() || '';
                
                row.style.display = (searchTerm === '' || username.includes(searchTerm) || 
                                    fullName.includes(searchTerm) || email.includes(searchTerm)) ? '' : 'none';
            });
        });
    }

    // Confirmed Table Search
    const confirmedSearch = document.getElementById('confirmedSearch');
    if (confirmedSearch) {
        const confirmedTable = document.getElementById('confirmedTable');
        const confirmedRows = confirmedTable ? confirmedTable.querySelectorAll('tbody tr') : [];
        
        confirmedSearch.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            confirmedRows.forEach(function(row) {
                if (row.querySelector('td[colspan="11"]')) return;
                
                const username = row.cells[1]?.textContent.toLowerCase() || '';
                const fullName = row.cells[2]?.textContent.toLowerCase() || '';
                const division = row.cells[3]?.textContent.toLowerCase() || '';
                const deptCommittee = row.cells[4]?.textContent.toLowerCase() || '';
                const email = row.cells[5]?.textContent.toLowerCase() || '';
                const role = row.cells[6]?.textContent.toLowerCase() || '';
                
                row.style.display = (searchTerm === '' || username.includes(searchTerm) || 
                                    fullName.includes(searchTerm) || division.includes(searchTerm) ||
                                    deptCommittee.includes(searchTerm) || email.includes(searchTerm) || 
                                    role.includes(searchTerm)) ? '' : 'none';
            });
        });
    }

    // Hover tooltip for Dept./Committee column
    const deptCells = document.querySelectorAll('.dept-committee-cell');
    deptCells.forEach(cell => {
        cell.addEventListener('mouseenter', function(e) {
            const items = this.getAttribute('data-items');
            if (!items || items === '') return;
            
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            
            const itemList = items.split(', ');
            let formattedItems = '';
            itemList.forEach(item => {
                formattedItems += item + '<br>';
            });
            
            tooltip.innerHTML = formattedItems;
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - 10) + 'px';
            tooltip.style.left = (rect.left + (rect.width / 2)) + 'px';
            tooltip.style.transform = 'translateX(-50%) translateY(-100%)';
        });
        
        cell.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
});

function removeDepartment(btn, deptId) {
    btn.parentElement.remove();
}
</script>

</body>
</html>