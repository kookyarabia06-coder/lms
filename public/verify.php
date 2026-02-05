<?php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Username & Password</title>
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Verify your Email</h1>
            <p>Please check the OTP we sent to your email</p>
        </div>
        
        <form class="login-form" method="POST" action="">
            <?php if(!empty($err)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($err) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">OTP</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope-circle-check"></i>
                    <input type="text" id="otp_code" name="username" class="form-control" 
                           placeholder="Enter OTP" required 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">

            <button class= "verify-btn" action= "" type="submit">Verify</button>

            </div>
            </div>

    <script>
        // Simple password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Add focus animation to inputs
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Clear error message when user starts typing
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const errorMessage = document.querySelector('.error-message');
        
        if(errorMessage) {
            usernameInput.addEventListener('input', function() {
                errorMessage.style.display = 'none';
            });
            
            passwordInput.addEventListener('input', function() {
                errorMessage.style.display = 'none';
            });
        }
    </script>
</body>
</html>
