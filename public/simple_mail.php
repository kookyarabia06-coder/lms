<?php


// gumaganato
require 'vendor/autoload.php'; // Path to autoload.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

//otp generator   jasbdkjabskjdbfkajsbd
    $otp_code= str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';    // SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'learningmanagement576@gmail.com';  // SMTP username
    $mail->Password   = 'ahkv dpsl urcn lbmr';     // SMTP password (use App Password for Gmail)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
    $mail->Port       = 587;                    // TCP port to connect to
    
    // Sender & Recipients
    $mail->setFrom('learningmanagement576@gmail.com', 'Your Name');
    $mail->addAddress('faith2miyuki@gmail.com', 'Recipient Name');
    
    // Optional: Add CC, BCC, Reply-To
    $mail->addCC('cc@example.com');
    $mail->addBCC('bcc@example.com');
    $mail->addReplyTo('reply@example.com', 'Reply To');
    
    // Content
    $mail->isHTML(true); // Set email format to HTML
    $mail->Subject = 'Test Email Subject';
    $mail->Body    = '<h1>OTP: ' . $otp_code . '</h1>';
   $mail->AltBody = "\nOTP: " . $otp_code;

    // Attachments (optional)
    // $mail->addAttachment('/path/to/file.pdf', 'filename.pdf');
    
    // Send email
    $mail->send();
    header('Location: verify.php?status=success');    
    
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

?>