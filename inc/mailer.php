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
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #2c3e50; text-align: center; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #7f8c8d; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Email Verification</h2>
                <p>Hello ' . htmlspecialchars($recipientName) . ',</p>
                <p>Thank you for registering. Use the OTP below to verify your email address:</p>
                
                <div class="otp-code">' . $otpCode . '</div>
                
                <p>This OTP is valid for 10 minutes.</p>
                <p>If you didn\'t request this, please ignore this email.</p>
                
                <div class="footer">
                    <p>Best regards,<br>Your App Team</p>
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