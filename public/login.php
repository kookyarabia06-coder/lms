<?php
// login.php
require_once __DIR__ . '/../inc/config.php';
// session_start();

$error = '';
$pending_contact = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Find user
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Check if user is confirmed
        if (isset($user['status']) && $user['status'] !== 'confirmed') {
            if ($user['status'] === 'pending') {
                $pending_contact = 'Your account is pending approval. Please contact administrator.';
            } else {
                $error = 'Please confirm your email before logging in.';
            }
        } else {
            // Login successful
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'fname' => $user['fname'] ?? '',
                'lname' => $user['lname'] ?? '',
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'] ?? 'confirmed'
            ];
            
            header('Location: dashboard.php');
            exit();
        }
    } else {
        $error = 'Invalid username or password';
    }
}


$admin_email = '';
try {
$stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();
if ($admin) {
$admin_email = $admin['email'];
}
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* login notification if staff number does not meet the netcode_DB */
        .pending-note {
            background: #fff9e6;
            border-left: 3px solid #ed6c02;
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: #5f5b4c;
        }
        
        .pending-note i {
            color: #ed6c02;
            font-size: 16px;
            margin-top: 2px;
        }
        
        .pending-note-content {
            flex: 1;
        }
        
        .pending-note-title {
            font-weight: 600;
            color: #5f4b1c;
            margin-bottom: 4px;
        }
        
        .pending-note-text {
            color: #6b6b6b;
            line-height: 1.5;
        }
        
        .pending-note-actions {
            margin-top: 10px;
            display: flex;
            gap: 16px;
        }
        
        .pending-note-actions a {
            color: #ed6c02;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pending-note-actions a:hover {
            text-decoration: underline;
        }
        
        .pending-note-actions i {
            font-size: 12px;
        }
        
     
        .error-message {
            background: #ffebee;
            border-left: 3px solid #d32f2f;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #5f4c4c;
        }
        
        .error-message i {
            color: #d32f2f;
        }
        
   
        .success-message {
            background: #e8f5e9;
            border-left: 3px solid #2e7d32;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #1e4620;
        }
        
        .success-message i {
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>
        
        <!-- nofig -->
<?php if (!empty($pending_contact)): ?>
<div class="pending-note">
<i class="fas fa-clock"></i>
<div class="pending-note-content">
<div class="pending-note-title"><p>Account pending approval.</p>
                                <p>Please wait for Admin approval.</p>
</div>
<div class="pending-note-text">


             <!-- wag galawin pls        -->
<!-- <?php if (!empty($admin_email)): ?>
<a href="mailto:<?= htmlspecialchars($admin_email) ?>?subject=Account%20Activation%20Request&body=Hello%20Admin%2C%0D%0A%0D%0AMy%20account%20is%20still%20pending.%20Please%20activate%20it.%0D%0AUsername%3A%20<?= urlencode($_POST['username'] ?? '') ?>%0D%0A%0D%0AThank%20you.">
<i class="fas fa-envelope"></i> Email Admin</a>
<a href="login.php">
<i class="fas fa-arrow-left"></i> Back
</a> -->
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
        
        <!-- Error Message -->
<?php if(!empty($error)): ?>
<div class="error-message">
<i class="fas fa-exclamation-circle"></i>
<span><?= htmlspecialchars($error) ?></span>
</div>
        <?php endif; ?>
        
        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username" required 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                    <span class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-options">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
            </div>
            
            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="signup-link">
                Don't have an account?
                <a href="<?= BASE_URL ?>/public/register.php">Sign up now</a>
            </div>
        </form>
    </div>
        </div>

    <script>
        // Password toggle
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
        
        // Input animation
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Clear error on typing
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const errorMessage = document.querySelector('.error-message');
        
        if(errorMessage) {
            usernameInput.addEventListener('input', () => errorMessage.style.display = 'none');
            passwordInput.addEventListener('input', () => errorMessage.style.display = 'none');
        }
    </script>
</body>
</html>