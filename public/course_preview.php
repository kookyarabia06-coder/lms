<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$courseId = intval($_GET['id'] ?? 0);
if(!$courseId) die('Invalid course ID');

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if(!$course) die('Course not found');



// Fetch all courses with enrollment info
$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description, 
        c.summary,
        c.thumbnail,
        c.created_at, 
        c.expires_at AS course_expires_at,
        e.status AS enroll_status,
        e.progress, 
        e.total_time_seconds,
        e.enrolled_at,
        c.proponent_id
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?=htmlspecialchars($course['title'])?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="course-content-wrapper">
        <!-- Course Header -->
        <div class="course-header">
            <h3><?=htmlspecialchars($course['title'])?></h3>
            <p><?=nl2br(htmlspecialchars($course['description']))?></p>
        </div>

        <!-- Course Info -->
        <div class="course-info-card">
            <div class="course-instructor">
                <div class="instructor-avatar">
                    <?= substr($course['fname'] ?? 'I', 0, 1) . substr($course['lname'] ?? 'Instructor', 0, 1) ?>
                </div>
                <div class="instructor-info">
                    <h5><?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></h5>
                    <p>Course Instructor</p>
                </div>
            </div>
            <div class="modern-course-info-meta">
                <div>
                    <div class="meta-item"><span>Created on: <?= date('F j, Y', strtotime($course['created_at'] ?? '')) ?></span></div>
                    <div class="meta-item"><span>Expires on: <?= $course['expires_at'] ? date('F j, Y', strtotime($course['expires_at'])) : 'No expiration' ?></span></div>
                </div> 
            </div>
            <div class="modern-card-actions mt-3">
                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $course['id'] ?>"
                        class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Enroll Now
                    </a>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="course-info-card">
            <div class="content-header">
                <h4>Course Preview</h4>
            </div>
            <div class="modern-course-info-content">
                <!-- TEST1 -->
                <?= $course['summary'] ?? '<p>No preview available.</p>' ?>
            </div>
        </div>
    </div>
</body>
</html>