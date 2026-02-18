<?php
require_once __DIR__ . '/../inc/config.php';
// session_start();

// Check if PHPMailer exists
$mailer_path = __DIR__ . '/../inc/mailer.php';
if (file_exists($mailer_path)) {
    require_once $mailer_path;
} else {
    die("Mailer configuration not found!");
}

$err = ''; 
$success = '';
$showOTP = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp_verify'])) {
        // ============ OTP VERIFICATION CODE or just follow the number HAHHAHAHAHHA number 1 ============
        $enteredOTP = $_POST['security_code'] ?? '';
        $storedOTP = $_SESSION['registration_otp'] ?? '';
        $userData = $_SESSION['registration_data'] ?? [];
        
        if (empty($enteredOTP)) {
            $err = 'Please enter the OTP';
            $showOTP = true;
        } elseif (empty($storedOTP) || empty($userData)) {
            $err = 'OTP session expired. Please register again.';
        } elseif ($enteredOTP !== $storedOTP) {
            $err = 'Invalid OTP. Please try again.';
            $showOTP = true;
        } else {
            // OTP is correct! Create the user account = 2
            $hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // insert sa database with confirm status =3
            $stmt = $pdo->prepare('INSERT INTO users (username, password, fname, lname, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, "user", "pending", NOW())');
            
            if ($stmt->execute([$userData['username'], $hash, $userData['fname'], $userData['lname'], $userData['email']])) {
                // Clear session data
                unset($_SESSION['registration_otp']);
                unset($_SESSION['registration_data']);
                unset($_SESSION['otp_time']);
                
                // auto login to if ever hindi na gana try remove ?register=1 below 
                header('Location: login.php?registered=1');
                exit();
            } else {
                $err = 'Registration failed. Please try again.';
                $showOTP = true;
            }
        }
        
    } elseif (isset($_POST['resend_otp'])) {
        // resend otp
        $userData = $_SESSION['registration_data'] ?? [];
        
        if (!empty($userData)) {
            // generator ng otp 4
            $newOTP = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['registration_otp'] = $newOTP;
            $_SESSION['otp_time'] = time();
            
            // send otp 5
            $fullName = $userData['fname'] . ' ' . $userData['lname'];
            $emailResult = sendOTPEmail($userData['email'], $fullName, $newOTP);
            
            if (!$emailResult['success']) {
                $emailResult = sendOTPEmail($userData['email'], $fullName, $newOTP);
            }
            
            $success = $emailResult['success'] ? "New OTP sent to your email" : "Failed to resend OTP";
            $showOTP = true;
        } else {
            $err = 'Session expired. Please register again.';
        }
        
    } else {
        // ============ INITIAL R3GISTRATION PORM ============
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        if (!$username || !$password || !$email) { 
            $err = 'All fields are required'; 
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email format';
        } elseif (strlen($password) < 8) {
            $err = 'Password must be at least 8 characters';
        } else {
            // check from user select ID for existing user to
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) { 
                $err = 'Username or email already exists'; 
            } else {
                //regenirate otp 5
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Store in session cache daw to wag nalang galawin sabi ni kenneth
                $_SESSION['registration_data'] = [
                    'username' => $username,
                    'password' => $password,
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email
                ];
                $_SESSION['registration_otp'] = $otp;
                $_SESSION['otp_time'] = time(); // Store generation time
                
                // will call $email nalang tinatamad na ako mag type  send otp
                $fullName = $fname . ' ' . $lname;
                $emailResult = sendOTPEmail($email, $fullName, $otp);
                
                // If PHPMailer fails use local logging hehehhe
                if (!$emailResult['success']) {
                    error_log("PHPMailer failed: " . $emailResult['message']);
                    $emailResult = sendOTPEmail($email, $fullName, $otp);
                    
                    if ($emailResult['success']) {
                        $success = "OTP generated: <strong>$otp</strong> (Check server logs)";
                        $showOTP = true;
                    } else {
                        $err = 'Failed to generate OTP. Please try again.';
                    }
                } else {
                    $success = "OTP has been sent to $email";
                    $showOTP = true;
                }
            }
        }
    }
}

// Calculate OTP time left if OTP was sent
        if (isset($_SESSION['otp_time'])) {
       $otpTime = $_SESSION['otp_time'];
        $currentTime = time();
        $timeElapsed = $currentTime - $otpTime;
       $timeLeft = 600 - $timeElapsed; // 10m in secc
    if ($timeLeft < 0) $timeLeft = 0;
} else {
    $timeLeft = 600;
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
    <style>
        /* Additional CSS for OTP functionality by AI */
        .otp-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .otp-input-container {
            position: relative;
            margin: 20px 0;
        }
        
        .otp-input {
            letter-spacing: 10px;
            font-size: 28px;
            text-align: center;
            padding: 15px;
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
        }
        
        .timer-display {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin: 10px 0;
        }
        
        .timer-expired {
            color: #dc3545;
            font-weight: bold;
        }
        
        .resend-code-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            margin-left: 10px;
        }
        
        .resend-code-btn:hover {
            background: #5a6268;
        }
        
        .resend-code-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-verify {
            flex: 1;
            background: #28a745;
        }
        
        .btn-verify:hover {
            background: #218838;
        }
        
        .btn-cancel {
            flex: 1;
            background: #6c757d;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .code-status {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #c3e6cb;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Create Account</h1>
            <p>Join us by creating your free account</p>
        </div>
        
        <?php if($err): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>
        
        <?php if($success && !$showOTP): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form class="login-form" method="POST" id="registerForm">
            <?php if(!$showOTP): ?>
                <!-- Registration Form -->
                <div class="form-row">
                    <div class="form-group half">
                        <label for="fname">First Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="fname" name="fname" class="form-control" 
                                   placeholder="Enter your first name" 
                                   value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group half">
                        <label for="lname">Last Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="lname" name="lname" class="form-control" 
                                   placeholder="Enter your last name"
                                   value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-at"></i>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                    </div>
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
                
                <button type="submit" class="login-button">Send OTP</button>
                
                <div class="signup-link">
                    Already have an account?
                    <a href="<?= BASE_URL ?>/public/login.php">Sign in now</a>
                </div>
                
            <?php else: ?>
                <!-- OTP Verification Section -->
                <div class="otp-section">
                    <?php if($success): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?= $success ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-shield-alt"></i> Verify Your Email
                    </h3>
                    
                    <p style="text-align: center; margin-bottom: 20px;">
                        Enter the 6-digit OTP sent to:<br>
                        <strong><?= htmlspecialchars($_SESSION['registration_data']['email'] ?? '') ?></strong>
                    </p>
                    
                    <div class="otp-input-container">
                        <input type="text" id="security_code" name="security_code" class="otp-input" 
                               placeholder="000000" maxlength="6" pattern="\d{6}" required 
                               autocomplete="off" inputmode="numeric">
                        <input type="hidden" name="otp_verify" value="1">
                    </div>
                    
                    <div class="timer-display" id="otpTimer">
                        <?php
                        if ($timeLeft <= 0) {
                            echo '<span class="timer-expired">OTP has expired</span>';
                        } else {
                            $minutes = floor($timeLeft / 60);
                            $seconds = $timeLeft % 60;
                            echo "OTP expires in: <span id='timeLeft'>" . sprintf('%02d:%02d', $minutes, $seconds) . "</span>";
                        }
                        ?>
                    </div>
                    
                    <div style="text-align: center; margin: 15px 0;">
                        <button type="button" class="resend-code-btn" id="resendCodeBtn" 
                                onclick="resendOTP()" <?= $timeLeft > 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                        <span id="resendTimer" style="font-size: 12px; color: #666; margin-left: 10px;"></span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="login-button btn-verify">
                            <i class="fas fa-check"></i> Verify & Register
                        </button>
                        <button type="button" class="login-button btn-cancel" onclick="window.location.href='register.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                        <i class="fas fa-info-circle"></i> Didn't receive the code? Check your spam folder.
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
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
        }
        
        // OTP Timer and Auto-submit
        const otpTimeLeft = <?= $timeLeft ?>;
        let timeLeft = otpTimeLeft;
        let canResend = timeLeft <= 0;
        let resendCooldown = 60; // 60 seconds cooldown for resend
        
        // Update OTP timer
        function updateOTPTimer() {
            if (timeLeft > 0) {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                const timeLeftElement = document.getElementById('timeLeft');
                if (timeLeftElement) {
                    timeLeftElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
                
                if (timeLeft <= 0) {
                    canResend = true;
                    document.getElementById('resendCodeBtn').disabled = false;
                    document.getElementById('otpTimer').innerHTML = '<span class="timer-expired">OTP has expired</span>';
                }
            }
        }
        
        // Update resend timer
        function updateResendTimer() {
            const resendBtn = document.getElementById('resendCodeBtn');
            const resendTimerElement = document.getElementById('resendTimer');
            
            if (!canResend && resendCooldown > 0) {
                resendCooldown--;
                resendBtn.disabled = true;
                if (resendTimerElement) {
                    resendTimerElement.textContent = `Resend in ${resendCooldown}s`;
                }
                
                if (resendCooldown <= 0) {
                    canResend = true;
                    resendBtn.disabled = false;
                    if (resendTimerElement) {
                        resendTimerElement.textContent = '';
                    }
                }
            }
        }
        
        // Start timers if OTP section is shown
        <?php if ($showOTP): ?>
        setInterval(updateOTPTimer, 1000);
        setInterval(updateResendTimer, 1000);
        <?php endif; ?>
        
        // Auto-submit OTP when 6 digits entered
        const securityCodeInput = document.getElementById('security_code');
        if (securityCodeInput) {
            securityCodeInput.focus();
            
            securityCodeInput.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/\D/g, '');
                
                // Limit to 6 digits
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
                
                // Auto-submit when 6 digits entered
                if (this.value.length === 6) {
                    setTimeout(() => {
                        document.getElementById('registerForm').submit();
                    }, 300);
                }
            });
            
            // Allow paste
            securityCodeInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = pastedText.replace(/\D/g, '');
                this.value = numbers.slice(0, 6);
                
                if (this.value.length === 6) {
                    setTimeout(() => {
                        document.getElementById('registerForm').submit();
                    }, 300);
                }
            });
        }
        
        // Resend OTP function
        function resendOTP() {
            if (!canResend) return;
            
            if (confirm('Resend OTP to your email?')) {
                // Create a hidden form to resend
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'resend_otp';
                input.value = '1';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Form validation for registration
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                <?php if (!$showOTP): ?>
                // Only validate on initial registration
                const username = document.getElementById('username')?.value.trim();
                const password = document.getElementById('password')?.value;
                const email = document.getElementById('email')?.value.trim();
                
                if (!username) {
                    e.preventDefault();
                    showError('Username is required');
                    return false;
                }
                
                if (!password) {
                    e.preventDefault();
                    showError('Password is required');
                    return false;
                }
                
                if (password.length < 8) {
                    if (!confirm('Your password is less than 8 characters. Continue anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                if (!email) {
                    e.preventDefault();
                    showError('Email is required');
                    return false;
                }
                <?php else: ?>
                // OTP validation
                const otp = document.getElementById('security_code')?.value.trim();
                if (!otp || otp.length !== 6) {
                    e.preventDefault();
                    showError('Please enter a valid 6-digit OTP');
                    return false;
                }
                <?php endif; ?>
            });
        }
        
        function showError(message) {
            // Create error message display
            let errorDiv = document.querySelector('.alert-danger');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${message}</span>`;
                form.insertBefore(errorDiv, form.firstChild);
            } else {
                errorDiv.querySelector('span').textContent = message;
            }
            
            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Add focus animation to inputs
        const inputs = document.querySelectorAll('.form-control, .otp-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>