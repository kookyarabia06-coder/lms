<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message)) $errors[] = "Message is required";
    
    if (empty($errors)) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'learningmanagement576@gmail.com'; // Your Gmail
            $mail->Password   = 'ahkv dpsl urcn lbmr';    // Your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Sender & Recipients
            $mail->setFrom('learningmanagement576@gmail.com', 'Website Contact Form');
            $mail->addAddress('$email, $fname');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "Contact Form: " . $subject;
            
            $mail->Body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> {$name}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Subject:</strong> {$subject}</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p>Sent from: " . $_SERVER['HTTP_HOST'] . "</p>
            ";
            
            $mail->AltBody = "
                New Contact Form Submission
                Name: {$name}
                Email: {$email}
                Subject: {$subject}
                Message: {$message}
            ";
            
            // Send email
            if ($mail->send()) {
                // Redirect with success message
                header('Location: contact_form.php?status=success');
                exit;
            } else {
                $errors[] = "Failed to send email. Please try again later.";
            }
            
        } catch (Exception $e) {
            $errors[] = "Message could not be sent. Error: " . $mail->ErrorInfo;
        }
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        echo "<div class='error'>";
        foreach ($errors as $error) {
            echo "<p>{$error}</p>";
        }
        echo "</div>";
        echo "<p><a href='contact_form.php'>Go back</a></p>";
    }
}
?>