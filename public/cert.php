<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");
 $stmt->execute([$userId]);
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
//certifcate area 

?>
<!DOCTYPE html>
<html lang="en">
<head>Certificate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>??" rel="stylesheet">
<title>Certificate</title>
<body>
<p>
  Dear.   <?= htmlspecialchars($_SESSION['user']['fname'] ?? '') ?> <?= htmlspecialchars($_SESSION['user']['lname'] ?? '') ?>,
    <br><br>
    congtv
    <img src="<?= BASE_URL ?>/uploads/certificates/certificate_<?= $userId ?>.png" alt="Certificate Image" style="max-width: 100%; height: auto;">
</p>



<button onclick="window.history.back()" class="btn btn-secondary">Back</button>
</body>
</html>