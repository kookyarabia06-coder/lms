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
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: #e8eef4;
                    padding: 40px 20px;
                    min-height: 100vh;
                }
                .email-wrapper { max-width: 560px; margin: 0 auto; }
                .email-card {
                    background: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 12px rgba(0, 35, 102, 0.12);
                    border: 1px solid #d0dae6;
                }
                .email-header {
                    background: linear-gradient(135deg, #002366 0%, #1a4d8f 100%);
                    padding: 36px 32px;
                    text-align: center;
                }
                .email-header h1 {
                    font-size: 24px;
                    font-weight: 600;
                    color: #ffffff;
                    letter-spacing: 0.3px;
                }
                .email-body {
                    padding: 36px 32px;
                }
                .greeting {
                    font-size: 15px;
                    color: #0c2e45;
                    margin-bottom: 18px;
                    line-height: 1.6;
                }
                .greeting strong {
                    color: #002366;
                }
                .message {
                    font-size: 14px;
                    color: #2b4e6b;
                    line-height: 1.7;
                    margin-bottom: 22px;
                }
                .info-panel {
                    background: #f5f8fc;
                    border: 1px solid #c5d5e6;
                    border-radius: 6px;
                    padding: 18px 22px;
                    margin: 26px 0;
                }
                .info-panel p {
                    margin: 0;
                    color: #1a4d8f;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .info-panel strong {
                    color: #002366;
                }
                .divider {
                    height: 1px;
                    background: #d8e2ec;
                    margin: 28px 0;
                    border: none;
                }
                .footer {
                    background: #fafbfc;
                    padding: 26px 32px;
                    border-top: 1px solid #d8e2ec;
                    text-align: center;
                }
                .footer p {
                    margin: 0 0 6px 0;
                    font-size: 13px;
                    color: #567e9f;
                }
                .footer strong {
                    color: #2b4e6b;
                }
                .footer-note {
                    margin-top: 14px;
                    padding-top: 14px;
                    border-top: 1px solid #e2e8f0;
                    font-size: 11px;
                    color: #7a95a8;
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-card">
                    <div class="email-header">
                        <h1>Account Verified</h1>
                    </div>

                    <div class="email-body">
                        <p class="greeting">Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                        
                        <p class="message">
                            Your account has been verified by an administrator.
                            You now have full access to the Learning Management System.
                        </p>

                        <div class="info-panel">
                            <p>
                                <strong>Access Granted:</strong> You can now access all features including 
                                courses, assignments, learning materials, and track your progress through the dashboard.
                            </p>
                        </div>

                        <hr class="divider">

                        <p class="message" style="margin-bottom: 0;">
                            For any assistance, please contact your system administrator or the LMS support team.
                        </p>
                    </div>

                    <div class="footer">
                        <p>Best regards,</p>
                        <p><strong>LMS Team</strong></p>
                        <p class="footer-note">This is an automated message, please do not reply.</p>
                    </div>
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
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #002366 0%, #1a4d8f 100%);
                    padding: 40px 20px;
                    min-height: 100vh;
                }
                .email-wrapper { max-width: 600px; margin: 0 auto; }
                .email-card { 
                    background: rgba(255,255,255,0.95);
                    backdrop-filter: blur(8px);
                    border-radius: 56px;
                    padding: 48px 40px;
                    box-shadow: 0 30px 60px -20px rgba(0,40,80,0.25);
                    border: 1px solid rgba(255,255,255,0.6);
                }
                .header { 
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #e2e8f0;
                }
                .header h1 {
                    font-size: 32px;
                    font-weight: 700;
                    background: linear-gradient(135deg, #1f6392, #0a3b58);
                    -webkit-background-clip: text;
                    background-clip: text;
                    color: transparent;
                    margin-bottom: 10px;
                }
                .credentials { 
                    background: rgba(248, 250, 252, 0.8);
                    padding: 24px; 
                    border-radius: 16px; 
                    margin: 24px 0;
                    border: 1px solid #e2e8f0;
                }
                .credentials h3 {
                    font-size: 18px;
                    font-weight: 600;
                    color: #1e3c72;
                    margin-bottom: 16px;
                }
                .cred-box { 
                    background: rgba(255,255,255,0.9);
                    padding: 14px 18px; 
                    border-radius: 12px;
                    margin: 12px 0; 
                    font-family: "Inter", system-ui, monospace;
                    border-left: 4px solid #1f6fb0;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                }
                .cred-box strong {
                    color: #144a6f;
                    margin-right: 8px;
                }
                .cred-box span {
                    color: #0c2e45;
                    font-weight: 500;
                }
                .warning-box {
                    background: rgba(220, 53, 69, 0.1);
                    border: 1px solid rgba(220, 53, 69, 0.3);
                    border-radius: 50px;
                    padding: 12px 18px;
                    margin-top: 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .warning-box i {
                    color: #dc3545;
                    font-size: 16px;
                }
                .warning-box p {
                    color: #dc3545;
                    font-size: 13px;
                    margin: 0;
                    font-weight: 500;
                }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                    text-align: center;
                    font-size: 14px; 
                    color: #567e9f; 
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-card">
                    <div class="header">
                        <h1>Welcome to LMS!</h1>
                    </div>

                    <div class="content">
                        <p>Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                        <p>Your account has been created by an administrator. You can now access the Learning Management System.</p>

                        <div class="credentials">
                            <h3>Your Login Credentials:</h3>

                            <div class="cred-box">
                                <strong>Username:</strong>
                                <span>' . htmlspecialchars($username) . '</span>
                            </div>

                            <div class="cred-box">
                                <strong>Password:</strong>
                                <span>' . htmlspecialchars($password) . '</span>
                            </div>

                            <div class="warning-box">
                                <i>⚠️</i>
                                <p><strong>Important:</strong> For security reasons, please change your password after logging in.</p>
                            </div>
                        </div>
                    </div>

                    <div class="footer">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>Best regards,<br><strong>LMS Team</strong></p>
                    </div>
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