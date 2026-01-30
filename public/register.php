<?php
require_once __DIR__ . '/../inc/config.php';
$err=''; $success='';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    if(!$username || !$password){ $err='Username and password required'; }
    else {
        // check exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if($stmt->fetch()){ $err='Username or email already exists'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username,password,fname,lname,email,role,created_at) VALUES (?,?,?,?,?,"user",NOW())');
            $stmt->execute([$username,$hash,$fname,$lname,$email]);
            $success = 'Registered successfully. You may login.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Register</title>
<link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page" bgcolor="#faf4ed">

<div class="login-page login-container">
  <div class="login-card">
    <h4>Register</h4>

    <?php if($err): ?>
      <div class="login-alert"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
      <div class="login-alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
      <!-- First & Last Name Side by Side -->
      <div style="display:flex; gap:1rem; margin-bottom:1rem;">
        <input name="fname" class="login-input" placeholder="First Name">
        <input name="lname" class="login-input" placeholder="Last Name">
      </div>

      <!-- Email -->
      <input name="email" type="email" class="login-input" placeholder="Email">

      <!-- Username -->
      <input name="username" class="login-input" placeholder="Username">

      <!-- Password -->
      <input name="password" type="password" class="login-input" placeholder="Password">

      <!-- Footer: Register Button + Login Link -->
      <div class="login-footer">
        <button type="submit" class="login-btn">Register</button>
        <a href="login.php" class="login-register-link">Login</a>
      </div>
    </form>
  </div>
</div>

</body>
</html>


