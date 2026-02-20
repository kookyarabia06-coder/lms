<?php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';
require_login();
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
// nilinis kona ung code para readable dont remoe my comment
//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////

// Only admin can access this page
if (!is_admin() && !is_superadmin()) {
    echo 'Admin only';
    exit;
}

$act = $_GET['act'] ?? '';

// ADD USER
// ADD USER WITH EMAIL NOTIFICATION
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start session for messages
    // session_start();
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fname    = $_POST['fname'];
    $lname    = $_POST['lname'];
    $email    = $_POST['email'];
    $role     = $_POST['role'];
    $departments = $_POST['departments'] ?? null;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header('Location: users_crud.php?act=addform');
        exit;
    }

    // Check if username or email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        $_SESSION['error'] = "Username or email already exists";
        header('Location: users_crud.php?act=addform');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert user
        $stmt = $pdo->prepare(
            "INSERT INTO users (username,password,fname,lname,email,departments,role,status,created_at)
             VALUES (?,?,?,?,?,?,?,'confirmed',NOW())"
        );
        $stmt->execute([$username, $hash, $fname, $lname, $email, $departments, $role]);
        


        
        // Get the new user ID
        $newUserId = $pdo->lastInsertId();
        
        // Prepare recipient name
        $recipientName = !empty($fname) ? $fname : $username;
        if (!empty($lname)) {
            $recipientName .= ' ' . $lname;
        }
        
        // SEND WELCOME EMAIL
        require_once __DIR__ . '/../inc/mailerconfigadmin.php';
        
        // You need to create this function or modify existing one
        $emailResult = sendConfirmationEmail($email, $recipientName, $username, $password);
        
        if ($emailResult['success']) {
            $pdo->commit();
            $_SESSION['success'] = "User added successfully and welcome email sent to $email";
        } else {
            // Email failed but user was created - decide if you want to rollback
            // For now, we'll still commit but show warning
            $pdo->commit();
            $_SESSION['warning'] = "User added but email failed: " . $emailResult['message'];
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
    $id       = (int)$_POST['id'];
    $username = $_POST['username'];
    $password = $_POST['password'] ?? '';
    $fname    = $_POST['fname'];
    $lname    = $_POST['lname'];
    $email    = $_POST['email'];
    $role     = $_POST['role'];

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
    $stmt->execute($params);-

    header('Location: ../inc/mailerconfigadmin.php');
    exit;
}

// DELETE USER
if ($act === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
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
}

// CONFIRM USER STATUS
if (isset($_GET['act']) && $_GET['act'] === 'confirm' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current) {
        // Only update if not already confirmed
        if ($current['status'] !== 'confirmed') {
            $update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $update->execute(['confirmed', $id]);
        }
      
        header('Location: users_crud.php');
        exit;
    } else {
        exit('User not found');
    }
}

// REJECT USER (Delete pending user)
if (isset($_GET['act']) && $_GET['act'] === 'reject' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare('DELETE FROM users WHERE id = ? AND status = "pending"')->execute([$id]);
    header('Location: users_crud.php');
    exit;
}

// get all user//////////////////////////////////////////////////////////////////////////////////
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// feth confim users//////////////////////////////////////////////////////////////////////////////////
$confirmedUsers = $pdo->query("SELECT * FROM users WHERE status = 'confirmed' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// pending users////////////////////////////////////////////////////////////////////////////////// 
$pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Count stats//////////////////////////////////////////////////////////////////////////////////
$totalUsers = count($allUsers);
$totalConfirmed = count($confirmedUsers);
$totalPending = count($pendingUsers);

//show departments testing//////////////////////////////////////////////////////////////////////////////////
$stmt = $pdo->prepare("
SELECT d.id, d.name 
FROM departments d 
ORDER BY d.name ASC
");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
                                <input name="email" type="email" class="form-control" placeholder="Email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="user">Student</option>
                                    <option value="proponent">Proponent</option>
                                    <option value="admin">Admin</option>
                                    
                                </select>
                            </div>
                        </div>

<label>Department</label>
checkbox
<div class="mb-3">      
<?php foreach ($departments as $dept): ?>
<div class="form-check">
<input class="form-check-input" type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" id="dept<?= $dept['id'] ?>">
<label class="form-check-label" for="dept<?= $dept['id'] ?>">
    <?= htmlspecialchars($dept['name']) ?>
</label>
</div>
<?php endforeach; ?>
</div>
 
 

    <div class="mt-3">
<button type="submit" class="btn btn-primary">Create User</button>
<a href="users_crud.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<?php endif; ?>


            <!-- edit userpform -->
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
                                <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Role</label>
                                <select name="role" class="form-control">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Student</option>
                                    <option value="proponent" <?= $user['role'] === 'proponent' ? 'selected' : '' ?>>Proponent</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Status</label>
                                <div>
                                    <span class="badge <?= $user['status'] === 'confirmed' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($user['status'] ?? 'pending') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
 <div class="mt-3">
<button class="btn btn-primary">Update User</button>
<a href="users_crud.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
<?php endif; ?>

            <!-- pendigng USER  -->
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
<table class="table table-hover mb-0 fixed-table">
<thead class="table-light">
<tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($pendingUsers)): ?>
<tr>
<td colspan="7" class="text-center py-4 text-muted">
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
<td><span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span></td>
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
<!-- <i class="fas fa-times"></i> Reject  missing -->  sd
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

            <!-- CONFIRM ONLY -->
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
<td colspan="8" class="text-center py-4 text-muted">
<i class="fas fa-users"></i> No confirmed users yet
</td>
</tr>
<?php else: ?>
<?php foreach ($confirmedUsers as $u): ?>
    <?php foreach ($departments as $dept): ?>
        <?php if ($u['departments'] == $dept['id']): ?> 
<tr>
<td><span class="fw-bold">#<?= $u['id'] ?></span></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
<td><?= htmlspecialchars($dept['name'] ?? 'No Department') ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>



<td>
<?php if ($u['role'] === 'admin'): ?>
<span class="badge bg-danger">Admin</span>
<?php elseif ($u['role'] === 'proponent'): ?>
<span class="badge bg-info">Proponent</span>
<?php else: ?>
<span class="badge bg-secondary">Student</span>
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
<a href="?act=delete&id=<?= $u['id'] ?>" 
onclick="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?')" 
class="btn btn-danger btn-sm">
<i class="fas fa-trash"></i> Delete
 </a>
 </td>
        </tr>
        <?php endif; ?>
    <?php endforeach; ?>
        <?php endforeach; ?>
    
         
          <?php endif; ?>
         </tbody>
         </table>
         </div>
         </div>
         </div>

        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
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
    </script>

</body>
</html>