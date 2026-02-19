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
<title>Register - Create Account</title>
<link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Additional CSS for OTP functionality */
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

/* Department Checkbox Styles */
.department-section {
margin: 20px 0;
padding: 15px;
background: #f8f9fa;
border-radius: 8px;
border: 1px solid #e0e0e0;
}

.department-title {
font-size: 14px;
font-weight: 600;
color: #555;
margin-bottom: 15px;
display: flex;
align-items: center;
}

.department-title i {
color: #667eea;
margin-right: 8px;
}

.checkbox-grid {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
gap: 12px;
max-height: 300px;
overflow-y: auto;
padding: 5px;
}

.checkbox-item {
display: flex;
align-items: center;
padding: 8px 12px;
background: white;
border: 1px solid #e0e0e0;
border-radius: 8px;
transition: all 0.2s;
cursor: pointer;
}

.checkbox-item:hover {
background: #e7f1ff;
border-color: #667eea;
transform: translateY(-2px);
box-shadow: 0 4px 8px rgba(102, 126, 234, 0.1);
}

.checkbox-item input[type="checkbox"] {
width: 18px;
height: 18px;
margin-right: 12px;
cursor: pointer;
accent-color: #667eea;
}

.checkbox-item label {
cursor: pointer;
font-size: 14px;
color: #333;
flex: 1;
}

.selected-count {
display: inline-block;
background: #667eea;
color: white;
padding: 4px 12px;
border-radius: 50px;
font-size: 12px;
margin-left: 10px;
}

.select-all-btn {
background: none;
border: 1px dashed #667eea;
color: #667eea;
padding: 5px 15px;
border-radius: 20px;
font-size: 12px;
cursor: pointer;
transition: all 0.2s;
margin-left: auto;
}

.select-all-btn:hover {
background: #667eea;
color: white;
}

.department-header {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 15px;
}

/* Alert styles */
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

/* Selected departments summary */
.selected-summary {
margin-top: 10px;
font-size: 13px;
color: #666;
background: white;
padding: 8px 12px;
border-radius: 6px;
border: 1px solid #e0e0e0;
}

.selected-badge {
display: inline-block;
background: #e7f1ff;
color: #667eea;
padding: 4px 10px;
border-radius: 50px;
font-size: 12px;
margin: 2px;
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