<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Only admin can access this page
if (!is_admin()) {
    echo 'Admin only';
    exit;
}

$act = $_GET['act'] ?? '';

if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (username,password,fname,lname,email,role,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([$username, $hash, $fname, $lname, $email, $role]);

    header('Location: users_crud.php');
    exit;
}

if ($act === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    header('Location: users_crud.php');
    exit;
}

// Fetch all users
$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>All Users - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9f9f9; }
        .card { margin-bottom: 20px; }
        .table-actions a { margin-right: 5px; }
    </style>
</head>

<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
<div class="main">
    <div class="container py-4">
        <h3>All Users</h3>

        <!-- Add User Button -->
        <?php if($act !== 'addform'): ?>
            <p><a href="?act=addform" class="btn btn-success btn-sm">Add New User</a></p>
        <?php endif; ?>

        <!-- Add User Form -->
        <?php if ($act === 'addform'): ?>
            <div class="card p-3 mb-3">
                <h5 class="mb-3">Add New User</h5>
                <form method="post" action="?act=add">
                    <div class="mb-2"><input name="username" class="form-control" placeholder="Username" required></div>
                    <div class="mb-2"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                    <div class="mb-2"><input name="fname" class="form-control" placeholder="First Name"></div>
                    <div class="mb-2"><input name="lname" class="form-control" placeholder="Last Name"></div>
                    <div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Email"></div>
                    <div class="mb-2">
                        <select name="role" class="form-control" required>
                            <option value="user">Student</option>
                            <option value="proponent">Proponent</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button class="btn btn-primary">Create User</button>
                    <a href="users_crud.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card shadow-sm p-3">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
                            <td class="table-actions">
                                <a href="?act=delete&id=<?= $u['id'] ?>" onclick="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?')" class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
