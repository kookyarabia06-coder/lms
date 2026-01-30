<?php
require_once __DIR__ . '/../inc/config.php';

// Redirect if already logged in 
if(isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

$err = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    if($u && password_verify($password, $u['password'])) {
        $_SESSION['user'] = [
            'id' => $u['id'],
            'username' => $u['username'],
            'fname' => $u['fname'],
            'lname' => $u['lname'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    } else {
        $err = 'Invalid credentials';
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login</title>
<link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page" bgcolor="#faf4ed">
<div class="login-page login-container">
  <div class="login-card">
    <h4>Login</h4>
    <?php if($err): ?>
      <div class="login-alert"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <form method="post">
      <input name="username" class="login-input" placeholder="Username" required>
      <input name="password" type="password" class="login-input" placeholder="Password" required>
      <div class="login-footer">
        <button type="submit" class="login-btn">Login</button>
        <a href="<?= BASE_URL ?>/public/register.php" class="login-register-link">Register</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
