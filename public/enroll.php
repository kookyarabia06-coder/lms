<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

// Only logged-in users can enroll
if(!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user']['id'];

// Check both GET and POST for course_id
$courseId = 0;
if (isset($_POST['course_id'])) {
    $courseId = intval($_POST['course_id']);
} elseif (isset($_GET['course_id'])) {
    $courseId = intval($_GET['course_id']);
}

if(!$courseId) {
    $_SESSION['error_message'] = 'Invalid course ID. Please try again.';
    header('Location: courses.php'); // Students go to courses.php
    exit;
}

// Check if course exists and is active
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND is_active = 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if(!$course) {
    $_SESSION['error_message'] = 'Course not found or inactive.';
    header('Location: courses.php'); // Students go to courses.php
    exit;
}

// Check if user is already enrolled
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $courseId]);
$enrollment = $stmt->fetch();

if($enrollment) {
    // Already enrolled, redirect back with message
    $_SESSION['message'] = "You are already enrolled in '{$course['title']}'";
    header('Location: courses.php'); // Students go to courses.php
    exit;
}

// Enroll the user
$stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_at, status, progress, total_time_seconds) VALUES (?, ?, NOW(), 'ongoing', 0, 0)");
$stmt->execute([$userId, $courseId]);

$_SESSION['success_message'] = "Successfully enrolled in '{$course['title']}'";
header("Location: course_view.php?id={$courseId}");
exit;
?>