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
<html><head><meta charset="utf-8"><title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center"><div class="col-md-6">
    <div class="card p-3">
      <h4 class="mb-3">Register</h4>
      <?php if($err): ?><div class="alert alert-danger"><?=htmlspecialchars($err)?></div><?php endif; ?>
      <?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
      <form method="post">
        <div class="row"><div class="col"><input name="fname" class="form-control" placeholder="First name"></div><div class="col"><input name="lname" class="form-control" placeholder="Last name"></div></div>
        <div class="mb-2 mt-2"><input name="email" type="email" class="form-control" placeholder="Email"></div>
        <div class="mb-2"><input name="username" class="form-control" placeholder="Username"></div>
        <div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Password"></div>
        <div class="d-flex justify-content-between align-items-center">
          <button class="btn btn-success">Register</button>
          <a href="login.php">Login</a>
        </div>
      </form>
    </div>
  </div></div>
</div>
</body></html>
