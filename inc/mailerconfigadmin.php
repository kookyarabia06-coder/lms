<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration from a separate file
$smtpConfig = [
    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
    'username' => defined('SMTP_USERNAME') ? trim(SMTP_USERNAME) : 'learningmanagement576@gmail.com',
    'password' => defined('SMTP_PASSWORD') ? trim(SMTP_PASSWORD) : 'ahkv dpsl urcn lbmr',
    'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
    'from_email' => defined('SMTP_FROM_EMAIL') ? trim(SMTP_FROM_EMAIL) : 'learningmanagement576@gmail.com',
    'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'LMS'
];

function sendConfirmationEmail($recipientEmail, $recipientName) {
    global $smtpConfig;
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        // Sender and recipient
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Verified - LMS';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
                .verified-badge { background: #28a745; color: white; padding: 10px 20px; border-radius: 50px; display: inline-block; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #7f8c8d; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Account Verified Successfully!</h2>
                <p>Hello ' . htmlspecialchars($recipientName) . ',</p>
                <p>Your account has been verified by an administrator.</p>
                
                <div class="verified-badge">
                    ✓ Account Verified
                </div>
                
                <p>You can now access all features of the LMS.</p>
                <p><a href="' . BASE_URL . '/login.php">Login to your account</a></p>
                
                <div class="footer">
                    <p>Best regards,<br>LMS Team</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Your account has been verified. You can now login to LMS.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Confirmation email sent'];
        
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return ['success' => false, 'message' => "Failed to send email: " . $e->getMessage()];
    }
}

// Keep simple version for testing
function sendConfirmationEmailSimple($email, $name) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Confirmation email for $name ($email)");
        return ['success' => true, 'message' => 'Email logged locally'];
    }
    return sendConfirmationEmail($email, $name);

    function sendWelcomeEmail($recipientEmail, $recipientName, $username, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings (same as your other function)
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? trim(SMTP_USERNAME) : 'learningmanagement576@gmail.com';
        $mail->Password   = defined('SMTP_PASSWORD') ? trim(SMTP_PASSWORD) : 'ahkvdpslurcnlbmr'; // Remove spaces!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        
        // Sender and recipient
        $fromEmail = defined('SMTP_FROM_EMAIL') ? trim(SMTP_FROM_EMAIL) : 'learningmanagement576@gmail.com';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'LMS Admin';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to LMS - Your Account Has Been Created';
        
        // HTML Email Body
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; margin: -30px -30px 20px -30px; }
                .credentials { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .cred-box { background: white; padding: 10px; border-left: 4px solid #667eea; margin: 10px 0; font-family: monospace; }
                .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #7f8c8d; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to LMS!</h1>
                </div>
                
                <h2>Hello ' . htmlspecialchars($recipientName) . ',</h2>
                
                <p>Your account has been created by an administrator. You can now access the Learning Management System.</p>
                
                <div class="credentials">
                    <h3>Your Login Credentials:</h3>
                    
                    <div class="cred-box">
                        <strong>Username:</strong> ' . htmlspecialchars($username) . '
                    </div>
                    
                    <div class="cred-box">
                        <strong>Password:</strong> ' . htmlspecialchars($password) . '
                    </div>
                    
                    <p style="color: #dc3545; font-size: 14px; margin-top: 15px;">
                        <strong>⚠️ Important:</strong> For security reasons, please change your password after logging in.
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <a href="' . BASE_URL . '/login.php" class="button">Login to Your Account</a>
                </div>
                
                <div class="footer">
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>Best regards,<br>LMS Team</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Plain text alternative
        $mail->AltBody = "Welcome to LMS!\n\nYour account has been created.\n\nUsername: $username\nPassword: $password\n\nPlease change your password after logging in.\n\nLogin at: " . BASE_URL . "/login.php";
        
        $mail->send();
        return ['success' => true, 'message' => 'Welcome email sent successfully'];
        
    } catch (Exception $e) {
        error_log('Welcome email failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Also add a simple version for local testing
function sendWelcomeEmailSimple($email, $name, $username, $password) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Welcome email for $name ($email) - Username: $username, Password: $password");
        return ['success' => true, 'message' => 'Welcome email logged locally'];
    }
    return sendWelcomeEmail($email, $name, $username, $password);
}
}
?>