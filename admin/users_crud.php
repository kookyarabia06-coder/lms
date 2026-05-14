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
    
    if (isset($_GET['form']) && $_GET['form'] === 'add') {
        header('Location: users_crud.php?act=addform');
    } elseif (isset($_GET['form']) && $_GET['form'] === 'edit' && isset($_GET['user_id'])) {
        header('Location: users_crud.php?act=edit&id=' . $_GET['user_id']);
    } else {
        header('Location: users_crud.php');
    }
    exit;
}

// =====================================
// REPORT DATA HANDLERS (AJAX)
// =====================================

// Handle AJAX Get Filtered Report Data (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_user_report_data']) && (is_admin() || is_superadmin())) {
    header('Content-Type: application/json');
    try {
        $year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;
        $employment = $_GET['employment'] ?? '';
        $committee_id = isset($_GET['committee_id']) && !empty($_GET['committee_id']) ? (int)$_GET['committee_id'] : null;
        $division_id = isset($_GET['division_id']) && !empty($_GET['division_id']) ? (int)$_GET['division_id'] : null;
        $dept_id = isset($_GET['dept_id']) && !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        
        $where_clauses = ["u.status = 'confirmed'"];
        $params = [];
        
        if ($year) { $where_clauses[] = "YEAR(u.created_at) = ?"; $params[] = $year; }
        if ($month) { $where_clauses[] = "MONTH(u.created_at) = ?"; $params[] = $month; }
        if ($employment) { $where_clauses[] = "u.employment = ?"; $params[] = $employment; }
        if ($committee_id) { $where_clauses[] = "c.id = ?"; $params[] = $committee_id; }
        if ($division_id) { $where_clauses[] = "dept.id = ?"; $params[] = $division_id; }
        if ($dept_id) { $where_clauses[] = "d.id = ?"; $params[] = $dept_id; }
        
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        $query = "
            SELECT 
                u.id,
                u.username,
                CONCAT(u.fname, ' ', u.lname) as full_name,
                u.email,
                u.role,
                u.employment,
                DATE_FORMAT(u.created_at, '%b %d, %Y') as joined_date,
                GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as department_names,
                GROUP_CONCAT(DISTINCT dept.name SEPARATOR ', ') as division_names,
                GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as committee_names
            FROM users u
            LEFT JOIN user_departments ud ON u.id = ud.user_id
            LEFT JOIN depts d ON ud.dept_id = d.id
            LEFT JOIN departments dept ON d.department_id = dept.id
            LEFT JOIN committees c ON ud.committee_id = c.id
            $where_sql
            GROUP BY u.id
            ORDER BY u.created_at DESC
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

// Handle AJAX Get Filter Options (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_user_filter_options']) && (is_admin() || is_superadmin())) {
    header('Content-Type: application/json');
    try {
        // Get years
        $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE status = 'confirmed' ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($years)) $years = [date('Y')];
        
        // Get divisions
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        $divisions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get departments
        $stmt = $pdo->query("SELECT id, name, department_id FROM depts ORDER BY name");
        $departments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get committees
        $stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
        $committees_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'years' => $years,
            'divisions' => $divisions_data,
            'departments' => $departments_data,
            'committees' => $committees_data
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
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
    $employment = $_POST['employment'] ?? 'permanent';

    $selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
    if (!is_array($selectedDepts)) {
        $selectedDepts = [$selectedDepts];
    }
    $selectedDepts = array_filter($selectedDepts, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedDepts = array_values($selectedDepts);

    $selectedCommittees = isset($_POST['committees']) ? $_POST['committees'] : [];
    if (!is_array($selectedCommittees)){
        $selectedCommittees = [$selectedCommittees];
    }
    $selectedCommittees = array_filter($selectedCommittees, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedCommittees = array_values($selectedCommittees);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header('Location: users_crud.php?act=addform');
        exit;
    }

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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password, fname, lname, email, role, employment, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())"
        );
        $stmt->execute([$username, $hash, $fname, $lname, $email, $role, $employment]);

        $newUserId = $pdo->lastInsertId();

        if (!empty($selectedDepts)) {
            $placeholders = implode(',', array_fill(0, count($selectedDepts), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM depts WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedDepts);
            $validDeptIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($validDeptIds)) {
                $deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, dept_id) VALUES (?, ?)");
                foreach ($validDeptIds as $deptId) {
                    $deptStmt->execute([$newUserId, $deptId]);
                }
            }
        }

        if (!empty($selectedCommittees)) {
            $placeholders = implode(',', array_fill(0, count($selectedCommittees), '?'));
            $checkStmt = $pdo->prepare("SELECT id FROM committees WHERE id IN ($placeholders)");
            $checkStmt->execute($selectedCommittees);
            $validCommIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($validCommIds)) {
                $commStmt = $pdo->prepare("INSERT INTO user_departments (user_id, committee_id) VALUES (?, ?)");
                foreach ($validCommIds as $commId) {
                    $commStmt->execute([$newUserId, $commId]);
                }
            }
        }

        $recipientName = !empty($fname) ? $fname : $username;
        if (!empty($lname)) {
            $recipientName .= ' ' . $lname;
        }

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
    $employment = $_POST['employment'] ?? 'permanent';

    if (!in_array($employment, ['permanent', 'job_order'])) {
        $employment = 'permanent';
    }

    $selectedDepts = isset($_POST['departments']) ? $_POST['departments'] : [];
    if (!is_array($selectedDepts)) {
        $selectedDepts = [$selectedDepts];
    }
    $selectedDepts = array_filter($selectedDepts, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedDepts = array_values($selectedDepts);

    $selectedCommittees = isset($_POST['committees']) ? $_POST['committees'] : [];
    if (!is_array($selectedCommittees)) {
        $selectedCommittees = [$selectedCommittees];
    }
    $selectedCommittees = array_filter($selectedCommittees, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedCommittees = array_values($selectedCommittees);

    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmail->execute([$email, $id]);
    if ($checkEmail->fetch()) {
        $_SESSION['error'] = "Email already exists for another user";
        header('Location: users_crud.php?act=edit&id=' . $id);
        exit;
    }

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
            $sql = "UPDATE users SET username=?, fname=?, lname=?, email=?, role=?, employment=?, password=? WHERE id=?";
            $params = [$username, $fname, $lname, $email, $role, $employment, $hash, $id];
        } else {
            $sql = "UPDATE users SET username=?, fname=?, lname=?, email=?, role=?, employment=? WHERE id=?";
            $params = [$username, $fname, $lname, $email, $role, $employment, $id];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?")->execute([$id]);

        if (!empty($selectedDepts)) {
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

        if (!empty($selectedCommittees)) {
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

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        exit('User not found');
    }

    $deptStmt = $pdo->prepare("SELECT dept_id FROM user_departments WHERE user_id = ? AND dept_id IS NOT NULL");
    $deptStmt->execute([$id]);
    $userDepts = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    $user['department_ids'] = $userDepts ?: [];

    $commStmt = $pdo->prepare("SELECT committee_id FROM user_departments WHERE user_id = ? AND committee_id IS NOT NULL");
    $commStmt->execute([$id]);
    $userCommittees = $commStmt->fetchAll(PDO::FETCH_COLUMN);
    $user['committee_ids'] = $userCommittees ?: [];

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
           GROUP_CONCAT(DISTINCT d.id SEPARATOR '||') as dept_ids,
           GROUP_CONCAT(DISTINCT d.name SEPARATOR '||') as department_names,
           GROUP_CONCAT(DISTINCT dept.id SEPARATOR '||') as division_ids,
           GROUP_CONCAT(DISTINCT dept.name SEPARATOR '||') as division_names,
           GROUP_CONCAT(DISTINCT c.id SEPARATOR '||') as committee_ids,
           GROUP_CONCAT(DISTINCT c.name SEPARATOR '||') as committee_names
    FROM users u
    LEFT JOIN user_departments ud ON u.id = ud.user_id
    LEFT JOIN depts d ON ud.dept_id = d.id
    LEFT JOIN departments dept ON d.department_id = dept.id
    LEFT JOIN committees c ON ud.committee_id = c.id
    WHERE u.status = 'pending'
    GROUP BY u.id
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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    .delete-confirm-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .delete-confirm-header i { font-size: 22px; color: #dc3545; }
    .delete-confirm-header h3 { font-size: 18px; font-weight: 600; margin: 0; color: #111827; }
    .delete-confirm-body { padding: 20px 24px; }
    .delete-confirm-body p { color: #4b5563; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
    .delete-confirm-body .user-info { background: #f9fafb; padding: 12px; border-radius: 8px; margin: 12px 0; border-left: 3px solid #dc3545; }
    .delete-confirm-body .user-info strong { display: block; color: #0f172a; margin-bottom: 4px; }
    .delete-confirm-body .user-info small { color: #64748b; font-size: 12px; }
    .warning-note { background: #fef3c7; padding: 12px; border-radius: 8px; margin-top: 16px; font-size: 13px; color: #92400e; display: flex; align-items: center; gap: 8px; }
    .warning-note i { color: #f59e0b; }
    .delete-confirm-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
    .delete-confirm-footer button { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; }
    .delete-confirm-footer .btn-cancel-delete { background: #f3f4f6; color: #374151; }
    .delete-confirm-footer .btn-cancel-delete:hover { background: #e5e7eb; }
    .delete-confirm-footer .btn-confirm-delete { background: #dc3545; color: white; }
    .delete-confirm-footer .btn-confirm-delete:hover { background: #c82333; }
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
<div class="d-flex gap-2">
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#reportModal">
        <i class="fas fa-chart-bar me-2"></i>Generate Report
    </button>
    <?php if($act !== 'addform'): ?>
    <a href="?act=addform" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New User
    </a>
    <?php endif; ?>
</div>
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
<option value="user">Employee</option>
<option value="proponent">Proponent</option>
<option value="admin">Admin</option>
</select>
</div>
<div class="col-md-6 mb-3">
    <label>Employment Status</label>
    <select name="employment" class="form-control" required>
        <option value="permanent">Permanent</option>
        <option value="job_order">Job Order</option>
    </select>
</div>
</div>

<div class="mb-3" id="studentSectionAdd" style="display: none;">
    <div class="card p-3 mb-3">
        <h6 class="card-title mb-3">Select Division and Department</h6>
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
        <div class="mb-3">
            <label class="form-label fw-bold">Select Department</label>
            <select class="form-control" id="departmentSelectAdd" name="departments[]" disabled>
                <option value="">-- First select a division --</option>
            </select>
            <small class="text-muted">Choose the department for this user</small>
        </div>
        <div class="mt-2" id="selectedDepartmentsAdd">
            <label class="form-label">Selected Departments:</label>
            <div class="d-flex flex-wrap gap-2" id="selectedDeptsListAdd"></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addDepartmentBtnAdd" style="display: none;">
            <i class="fas fa-plus"></i> Add Another Department
        </button>
    </div>
</div>

<div class="mb-3" id="committeeSectionAdd" style="display: none;">
    <div class="card p-3">
        <h6 class="card-title mb-3">Select Committees</h6>
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
<option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Employee</option>
<option value="proponent" <?= $user['role'] === 'proponent' ? 'selected' : '' ?>>Proponent</option>
<option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
</select>
</div>
<div class="col-md-6 mb-3">
    <label>Employment Status</label>
    <select name="employment" class="form-control">
        <option value="permanent" <?= ($user['employment'] ?? 'permanent') === 'permanent' ? 'selected' : '' ?>>Permanent</option>
        <option value="job_order" <?= ($user['employment'] ?? '') === 'job_order' ? 'selected' : '' ?>>Job Order</option>
    </select>
</div>
</div>

<div class="mb-3" id="studentSectionEdit" style="display: none;">
    <div class="card p-3 mb-3">
        <h6 class="card-title mb-3">Select Division and Department</h6>
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

<div class="mb-3" id="committeeSectionEdit" style="display: none;">
    <div class="card p-3">
        <h6 class="card-title mb-3">Select Committees</h6>
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

<!-- Add Division Modal -->
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

<!-- Edit Division Modal -->
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

<!-- ===================================== -->
<!-- GENERATE REPORT MODAL                  -->
<!-- ===================================== -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);">
                <h5 class="modal-title" id="reportModalLabel">
                    <i class="fas fa-chart-line me-2"></i>Generate User Report
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Year</label>
                        <select id="reportYear" class="form-select">
                            <option value="">All Years</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Month</label>
                        <select id="reportMonth" class="form-select">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Employment</label>
                        <select id="reportEmployment" class="form-select">
                            <option value="">All</option>
                            <option value="permanent">Permanent</option>
                            <option value="job_order">Job Order</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Committee</label>
                        <select id="reportCommittee" class="form-select">
                            <option value="">All Committees</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Division</label>
                        <select id="reportDivision" class="form-select" onchange="updateReportDepartments()">
                            <option value="">All Divisions</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Department</label>
                        <select id="reportDepartment" class="form-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end mb-3">
                    <button id="generateReportPreviewBtn" class="btn btn-success">
                        <i class="fas fa-search me-1"></i> Generate
                    </button>
                </div>
                <div id="reportPreviewContainer" style="display: none;">
                    <div id="reportPreviewContent"></div>
                    <div class="d-flex gap-2 mt-3 justify-content-end">
                        <button class="btn btn-outline-secondary" onclick="printGeneratedReport()">
                            <i class="fas fa-print me-1"></i> Print
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

<!-- Delete Confirmation Modal -->
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
    <th>Employment</th>
    <th>Registered</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($pendingUsers)): ?>
<tr>
    <td colspan="10" class="text-center py-4 text-muted">
        <i class="fas fa-check-circle"></i> No pending users found
    </td>
</tr>
<?php else: ?>
<?php foreach ($pendingUsers as $u): 
    $deptIds = !empty($u['dept_ids']) ? explode('||', $u['dept_ids']) : [];
    $deptNames = !empty($u['department_names']) ? explode('||', $u['department_names']) : [];
    $divisionNames = !empty($u['division_names']) ? explode('||', $u['division_names']) : [];
    $committeeIds = !empty($u['committee_ids']) ? explode('||', $u['committee_ids']) : [];
    $committeeNames = !empty($u['committee_names']) ? explode('||', $u['committee_names']) : [];
    $isStudent = !empty($deptIds);
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
                foreach ($displayDivisions as $div): 
                ?>
                    <span class="badge-item" style="background-color: #6610f2; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($div) ?>
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
    <td><?= (($u['employment'] ?? 'permanent') === 'job_order') ? 'Job Order' : 'Permanent' ?></td>
    <td><?= date('M d, Y H:i', strtotime($u['created_at'])) ?></td>
    <td><span class="badge-pending"><i class="fas fa-clock"></i> Pending</span></td>
    <td class="table-actions">
        <a href="javascript:void(0)" onclick="showConfirmModal('Approve <?= htmlspecialchars($u['username']) ?>?', '?act=confirm&id=<?= $u['id'] ?>')" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</a>
        <a href="javascript:void(0)" onclick="showConfirmModal('Reject and delete <?= htmlspecialchars($u['username']) ?>?', '?act=reject&id=<?= $u['id'] ?>')" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></a>
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
    <h5 class="m-0"><span class="status-indicator status-confirmed"></span> Confirmed Users (<?= count($confirmedUsers) ?>)</h5>
    <div style="width: 250px;"><input type="text" id="confirmedSearch" class="form-control form-control-sm" placeholder="Search users..."></div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0 fixed-table" id="confirmedTable">
<thead class="table-light">
    <tr>
        <th>ID</th><th>Username</th><th>Full Name</th><th>Division</th><th>Dept./Committee</th>
        <th>Email</th><th>Role</th><th>Employment</th><th>Joined</th><th>Status</th><th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php if (empty($confirmedUsers)): ?>
    <tr><td colspan="11" class="text-center py-4 text-muted"><i class="fas fa-users"></i> No confirmed users yet</td></tr>
    <?php else: ?>
    <?php foreach ($confirmedUsers as $u): 
    $divisionNames = !empty($u['division_names']) ? explode('||', $u['division_names']) : [];
    $deptNames = !empty($u['department_names']) ? explode('||', $u['department_names']) : []; 
    $committeeNames = !empty($u['committee_names']) ? explode('||', $u['committee_names']) : [];
    $combinedItems = array_merge($deptNames, $committeeNames);
    ?>
    <tr>
        <td><span class="fw-bold">#<?= $u['id'] ?></span></td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
        <td>
            <?php if (!empty($divisionNames)): ?>
                <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden;">
                    <?php $displayDivisions = array_slice($divisionNames, 0, 2); $remainingDivisions = count($divisionNames) - 2;
                    foreach ($displayDivisions as $division): ?>
                        <span class="badge-item" style="background-color: #6610f2; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($division) ?></span>
                    <?php endforeach; ?>
                    <?php if ($remainingDivisions > 0): ?><span class="badge-count" style="background-color: #6c757d; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;">+<?= $remainingDivisions ?></span><?php endif; ?>
                </div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
        </td>
        <td class="dept-committee-cell" data-items="<?= htmlspecialchars(implode(', ', $combinedItems)) ?>">
            <?php if (!empty($combinedItems)): ?>
                <div class="badge-container" style="display: flex; gap: 4px; overflow: hidden; max-width: 100%;">
                    <?php $firstItem = $combinedItems[0]; $isDepartment = in_array($firstItem, $deptNames); $badgeClass = $isDepartment ? 'badge-department' : 'badge-committee'; $remainingCount = count($combinedItems) - 1; ?>
                    <span class="badge-item <?= $badgeClass ?>" style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 5px 8px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars($firstItem) ?></span>
                    <?php if ($remainingCount > 0): ?><span class="badge-count" style="background-color: #6c757d; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap;">+<?= $remainingCount ?></span><?php endif; ?>
                </div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
        </td>
        <td class="text-truncate" title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></td>
        <td>
            <?php if ($u['role'] === 'admin'): ?><span class="badge bg-danger">Admin</span>
            <?php elseif ($u['role'] === 'proponent'): ?><span class="badge bg-info">Proponent</span>
            <?php elseif ($u['role'] === 'superadmin'): ?><span class="badge bg-secondary">SuperAdmin</span>
            <?php else: ?><span class="badge bg-success">Employee</span><?php endif; ?>
        </td>
        <td>
            <?php if (($u['employment'] ?? 'permanent') === 'job_order'): ?><span class="badge bg-warning text-dark">Job Order</span>
            <?php else: ?><span class="badge bg-primary">Permanent</span><?php endif; ?>
        </td>
        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
        <td><span class="badge-confirmed"><i class="fas fa-check-circle"></i> Confirmed</span></td>
        <td class="table-actions">
            <a href="?act=edit&id=<?= $u['id'] ?>" class="btn btn-primary btn-sm" title="Edit user"><i class="fas fa-edit"></i></a>
            <a href="javascript:void(0)" class="btn btn-danger btn-sm delete-link" title="Delete user"
               data-user-id="<?= $u['id'] ?>" data-user-name="<?= htmlspecialchars($u['username']) ?>" data-user-role="<?= $u['role'] ?>"><i class="fas fa-trash"></i></a>
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
// Delete Modal Logic
const deleteModal = document.getElementById('deleteConfirmModal');
const deleteUserName = document.getElementById('deleteUserName');
const deleteUserRole = document.getElementById('deleteUserRole');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
let pendingDeleteUrl = null;
let pendingActionUrl = null;

function closeDeleteModal() { deleteModal.classList.remove('active'); pendingDeleteUrl = null; pendingActionUrl = null; }

function showDeleteModal(userId, userName, userRole) {
    deleteUserName.textContent = userName;
    deleteUserRole.textContent = 'Role: ' + (userRole === 'admin' ? 'Admin' : (userRole === 'proponent' ? 'Proponent' : 'Employee'));
    pendingDeleteUrl = '?act=delete&id=' + userId;
    deleteModal.classList.add('active');
}

function showConfirmModal(message, url) {
    deleteUserName.textContent = message;
    deleteUserRole.textContent = 'This action cannot be undone.';
    pendingActionUrl = url;
    deleteModal.classList.add('active');
    const modalHeader = document.querySelector('#deleteConfirmModal .delete-confirm-header h3');
    if (modalHeader) modalHeader.textContent = 'Confirm Action';
    const deleteBtn = document.querySelector('#confirmDeleteBtn');
    if (deleteBtn) deleteBtn.textContent = 'Confirm';
}

function resetModalToDeleteMode() {
    const modalHeader = document.querySelector('#deleteConfirmModal .delete-confirm-header h3');
    if (modalHeader) modalHeader.textContent = 'Delete User';
    const deleteBtn = document.querySelector('#confirmDeleteBtn');
    if (deleteBtn) deleteBtn.textContent = 'Delete User';
}

if (confirmDeleteBtn) { confirmDeleteBtn.onclick = function() { if (pendingDeleteUrl) window.location.href = pendingDeleteUrl; else if (pendingActionUrl) window.location.href = pendingActionUrl; }; }
if (cancelDeleteBtn) { cancelDeleteBtn.onclick = function() { closeDeleteModal(); resetModalToDeleteMode(); }; }
deleteModal.addEventListener('click', function(e) { if (e.target === deleteModal) { closeDeleteModal(); resetModalToDeleteMode(); } });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && deleteModal.classList.contains('active')) { closeDeleteModal(); resetModalToDeleteMode(); } });

document.querySelectorAll('.delete-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        showDeleteModal(this.getAttribute('data-user-id'), this.getAttribute('data-user-name'), this.getAttribute('data-user-role'));
        resetModalToDeleteMode();
    });
});

function enablePassword() { document.getElementById('passwordField').disabled = false; document.getElementById('passwordField').focus(); }

setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => { alert.style.transition = 'opacity 0.5s'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); });
}, 3000);

// ========== REPORT FUNCTIONS ==========
let allReportDepartments = [];
let allReportDivisions = [];
let allReportCommittees = [];

function loadReportFilterOptions() {
    fetch('users_crud.php?get_user_filter_options=1')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const yrSelect = document.getElementById('reportYear');
                yrSelect.innerHTML = '<option value="">All Years</option>';
                d.years.forEach(y => { yrSelect.innerHTML += `<option value="${y}">${y}</option>`; });
                
                const divSelect = document.getElementById('reportDivision');
                divSelect.innerHTML = '<option value="">All Divisions</option>';
                d.divisions.forEach(div => { divSelect.innerHTML += `<option value="${div.id}">${escapeHtml(div.name)}</option>`; });
                allReportDivisions = d.divisions;
                allReportDepartments = d.departments;
                
                const commSelect = document.getElementById('reportCommittee');
                commSelect.innerHTML = '<option value="">All Committees</option>';
                d.committees.forEach(comm => { commSelect.innerHTML += `<option value="${comm.id}">${escapeHtml(comm.name)}</option>`; });
                allReportCommittees = d.committees;
            }
        });
}

function updateReportDepartments() {
    const divisionId = document.getElementById('reportDivision').value;
    const deptSelect = document.getElementById('reportDepartment');
    deptSelect.innerHTML = '<option value="">All Departments</option>';
    if (divisionId) {
        allReportDepartments.filter(d => d.department_id == divisionId).forEach(d => {
            deptSelect.innerHTML += `<option value="${d.id}">${escapeHtml(d.name)}</option>`;
        });
    } else {
        allReportDepartments.forEach(d => {
            deptSelect.innerHTML += `<option value="${d.id}">${escapeHtml(d.name)}</option>`;
        });
    }
}

function generateReportPreview() {
    const year = document.getElementById('reportYear').value;
    const month = document.getElementById('reportMonth').value;
    const employment = document.getElementById('reportEmployment').value;
    const committeeId = document.getElementById('reportCommittee').value;
    const divisionId = document.getElementById('reportDivision').value;
    const deptId = document.getElementById('reportDepartment').value;
    
    const container = document.getElementById('reportPreviewContainer');
    const content = document.getElementById('reportPreviewContent');
    container.style.display = 'block';
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Generating report...</p></div>';
    
    let url = `users_crud.php?get_user_report_data=1&year=${year}&month=${month}&employment=${employment}&committee_id=${committeeId}&division_id=${divisionId}&dept_id=${deptId}`;
    
    fetch(url)
        .then(r => r.json())
        .then(d => {
            if (d.success && d.reports.length > 0) {
                let filtersUsed = [];
                filtersUsed.push('Year: ' + (year || 'All'));
                filtersUsed.push('Month: ' + (month ? new Date(2000, month - 1).toLocaleString('en-US', { month: 'long' }) : 'All'));
                filtersUsed.push('Employment: ' + (employment ? (employment === 'permanent' ? 'Permanent' : 'Job Order') : 'All'));
                
                const commSelect = document.getElementById('reportCommittee');
                filtersUsed.push('Committee: ' + (committeeId ? commSelect.options[commSelect.selectedIndex].text : 'All'));
                
                const divSelect = document.getElementById('reportDivision');
                filtersUsed.push('Division: ' + (divisionId ? divSelect.options[divSelect.selectedIndex].text : 'All'));
                
                const deptSelect = document.getElementById('reportDepartment');
                filtersUsed.push('Department: ' + (deptId ? deptSelect.options[deptSelect.selectedIndex].text : 'All'));
                
                let html = `<div style="text-align:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #43a047;">
                    <h4 style="margin:0 0 5px;font-size:18px;font-weight:600;color:#2e7d32;">User Report</h4>
                    <p style="margin:0;font-size:13px;color:#666;">${filtersUsed.join(' | ')}</p>
                </div>`;
                
                html += `<table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr style="background:#f5f5f5;border-bottom:2px solid #ccc;">
                        <th style="padding:8px 10px;text-align:left;">ID</th>
                        <th style="padding:8px 10px;text-align:left;">Username</th>
                        <th style="padding:8px 10px;text-align:left;">Full Name</th>
                        <th style="padding:8px 10px;text-align:left;">Email</th>
                        <th style="padding:8px 10px;text-align:left;">Role</th>
                        <th style="padding:8px 10px;text-align:center;">Employment</th>
                        <th style="padding:8px 10px;text-align:left;">Division</th>
                        <th style="padding:8px 10px;text-align:left;">Department</th>
                        <th style="padding:8px 10px;text-align:left;">Committee</th>
                        <th style="padding:8px 10px;text-align:left;">Joined</th>
                    </tr></thead><tbody>`;
                
                d.reports.forEach(report => {
                    html += `<tr style="border-bottom:1px solid #eee;">
                        <td style="padding:6px 10px;">#${report.id}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.username)}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.full_name)}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.email)}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.role === 'user' ? 'Employee' : report.role === 'proponent' ? 'Proponent' : report.role === 'superadmin' ? 'SuperAdmin' : report.role)}</td>
                        <td style="padding:6px 10px;text-align:center;">${report.employment === 'job_order' ? 'Job Order' : 'Permanent'}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.division_names || '—')}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.department_names || '—')}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.committee_names || '—')}</td>
                        <td style="padding:6px 10px;">${escapeHtml(report.joined_date)}</td>
                    </tr>`;
                });
                
                html += `</tbody></table><p style="text-align:right;font-size:12px;color:#888;margin-top:12px;padding-top:8px;border-top:1px solid #eee;">Total Records: <strong>${d.reports.length}</strong></p>`;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3" style="color:#dee2e6;"></i><p style="font-size:15px;">No records found matching your filters</p></div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error generating report.</div>';
        });
}

function printGeneratedReport() {
    const content = document.getElementById('reportPreviewContent').innerHTML;
    const w = window.open('', '_blank', 'width=1000,height=700');
    w.document.write(`<!DOCTYPE html><html><head><title>User Report</title><style>body{font-family:Arial,sans-serif;padding:30px;color:#222;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:6px 8px;}th{background:#f5f5f5;}@media print{body{padding:15px;}}</style></head><body>${content}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 300);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('generateReportPreviewBtn')?.addEventListener('click', generateReportPreview);
document.getElementById('reportModal')?.addEventListener('show.bs.modal', () => {
    loadReportFilterOptions();
    document.getElementById('reportPreviewContainer').style.display = 'none';
});

// Role-based visibility
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
            } else if (selectedRole === 'proponent') {
                studentSectionAdd.style.display = 'none';
                committeeSectionAdd.style.display = 'block';
            } else {
                studentSectionAdd.style.display = 'none';
                committeeSectionAdd.style.display = 'none';
            }
        }
        toggleSectionsAdd();
        roleSelectAdd.addEventListener('change', toggleSectionsAdd);
    }

    const roleSelectEdit = document.getElementById('roleSelectEdit');
    const studentSectionEdit = document.getElementById('studentSectionEdit');
    const committeeSectionEdit = document.getElementById('committeeSectionEdit');
    
    if (roleSelectEdit && studentSectionEdit && committeeSectionEdit) {
        function toggleSectionsEdit() {
            const selectedRole = roleSelectEdit.value;
            if (selectedRole === 'user') {
                studentSectionEdit.style.display = 'block';
                committeeSectionEdit.style.display = 'none';
            } else if (selectedRole === 'proponent') {
                studentSectionEdit.style.display = 'none';
                committeeSectionEdit.style.display = 'block';
            } else {
                studentSectionEdit.style.display = 'none';
                committeeSectionEdit.style.display = 'none';
            }
        }
        toggleSectionsEdit();
        roleSelectEdit.addEventListener('change', toggleSectionsEdit);
    }

    const divisionSelectAdd = document.getElementById('divisionSelectAdd');
    const departmentSelectAdd = document.getElementById('departmentSelectAdd');
    if (divisionSelectAdd && departmentSelectAdd) {
        divisionSelectAdd.addEventListener('change', function() {
            const divisionId = this.value;
            if (divisionId) {
                departmentSelectAdd.disabled = false;
                departmentSelectAdd.innerHTML = '<option value="">Loading...</option>';
                fetch(`get_departments.php?division_id=${divisionId}`)
                    .then(r => r.json())
                    .then(data => {
                        departmentSelectAdd.innerHTML = '<option value="">-- Select a Department --</option>';
                        data.forEach(dept => { departmentSelectAdd.innerHTML += `<option value="${dept.id}">${dept.name}</option>`; });
                    })
                    .catch(() => { departmentSelectAdd.innerHTML = '<option value="">Error loading</option>'; });
            } else {
                departmentSelectAdd.disabled = true;
                departmentSelectAdd.innerHTML = '<option value="">-- First select a division --</option>';
            }
        });
    }

    const divisionSelectEdit = document.getElementById('divisionSelectEdit');
    const departmentSelectEdit = document.getElementById('departmentSelectEdit');
    if (divisionSelectEdit && departmentSelectEdit) {
        divisionSelectEdit.addEventListener('change', function() {
            const divisionId = this.value;
            if (divisionId) {
                departmentSelectEdit.disabled = false;
                departmentSelectEdit.innerHTML = '<option value="">Loading...</option>';
                fetch(`get_departments.php?division_id=${divisionId}`)
                    .then(r => r.json())
                    .then(data => {
                        departmentSelectEdit.innerHTML = '<option value="">-- Select a Department --</option>';
                        data.forEach(dept => { departmentSelectEdit.innerHTML += `<option value="${dept.id}">${dept.name}</option>`; });
                    })
                    .catch(() => { departmentSelectEdit.innerHTML = '<option value="">Error loading</option>'; });
            } else {
                departmentSelectEdit.disabled = true;
                departmentSelectEdit.innerHTML = '<option value="">-- First select a division --</option>';
            }
        });
    }

    // Committee search
    document.getElementById('committeeSearchAdd')?.addEventListener('keyup', function() {
        const t = this.value.toLowerCase().trim();
        document.querySelectorAll('#committeeContainerAdd .committee-item').forEach(item => {
            item.style.display = (t === '' || item.getAttribute('data-committee-name').includes(t)) ? '' : 'none';
        });
    });
    document.getElementById('committeeSearchEdit')?.addEventListener('keyup', function() {
        const t = this.value.toLowerCase().trim();
        document.querySelectorAll('#committeeContainerEdit .committee-item').forEach(item => {
            item.style.display = (t === '' || item.getAttribute('data-committee-name').includes(t)) ? '' : 'none';
        });
    });

    // Table searches
    document.getElementById('pendingSearch')?.addEventListener('keyup', function() {
        const t = this.value.toLowerCase().trim();
        document.querySelectorAll('#pendingTable tbody tr').forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            const u = row.cells[1]?.textContent.toLowerCase() || '';
            const n = row.cells[2]?.textContent.toLowerCase() || '';
            const e = row.cells[5]?.textContent.toLowerCase() || '';
            row.style.display = (t === '' || u.includes(t) || n.includes(t) || e.includes(t)) ? '' : 'none';
        });
    });
    document.getElementById('confirmedSearch')?.addEventListener('keyup', function() {
        const t = this.value.toLowerCase().trim();
        document.querySelectorAll('#confirmedTable tbody tr').forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            const u = row.cells[1]?.textContent.toLowerCase() || '';
            const n = row.cells[2]?.textContent.toLowerCase() || '';
            const d = row.cells[3]?.textContent.toLowerCase() || '';
            const dc = row.cells[4]?.textContent.toLowerCase() || '';
            const e = row.cells[5]?.textContent.toLowerCase() || '';
            const r = row.cells[6]?.textContent.toLowerCase() || '';
            const emp = row.cells[7]?.textContent.toLowerCase() || '';
            row.style.display = (t === '' || u.includes(t) || n.includes(t) || d.includes(t) || dc.includes(t) || e.includes(t) || r.includes(t) || emp.includes(t)) ? '' : 'none';
        });
    });

    // Hover tooltip
    document.querySelectorAll('.dept-committee-cell').forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            const items = this.getAttribute('data-items');
            if (!items) return;
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.innerHTML = items.split(', ').join('<br>');
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - 10) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2) + 'px';
            tooltip.style.transform = 'translateX(-50%) translateY(-100%)';
        });
        cell.addEventListener('mouseleave', function() {
            if (this._tooltip) { this._tooltip.remove(); this._tooltip = null; }
        });
    });
});

function removeDepartment(btn, deptId) { btn.parentElement.remove(); }
</script>

</body>
</html>