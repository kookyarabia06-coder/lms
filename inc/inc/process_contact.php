<?php
require_once __DIR__ . '/../inc/config.php';
session_start();

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate
$errors = [];

if (empty($name)) {
$errors[] = "Name is required";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
$errors[] = "Valid email is required";
}

if (empty($subject)) {
$errors[] = "Subject is required";
}

if (empty($message)) {
$errors[] = "Message is required";
}

if (!empty($errors)) {
$_SESSION['contact_errors'] = $errors;
header('Location: contact.php?status=error');
exit;
}

try {
// Get IP and user agent
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';


$stmt = $pdo->prepare("
INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent, created_at)
VALUES (?, ?, ?, ?, ?, ?, NOW())
");

$stmt->execute([$name, $email, $subject, $message, $ip_address, $user_agent]);

// debuging
/*
$to = "admin@yourdomain.com";
$email_subject = "New Contact Form Message: $subject";
$email_message = "Name: $name\nEmail: $email\n\nMessage:\n$message";
$headers = "From: $email";
mail($to, $email_subject, $email_message, $headers);
*/

header('Location: contact.php?status=success');
exit;

} catch (Exception $e) {
error_log("Contact form error: " . $e->getMessage());
header('Location: contact.php?status=error');
exit;
}
?>