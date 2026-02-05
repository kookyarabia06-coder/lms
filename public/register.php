<?php
require_once __DIR__ . '/../inc/config.php';
$err=''; $success='';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $otp_code = $_POST['otp_code'] ?? '';
    $STATUS = 'pending';
    if(!$username || !$password){ $err='Username and password required'; }
    else {
        // check exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if($stmt->fetch()){ $err='Username or email already exists'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username,password,fname,otp_code,STATUS,lname,email,role,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$username,$hash,$fname,$otp_code,$STATUS,$lname,$email]);
            $success = 'Registered successfully. You may login.';
        }
        //otp insert
        $stmt = $pdo->prepare('SELECT otp_code FROM otp_code WHERE otp_code = ?');
        $stmt->execute([$otp_code]);
        if($stmt->fetch()){ $err='OTP code already exists'; }
        else {
            $stmt = $pdo->prepare('INSERT INTO otp_code (otp_code, otp_expired_at) VALUES (?, ?)');
            $stmt->execute([$otp_code, $otp_expired_at]);
        }


}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Create Account</title>
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Create Account</h1>
            <p>Join us by creating your free account</p>
        </div>
        
        <form class="login-form" method="POST" action="simple_mail.php">
            <?php if($err): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($err) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

               <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="success">
                Thank you! Your message has been sent successfully.
            </div>
        <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group half">
                    <label for="fname">First Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="fname" name="fname" class="form-control" 
                               placeholder="Enter your first name" 
                               value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-group half">
                    <label for="lname">Last Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="lname" name="lname" class="form-control" 
                               placeholder="Enter your last name"
                               value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>">
                    </div>
                </div>
            </div>
            
           <div class="form-group">
    <label for="email">Email Address</label>
    <div class="input-with-icon">
        <i class="fas fa-at"></i>
        <input type="email" id="email" name="email" class="form-control" 
               placeholder="Enter your email"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
</div>
             
                
            </div>
<!-- ======= -->
<!-- Security Code Input (Hidden by default) -->
<div class="form-group" id="securityCodeGroup" style="display: none;">
    <label for="security_code">
        <i class="fas fa-shield-alt"></i> Security Code
        <span id="countdown" style="color: #ff6b6b; font-weight: bold; margin-left: 10px;"></span>
    </label>
    <div class="input-with-icon">
        <i class="fas fa-key"></i>
        <input type="text" id="security_code" name="security_code" class="form-control" 
               placeholder="Enter the 6-digit code sent to your email" maxlength="6">
        <button type="button" class="resend-code-btn" id="resendCodeBtn">
            <i class="fas fa-redo"></i> Resend
        </button>
    </div>
    <small class="form-text text-muted" id="codeStatus"></small>
</div>

            <div class="form-group">
                <label for="username">Username *</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Choose a username" required
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter password" required>
                    <span class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <small class="password-hint">Minimum 8 characters recommended</small>
            </div>
            
            <button type="submit" class="login-button">Create Account</button>
            
            <div class="signup-link">
                Already have an account?
                <a href="<?= BASE_URL ?>/public/login.php">Sign in now</a>
            </div>
        </form>
    </div>

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
        const errorMessage = document.querySelector('.error-message');
        if(errorMessage) {
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    errorMessage.style.display = 'none';
                });
            });
        }
        
        // Clear success message when user starts typing
        const successMessage = document.querySelector('.success-message');
        if(successMessage) {
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    successMessage.style.display = 'none';
                });
            });
        }
        
        // Form validation
        const form = document.querySelector('.login-form');
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if(!username) {
                e.preventDefault();
                alert('Username is required');
                return false;
            }
            
            if(!password) {
                e.preventDefault();
                alert('Password is required');
                return false;
            }
            
            if(password.length < 8) {
                if(!confirm('Your password is less than 8 characters. Continue anyway?')) {
                    e.preventDefault();
                    return false;
                }
            }g
        });
    </script>
</body>
</html>