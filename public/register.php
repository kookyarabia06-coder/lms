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

// Fetch all divisions from departments table
$divisionStmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$divisions = $divisionStmt->fetchAll(PDO::FETCH_ASSOC);

$err = ''; 
$success = '';
$showOTP = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp_verify'])) {
        // OTP VERIFICATION
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
            // OTP is correct! Create the user account
            $hash = password_hash($userData['password'], PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // Insert into database 
                $stmt = $pdo->prepare('INSERT INTO users (username, password, fname, lname, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, "user", "pending", NOW())');

                if ($stmt->execute([
                    $userData['username'], 
                    $hash, 
                    $userData['fname'], 
                    $userData['lname'], 
                    $userData['email'],
                ])) {
                    $userId = $pdo->lastInsertId();

                    // Insert department assignments
                    if (!empty($userData['departments'])) {
                        $deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, dept_id) VALUES (?, ?)");
                        foreach ($userData['departments'] as $deptId) {
                            $deptStmt->execute([$userId, $deptId]);
                        }
                    }

                    $pdo->commit();

                    // Clear session data
                    unset($_SESSION['registration_otp']);
                    unset($_SESSION['registration_data']);
                    unset($_SESSION['otp_time']);

                    // Redirect to login
                    header('Location: login.php?registered=1');
                    exit();
                } else {
                    $pdo->rollBack();
                    $err = 'Registration failed. Please try again.';
                    $showOTP = true;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $err = 'Registration failed. Please try again.';
                error_log("Registration error: " . $e->getMessage());
                $showOTP = true;
            }
        }

    } elseif (isset($_POST['resend_otp'])) {
        // Resend OTP
        $userData = $_SESSION['registration_data'] ?? [];

        if (!empty($userData)) {
            // Generate new OTP
            $newOTP = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['registration_otp'] = $newOTP;
            $_SESSION['otp_time'] = time();

            // Send OTP
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
        // INITIAL REGISTRATION FORM
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Get the selected department from the form (single department)
        $selectedDept = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
        
        // Convert to array format for existing code (since your code expects an array)
        $selectedDepts = $selectedDept > 0 ? [$selectedDept] : [];

        // Validation
        if (!$username || !$password || !$email || !$fname || !$lname) { 
            $err = 'All fields are required.'; 
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email format';
        } elseif (strlen($password) < 8) {
            $err = 'Password must be at least 8 characters';
        } elseif (empty($selectedDepts)) {
            $err = 'Please select a department';
        } else {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) { 
                $err = 'Username or email already exists'; 
            } else {
                // Verify department exists
                $checkStmt = $pdo->prepare("SELECT id FROM depts WHERE id = ?");
                $checkStmt->execute([$selectedDept]);
                $validDeptId = $checkStmt->fetchColumn();
                
                if (!$validDeptId) {
                    $err = 'Invalid department selection';
                } else {
                    // Generate OTP
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

                    // store session
                    $_SESSION['registration_data'] = [
                        'username' => $username,
                        'password' => $password,
                        'fname' => $fname,
                        'lname' => $lname,
                        'email' => $email,
                        'departments' => [$validDeptId] // Store as array with one ID
                    ];
                    $_SESSION['registration_otp'] = $otp;
                    $_SESSION['otp_time'] = time();

                    // Send OTP
                    $fullName = $fname . ' ' . $lname;
                    $emailResult = sendOTPEmail($email, $fullName, $otp);

                    // If PHPMailer fails
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
}

// Calculate OTP time left
if (isset($_SESSION['otp_time'])) {
    $otpTime = $_SESSION['otp_time'];
    $currentTime = time();
    $timeElapsed = $currentTime - $otpTime;
    $timeLeft = 600 - $timeElapsed; // 10 minutes
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
    <title>ARMMC LMS · Register</title>
    <link href="<?= BASE_URL ?>/assets/css/register.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
</head>
<body>
    <div class="overlay"></div>
    <div class="register-card">
        <div class="grid-layout">
            <!-- LEFT SIDE: COMPANY LOGO (identical) -->
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

            <!-- RIGHT SIDE: REGISTRATION FORM -->
            <div class="register-container">
                <h2 class="register-header">
                    join our <span>LMS</span>
                </h2>
                <p class="register-subtitle">
                    Fill in your details to get started
                </p>

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

                <!-- Registration Form -->
                <div class="register-form-wrapper">
                    <form action="../public/register.php" method="POST" id="registerForm">
                        <?php if(!$showOTP): ?>
                        <!-- First Name & Last Name Row -->
                        <div class="register-name-row">
                            <div class="register-form-group">
                                <label for="fname" class="register-form-label">First Name</label>
                                <div class="register-input-container">
                                    <i class="fas fa-user register-input-icon"></i>
                                    <input 
                                        type="text" 
                                        id="fname" 
                                        name="fname" 
                                        class="register-form-input" 
                                        placeholder="John"
                                        value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="register-form-group">
                                <label for="lname" class="register-form-label">Last Name</label>
                                <div class="register-input-container">
                                    <i class="fas fa-user register-input-icon"></i>
                                    <input 
                                        type="text" 
                                        id="lname" 
                                        name="lname" 
                                        class="register-form-input" 
                                        placeholder="Doe"
                                        value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Email Address -->    
                        <div class="register-form-group">
                            <label for="email" class="register-form-label">Email Address</label>
                            <div class="register-input-container">
                                <i class="fas fa-envelope register-input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="register-form-input" 
                                    placeholder="john.doe@armmc.gov.ph"
                                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                    required
                                >
                            </div>
                        </div>
                       
                        <!-- Username -->
                        <div class="register-form-group">
                            <label for="username" class="register-form-label">Username</label>
                            <div class="register-input-container">
                                <i class="fas fa-at register-input-icon"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    class="register-form-input" 
                                    placeholder="johndoe123"
                                    value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Division & Department Row - SIMPLIFIED -->
                        <div class="division-department-row">
                            <div class="register-form-group">
                                <label class="register-form-label">Division</label>
                                <div class="register-input-container">
                                    <i class="fas fa-sitemap register-input-icon"></i>
                                    <select class="register-select-input" id="divisionSelect">
                                        <option value="">Select Division</option>
                                        <?php foreach($divisions as $division): ?>
                                            <option value="<?= $division['id'] ?>"><?= htmlspecialchars($division['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="register-form-group">
                                <label class="register-form-label">Department</label>
                                <div class="register-input-container">
                                    <i class="fas fa-building register-input-icon"></i>
                                    <select class="register-select-input" id="departmentSelect" name="department_id" disabled>
                                        <option value="">Select Department</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Password Row -->
                        <div class="register-name-row">    
                            <div class="register-form-group">
                                <label for="password" class="register-form-label">Password</label>
                                <div class="register-input-container">
                                    <i class="fas fa-lock register-input-icon"></i>
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        class="register-form-input" 
                                        placeholder="Use strong password"
                                        required
                                        onkeyup="checkPasswordStrength()"
                                    >
                                    <button type="button" class="register-password-toggle" onclick="togglePasswordVisibility('password', 'togglePasswordIcon')">
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                                <small class="password-hint">Minimum 8 characters recommended</small>
                                <div class="register-password-strength">
                                    <span>Strength:</span>
                                    <div class="register-strength-bar">
                                        <div class="register-strength-fill" id="strengthBar"></div>
                                    </div>
                                    <span id="strengthText">Weak</span>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="register-form-group">
                                <label for="confirm_password" class="register-form-label">Confirm Password</label>
                                <div class="register-input-container">
                                    <i class="fas fa-lock register-input-icon"></i>
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password" 
                                        class="register-form-input" 
                                        placeholder="Re-enter password"
                                        required
                                        onkeyup="validatePasswordMatch()"
                                    >
                                    <button type="button" class="register-password-toggle" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmIcon')">
                                        <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                                    </button>
                                </div>
                                <small id="passwordMatchMessage" style="color: #dc3545; font-size: 0.8rem; margin-left: 1rem;"></small>
                            </div>
                        </div>

                        <!-- Register Button -->
                        <button type="submit" class="register-btn-primary">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>

                        <!-- Sign In Link -->
                        <div class="register-signin-prompt">
                            Already have an account? 
                            <a href="../public/login.php" class="register-signin-link">Sign in now</a>
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
                                <button type="submit" class="register-btn-primary btn-verify">
                                    <i class="fas fa-check"></i> Verify & Register
                                </button>
                                <button type="button" class="register-btn-primary btn-cancel" onclick="window.location.href='register.php'">
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

                <!-- Bottom note -->
                <div class="register-bottom-note">
                    <span class="line"></span>
                    <span>ARMMC Learning Management System. All rights reserved 2026.</span>
                    <span class="line"></span>
                </div>
                <div class="register-bottom-note" style="margin-top: 0.5rem; margin-left: 250px;">
                    iMISS
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for interactive features -->
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Division change handler
        const divisionSelect = document.getElementById('divisionSelect');
        const departmentSelect = document.getElementById('departmentSelect');
        
        if (divisionSelect && departmentSelect) {
            divisionSelect.addEventListener('change', function() {
                const divisionId = this.value;
                
                if (divisionId) {
                    departmentSelect.disabled = false;
                    departmentSelect.innerHTML = '<option value="">Loading...</option>';
                    
                    fetch(`get_departments.php?division_id=${divisionId}`)
                        .then(response => response.json())
                        .then(data => {
                            departmentSelect.innerHTML = '<option value="">Select Department</option>';
                            data.forEach(dept => {
                                departmentSelect.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            departmentSelect.innerHTML = '<option value="">Error loading departments</option>';
                        });
                } else {
                    departmentSelect.disabled = true;
                    departmentSelect.innerHTML = '<option value="">Select Department</option>';
                }
            });
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            if (password.match(/[$@#&!]+/)) strength += 25;
            
            // Cap at 100%
            strength = Math.min(strength, 100);
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 30) {
                strengthText.textContent = 'Weak';
                strengthBar.style.background = '#dc3545';
            } else if (strength < 55) {
                strengthText.textContent = 'Medium';
                strengthBar.style.background = '#ffc107';
            } else if (strength < 80) {
                strengthText.textContent = 'Strong';
                strengthBar.style.background = '#230cf5';            
            } else {
                strengthText.textContent = 'Complex';
                strengthBar.style.background = '#28a745';
            }
        }

        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('passwordMatchMessage');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    message.textContent = '✓ Passwords match';
                    message.style.color = '#28a745';
                } else {
                    message.textContent = '✗ Passwords do not match';
                    message.style.color = '#dc3545';
                }
            } else {
                message.textContent = '';
            }
        }

        // OTP Timer and Auto-submit
        const otpTimeLeft = <?= $timeLeft ?>;
        let timeLeft = otpTimeLeft;
        let canResend = timeLeft <= 0;
        let resendCooldown = 60;

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

        <?php if ($showOTP): ?>
        setInterval(updateOTPTimer, 1000);
        setInterval(updateResendTimer, 1000);
        <?php endif; ?>

        // Auto-submit OTP when 6 digits entered
        const securityCodeInput = document.getElementById('security_code');
        if (securityCodeInput) {
            securityCodeInput.focus();

            securityCodeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
                if (this.value.length === 6) {
                    setTimeout(() => {
                        document.getElementById('registerForm').submit();
                    }, 300);
                }
            });

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

        function resendOTP() {
            if (!canResend) return;
            if (confirm('Resend OTP to your email?')) {
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

        // Form validation
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                <?php if (!$showOTP): ?>
                const username = document.getElementById('username')?.value.trim();
                const password = document.getElementById('password')?.value;
                const email = document.getElementById('email')?.value.trim();
                const fname = document.getElementById('fname')?.value.trim();
                const lname = document.getElementById('lname')?.value.trim();
                const departmentSelect = document.getElementById('departmentSelect');
                
                // Check if department is selected and not disabled
                const department = departmentSelect ? departmentSelect.value : '';

                if (!fname || !lname) {
                    e.preventDefault();
                    showError('First name and last name are required');
                    return false;
                }
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
                
                // Check if department is selected
                if (!department || department === '') {
                    e.preventDefault();
                    showError('Please select a department');
                    return false;
                }

                // Also check if department select is disabled (meaning no division selected)
                if (departmentSelect.disabled) {
                    e.preventDefault();
                    showError('Please select a division first');
                    return false;
                }

                <?php else: ?>
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
            let errorDiv = document.querySelector('.alert-danger');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${message}</span>`;
                const form = document.getElementById('registerForm');
                form.insertBefore(errorDiv, form.firstChild);
            } else {
                errorDiv.querySelector('span').textContent = message;
            }
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Input animations
        const inputs = document.querySelectorAll('.register-form-input, .otp-input, .checkbox-item');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (this.classList.contains('checkbox-item')) return;
                this.parentElement.style.transform = 'scale(1.02)';
            });
            input.addEventListener('blur', function() {
                if (this.classList.contains('checkbox-item')) return;
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>