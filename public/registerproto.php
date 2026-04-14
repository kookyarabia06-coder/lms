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

// Department list - This can be moved to db in future??? ata 
$departments = [
'Anesthetics',
'Breast Screening',
'cardiology',
'Ear,nose and throat (ENT)',
'Elderly services department',
'Gastroenerology',
'General Surgery',
'Gynecology'
];

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

// Convert departments array to JSON for storage
$departmentsJson = json_encode($userData['departments'] ?? []);
$course = $userData['course'] ?? '';

// Insert into database with departments as JSON
$stmt = $pdo->prepare('INSERT INTO users (username, password, fname, lname, email, departments, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "user", "pending", NOW())');

if ($stmt->execute([
$userData['username'], 
$hash, 
$userData['fname'], 
$userData['lname'], 
$userData['email'],
$departmentsJson,

])) {
// Clear session data
unset($_SESSION['registration_otp']);
unset($_SESSION['registration_data']);
unset($_SESSION['otp_time']);

// Redirect to login
header('Location: login.php?registered=1');
exit();
} else {
$err = 'Registration failed. Please try again.';
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
$departments = $_POST['departments'] ?? []; // This will be an array
$course = trim($_POST['course'] ?? '');

// Validation
if (!$username || !$password || !$email || empty($departments)) { 
$err = 'All fields are required. Please select at least one department.'; 
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$err = 'Invalid email format';
} elseif (strlen($password) < 8) {
$err = 'Password must be at least 8 characters';
} else {
// Check if user exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$stmt->execute([$username, $email]);
if ($stmt->fetch()) { 
$err = 'Username or email already exists'; 
} else {
// Generate OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Store in session with departments array
$_SESSION['registration_data'] = [
    'username' => $username,
    'password' => $password,
    'fname' => $fname,
    'lname' => $lname,
    'email' => $email,
    'departments' => $departments,
    'course' => $course
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
    <!-- Font Awesome 6 (free) for subtle icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* === GLOBAL STYLES (identical to welcome/login pages) === */
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

        /* main card - EXACT same dimensions */
        .register-card {
            max-width: 1280px;
            width: 100%;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 3.5rem;
            box-shadow: 
                0 30px 60px -20px rgba(0,40,80,0.25),
                0 8px 20px -8px rgba(0,32,64,0.1),
                inset 0 1px 1px rgba(255,255,255,0.6);
            border: 1px solid rgba(255,255,255,0.6);
            padding: 3rem 2.5rem;
        }

        /* two-column layout */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
        }

        /* left side – company logo (identical) */
        .logo-hero {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 2.5rem;
            padding: 3rem 2rem;
            box-shadow: 0 20px 30px -10px rgba(0,20,40,0.15);
            border: 1px solid rgba(255,255,255,0.8);
            transition: transform 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-hero:hover {
            transform: scale(1.01);
            background: rgba(255,255,255,0.65);
        }

        .logo-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .company-logo-png {
            max-width: 340px;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            filter: drop-shadow(0 12px 18px rgba(0,50,90,0.25));
            background: transparent;
            border-radius: 32px;
            transition: filter 0.2s;
        }

        .logo-caption {
            margin-top: 2rem;
            font-weight: 400;
            font-size: 1.1rem;
            letter-spacing: 2px;
            color: #1c3f5c;
            opacity: 0.8;
            text-transform: uppercase;
            border-bottom: 2px solid #a3c6e9;
            padding-bottom: 0.75rem;
            display: inline-block;
        }

        /* === REGISTER-SPECIFIC STYLES - sized to match exactly === */
        .register-container {
            padding: 1rem 0.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .register-badge {
            display: inline-block;
            background: #1d4e75;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            letter-spacing: 0.3px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 10px rgba(0,60,110,0.2);
            align-self: flex-start;
        }

        .register-header {
            font-size: clamp(2.2rem, 5vw, 3.4rem);
            font-weight: 700;
            line-height: 1.2;
            color: #0c2e45;
            margin-bottom: 0.5rem;
        }

        .register-header span {
            background: linear-gradient(135deg, #1f6392, #0a3b58);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            border-bottom: 4px solid #6ab0f5;
            display: inline-block;
            padding-bottom: 2px;
        }

        .register-subtitle {
            font-size: 1.1rem;
            color: #2b4e6b;
            margin-bottom: 1.5rem;
            line-height: 1.5;
            font-weight: 400;
            max-width: 500px;
        }

        /* register form */
        .register-form-wrapper {
            width: 100%;
            max-width: 480px; /* Slightly wider for two-column name fields */
            margin: 0.2rem 0 0.5rem;
        }

        /* Two-column for first/last name */
        .register-name-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .register-form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }

        .register-form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #144a6f;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
        }

        .register-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .register-input-icon {
            position: absolute;
            left: 1.2rem;
            color: #1f6fb0;
            font-size: 1rem;
            opacity: 0.7;
            z-index: 1;
        }

        .register-form-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid rgba(31, 111, 176, 0.2);
            border-radius: 50px;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(2px);
            transition: all 0.2s;
            color: #0c2e45;
        }

        .register-form-input:focus {
            outline: none;
            border-color: #1f6fb0;
            background: white;
            box-shadow: 0 0 0 4px rgba(31, 111, 176, 0.1);
        }

        .register-form-input::placeholder {
            color: #6f9ac0;
            font-size: 0.9rem;
        }

        /* Special styling for dropdown with checkboxes */
        .register-dropdown-container {
            position: relative;
            width: 100%;
        }

        .register-dropdown-selector {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid rgba(31, 111, 176, 0.2);
            border-radius: 50px;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(2px);
            color: #0c2e45;
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .register-dropdown-selector i {
            color: #1f6fb0;
            margin-right: 0.5rem;
            transition: transform 0.3s;
        }

        .register-dropdown-selector.active i {
            transform: rotate(180deg);
        }

        .register-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 1.5rem;
            margin-top: 0.5rem;
            padding: 1rem 1.2rem;
            box-shadow: 0 15px 30px -10px rgba(0,40,80,0.2);
            border: 1px solid rgba(31, 111, 176, 0.2);
            z-index: 10;
            display: none;
            max-height: 250px;
            overflow-y: auto;
        }

        .register-dropdown-menu.show {
            display: block;
        }

        .register-checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(31, 111, 176, 0.1);
            cursor: pointer;
        }

        .register-checkbox-item:last-child {
            border-bottom: none;
        }

        .register-checkbox-item input[type="checkbox"] {
            width: 1.1rem;
            height: 1.1rem;
            accent-color: #1f6fb0;
            margin-right: 0.8rem;
            cursor: pointer;
        }

        .register-checkbox-item label {
            color: #144a6f;
            font-size: 0.95rem;
            cursor: pointer;
            flex: 1;
        }

        .register-selected-count {
            font-size: 0.8rem;
            color: #1f6fb0;
            margin-left: 0.5rem;
            font-weight: 500;
        }

        .register-password-toggle {
            position: absolute;
            right: 1.2rem;
            background: none;
            border: none;
            color: #1f6fb0;
            cursor: pointer;
            font-size: 1rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .register-password-toggle:hover {
            opacity: 1;
        }

        .register-password-strength {
            margin-top: 0.4rem;
            font-size: 0.8rem;
            color: #567e9f;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .register-strength-bar {
            height: 4px;
            flex: 1;
            background: rgba(31, 111, 176, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }

        .register-strength-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #dc3545, #ffc107, #28a745);
            border-radius: 2px;
            transition: width 0.3s;
        }

        .register-btn-primary {
            background: #1f6fb0;
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            cursor: pointer;
            transition: 0.15s;
            box-shadow: 0 12px 18px -10px #1f6fb0;
            border: 1px solid rgba(255,255,255,0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            width: 100%;
            margin: 0.8rem 0 1.2rem;
        }

        .register-btn-primary i {
            font-size: 1.2rem;
        }

        .register-btn-primary:hover {
            background: #0f558b;
            transform: translateY(-3px);
            box-shadow: 0 20px 22px -12px #1f6fb0;
        }

        .register-signin-prompt {
            text-align: center;
            color: #2b4e6b;
            font-size: 0.95rem;
            margin-bottom: 1.2rem;
        }

        .register-signin-link {
            color: #1f6fb0;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .register-signin-link:hover {
            color: #0f4a78;
            text-decoration: underline;
        }

        .register-terms {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #567e9f;
            font-size: 0.8rem;
            margin: 0.8rem 0;
        }

        .register-terms input[type="checkbox"] {
            accent-color: #1f6fb0;
            width: 1rem;
            height: 1rem;
        }

        .register-terms a {
            color: #1f6fb0;
            text-decoration: none;
        }

        .register-terms a:hover {
            text-decoration: underline;
        }

        .register-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #567e9f;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            transition: color 0.2s;
            align-self: flex-start;
        }

        .register-back-link i {
            font-size: 0.9rem;
        }

        .register-back-link:hover {
            color: #1f6fb0;
        }

        /* bottom note - EXACT same */
        .register-bottom-note {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #567e9f;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .register-bottom-note .line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #9bbcdd, transparent);
            flex: 1;
        }

        .register-imiss {
            margin-top: 0.5rem;
            text-align: right;
            color: #567e9f;
            font-size: 0.9rem;
        }

        /* responsiveness */
        @media (max-width: 880px) {
            .grid-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .logo-hero {
                order: 1;
            }
            .register-container {
                order: 2;
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .register-badge {
                align-self: center;
            }
            .register-form-wrapper {
                margin: 0 auto;
            }
            .register-back-link {
                align-self: center;
            }
            .register-subtitle {
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 500px) {
            .register-card {
                padding: 1.8rem 1.2rem;
            }
            .logo-caption {
                font-size: 0.9rem;
            }
            .register-name-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
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

<!-- Department Checkboxes -->
<div class="department-section">
    <div class="department-header">
        <div class="department-title">
            <i class="fas fa-building"></i>
            Department i20 <span class="selected-count" id="selectedCount">0 selected</span>
        </div>
        <button type="button" class="select-all-btn" id="selectAllBtn" onclick="toggleSelectAll()">
            <i class="fas fa-check-double"></i> Select All
        </button>
    </div>
    
    <div class="checkbox-grid" id="departmentGrid">
        <?php foreach($departments as $index => $dept): ?>
            <div class="checkbox-item">
                <input type="checkbox" 
                        name="departments[]" 
                        value="<?= htmlspecialchars($dept) ?>" 
                        id="dept_<?= $index ?>"
                        <?= (isset($_POST['departments']) && in_array($dept, $_POST['departments'])) ? 'checked' : '' ?>>
                <label for="dept_<?= $index ?>"><?= htmlspecialchars($dept) ?></label>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!--  departments summary -->
    <div class="selected-summary" id="selectedSummary">
        <i class="fas fa-info-circle"></i>
        No departments selected yet
    </div>
</div>

<!-- Course/Program (Optional) -->
<div class="form-group">
    <label for="course">Course/Program (Optional)</label>
    <div class="input-with-icon">
        <i class="fas fa-graduation-cap"></i>
        <input type="text" id="course" name="course" class="form-control" 
                placeholder="Enter your course/program"
                value="<?= isset($_POST['course']) ? htmlspecialchars($_POST['course']) : '' ?>">
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

// Department checkbox functionality
function updateSelectedCount() {
const checkboxes = document.querySelectorAll('input[name="departments[]"]:checked');
const count = checkboxes.length;
const countElement = document.getElementById('selectedCount');
const summaryElement = document.getElementById('selectedSummary');

countElement.textContent = count + ' selected';

if (count > 0) {
const selected = Array.from(checkboxes).map(cb => cb.nextElementSibling.textContent);
let summary = '<i class="fas fa-check-circle text-success"></i> Selected: ';
summary += selected.map(name => `<span class="selected-badge">${name}</span>`).join(' ');
summaryElement.innerHTML = summary;
} else {
summaryElement.innerHTML = '<i class="fas fa-info-circle"></i> No departments selected yet';
}

// Update Select All button text
const selectAllBtn = document.getElementById('selectAllBtn');
if (count === <?= count($departments) ?>) {
selectAllBtn.innerHTML = '<i class="fas fa-times"></i> Deselect All';
} else {
selectAllBtn.innerHTML = '<i class="fas fa-check-double"></i> Select All';
}
}

function toggleSelectAll() {
const checkboxes = document.querySelectorAll('input[name="departments[]"]');
const checkedCount = document.querySelectorAll('input[name="departments[]"]:checked').length;

if (checkedCount === checkboxes.length) {
// Deselect all
checkboxes.forEach(cb => cb.checked = false);
} else {
// Select all
checkboxes.forEach(cb => cb.checked = true);
}

updateSelectedCount();
}

// Add event listeners to checkboxes
document.querySelectorAll('input[name="departments[]"]').forEach(checkbox => {
checkbox.addEventListener('change', updateSelectedCount);
});

// Initialize count on page load
updateSelectedCount();

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
const departments = document.querySelectorAll('input[name="departments[]"]:checked');

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
if (departments.length === 0) {
    e.preventDefault();
    showError('Please select at least one department/college');
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
form.insertBefore(errorDiv, form.firstChild);
} else {
errorDiv.querySelector('span').textContent = message;
}
errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Input animations
const inputs = document.querySelectorAll('.form-control, .otp-input, .checkbox-item');
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