<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer
// OR manually require if not using Composer:
// require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

function sendOTPEmail($recipientEmail, $recipientName, $otpCode) {
    $mail = new PHPMailer(true);
    
    try {
        // setting ng mail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'learningmanagement576@gmail.com '; // debugging
        $mail->Password   = 'ahkv dpsl urcn lbmr'; // debugging
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; //ecripto tang ina 
        $mail->Port       = 587; // TLS: 587, SSL: 465
        
        // logic 2
        $mail->setFrom('learningmanagement576@gmail.com ', 'LMS');
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code';
        
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
                .otp-box {
                    background: #f5f8fc;
                    border: 2px solid #c5d5e6;
                    border-radius: 8px;
                    padding: 24px;
                    margin: 26px 0;
                    text-align: center;
                }
                .otp-code {
                    font-size: 36px;
                    font-weight: 700;
                    color: #002366;
                    letter-spacing: 8px;
                    font-family: "Courier New", Courier, monospace;
                    margin: 12px 0;
                }
                .otp-label {
                    font-size: 12px;
                    color: #567e9f;
                    text-transform: uppercase;
                    letter-spacing: 1px;
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
                        <h1>Verification Code</h1>
                    </div>

                    <div class="email-body">
                        <p class="greeting">Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                        
                        <p class="message">
                            Thank you for registering. Use the OTP below to verify your email address.
                        </p>

                        <div class="otp-box">
                            <p class="otp-label">Your Verification Code</p>
                            <div class="otp-code">' . $otpCode . '</div>
                            <p style="font-size: 13px; color: #567e9f; margin: 8px 0 0 0;">Valid for 10 minutes</p>
                        </div>

                        <div class="info-panel">
                            <p>
                                <strong>Important:</strong> Please contact the admin for account activation 
                                after verifying your email address.
                            </p>
                        </div>

                        <hr class="divider">

                        <p class="message" style="margin-bottom: 0;">
                            If you didn\'t request this verification code, please ignore this email.
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
        
        // for formal email try lang
        $mail->AltBody = "Your OTP code: $otpCode\nValid for 10 minutes.\n\nIf you didn't request this, please ignore.";
        
        $mail->send();
        return ['success' => true, 'message' => 'OTP sent successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"];
    }
}

// For testing/development, you can use a simpler version or just bura this and run main
function sendOTPEmailSimple($email, $otp) {
    // For local development without SMTP
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        // Just log it for local development 
        error_log("Local dev - OTP for $email: $otp");
        return ['success' => true, 'message' => 'OTP logged locally'];
    }
    
    // Production - use actual email this is the MAIN
    return sendOTPEmail($email, 'User', $otp);
}
?>