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
    <title>ARMMC LMS · Login</title>
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
     <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<style>
 * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-image: url('../uploads/images/armmc-bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 35, 102, 0.4);
            z-index: -1;
        }

</style>
<body>
    <div class="overlay"></div>
    <div class="login-card">
        <div class="grid-layout">
            <!-- LEFT SIDE: COMPANY LOGO (identical to welcome page) -->
            <div class="logo-hero">
                <div class="logo-main">
                    <img 
                        class="company-logo-png" 
                        src="../uploads/images/armmc-logo.png" 
                        alt="ARMMC Logo"
                        title="Amang Rodriguez Memorial Medical Center"
                    >
                    <div class="logo-caption">
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i> 
                        AMANG RODRIGUEZ MEMORIAL MEDICAL CENTER 
                        <i class="fas fa-circle" style="font-size: 0.4rem; vertical-align: middle; color: #1f6fb0;"></i>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: LOGIN FORM -->
            <div class="login-container">  
                <h1 class="login-header">
                    access your <span>account</span>
                </h1>
                <p class="login-subtitle">
                    Please enter your credentials to continue
                </p>

                <!-- Pending Notification -->
                <?php if (!empty($pending_contact)): ?>
                <div class="pending-note">
                    <i class="fas fa-clock"></i>
                    <div class="pending-note-content">
                        <div class="pending-note-title">Account pending approval</div>
                        <div class="pending-note-text"><?= htmlspecialchars($pending_contact) ?></div>
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

                <!-- Login Form -->
                <div class="login-form-wrapper">
                    <form class="login-form" method="POST" action="" id="loginForm">
                        <!-- Username/Email field -->
                        <div class="login-form-group">
                            <label for="username" class="login-form-label">Username or Email</label>
                            <div class="login-input-container">
                                <i class="fas fa-user login-input-icon"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="login-form-input" 
                                    placeholder="Enter your username"
                                    value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Password field -->
                        <div class="login-form-group">
                            <label for="password" class="login-form-label">Password</label>
                            <div class="login-input-container">
                                <i class="fas fa-lock login-input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="login-form-input" 
                                    placeholder="Enter your password"
                                    required
                                >
                                <button type="button" class="login-password-toggle" id="togglePassword">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember me & Forgot password -->
                        <div class="login-options-row">
                            <label class="login-remember">
                                <input type="checkbox" name="remember" id="remember"> Remember me
                            </label>
                            <a href="forgot_password.php" class="login-forgot-link">Forgot password?</a>
                        </div>

                        <!-- Login button -->
                        <button type="submit" class="login-btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>

                        <!-- Sign up link -->
                        <div class="login-signup-prompt">
                            Don't have an account? 
                            <a href="<?= BASE_URL ?>/public/register.php" class="login-signup-link">Sign up now</a>
                        </div>
                    </form>
                </div>

                <!-- Bottom note -->
                <div class="login-bottom-note">
                    <span class="line"></span>
                    <span>ARMMC Learning Management System. All rights reserved 2026.</span>
                    <span class="line"></span>
                </div>
                <div class="login-bottom-note" style="margin-top: 0.5rem; margin-left: 250px;">
                    iMISS
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for interactive features -->
    <script>
        // Password toggle functionality
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
        const inputs = document.querySelectorAll('.login-form-input');
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

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    </script>
</body>
</html>
