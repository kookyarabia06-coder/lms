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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<<<<<<< HEAD
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card p-3">
        <h4 class="mb-3">Login</h4>
        <?php if($err): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-2">
            <input name="username" class="form-control" placeholder="Username" required>
          </div>
          <div class="mb-2">
            <input name="password" type="password" class="form-control" placeholder="Password" required>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-primary">Login</button>
            <a href="<?= BASE_URL ?>/public/register.php">Register</a>
          </div>
        </form>
=======
<body class="login-page" background: linear-gradient(
  180deg,
  #0F1C3F 0%,
  #2A5298 50%,
  #FFFFFF 100%
);
>
<div class="login-page login-container">
  <div class="login-card">
    <a href="<?= BASE_URL ?>/public/index.php" class="login-back-btn">
    <i class="fas fa-arrow-left"></i> < Back to home
  </a>
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
>>>>>>> f95964b909f01529c15cae74dd9c428ae4617bcf
      </div>
    </div>
  </div>
</div>
</body>
</html>