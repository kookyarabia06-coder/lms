<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$courseId = intval($_GET['id'] ?? 0);
if(!$courseId) die('Invalid course ID');

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname 
                       FROM courses c 
                       LEFT JOIN users u ON c.proponent_id = u.id 
                       WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if(!$course) die('Course not found');

// Fetch enrollment if student
$enrollment = null;

if (is_student()) {
    // BLOCK if course expired or inactive
    $today = date('Y-m-d');
    if ($course['is_active'] == 0 || ($course['expires_at'] && $today > $course['expires_at'])) {
        die('<div class="alert alert-danger m-4">
                <h5>Course Unavailable</h5>
                <p>This course has expired or is no longer active.</p>
             </div>');
    }

    // Check enrollment
    $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id=? AND course_id=?');
    $stmt->execute([$u['id'], $courseId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        // Auto-create enrollment ONLY if course is valid
        $stmt = $pdo->prepare('
            INSERT INTO enrollments 
            (user_id, course_id, enrolled_at, status, progress, total_time_seconds) 
            VALUES (?, ?, NOW(), "ongoing", 0, 0)
        ');
        $stmt->execute([$u['id'], $courseId]);

        $enrollmentId = $pdo->lastInsertId();
        $enrollment = [
            'id' => $enrollmentId,
            'progress' => 0,
            'total_time_seconds' => 0,
            'status' => 'ongoing'
        ];
    } else {
        $enrollmentId = $enrollment['id'];
    }
}

// Handle AJAX time tracking
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['seconds']) && is_student()) {
    $seconds = intval($_POST['seconds']);
    $total_seconds = $enrollment['total_time_seconds'] + $seconds;
    $progress = $total_seconds; // adjust formula if needed
    $stmt = $pdo->prepare('UPDATE enrollments SET total_time_seconds=?, progress=? WHERE id=?');
    $stmt->execute([$total_seconds, $progress, $enrollment['id']]);
    echo json_encode(['success'=>true]);
    exit;
}

// Handle completion with minimum time requirement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed']) && is_student()) {
    // Fetch latest total_time_seconds
    $stmt = $pdo->prepare("SELECT total_time_seconds FROM enrollments WHERE id=?");
    $stmt->execute([$enrollment['id']]);
    $totalTime = (int)$stmt->fetchColumn();

    if ($totalTime < 60) {
        echo json_encode([
            'success' => false,
            'message' => 'You must spend at least 60 seconds in this course before completing it.'
        ]);
        exit;
    }

    // Mark as completed
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'completed', completed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$enrollment['id']]);

    echo json_encode(['success' => true]);
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?=htmlspecialchars($course['title'])?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>
<<<<<<< HEAD
<body class="bg-light d-flex">
    <div class="sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
=======
<body class="d-flex">
    <div class="sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
<div class="main flex-1 p-4">
    <h3><?=htmlspecialchars($course['title'])?></h3>
    <p><?=nl2br(htmlspecialchars($course['description']))?></p>

    <?php if($course['file_pdf']): ?>
    <div class="mb-3">
        <h5>PDF:</h5>
        <iframe
            src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
            width="100%"
            height="800px"
            style="border:1px solid #ccc">
        </iframe>

        <p class="mt-2">
            <a class="btn btn-sm btn-outline-primary"
            href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
            target="_blank">
                Open PDF in new tab
            </a>
        </p>
>>>>>>> f95964b909f01529c15cae74dd9c428ae4617bcf
    </div>

    <div class="main flex-1 p-4" style="padding-right: 10px; margin-left: 250px">
        <h3><?=htmlspecialchars($course['title'])?></h3>
        <p><?=nl2br(htmlspecialchars($course['description']))?></p>

        <?php if($course['file_pdf']): ?>
        <div class="mb-3">
            <h5>PDF:</h5>
            <iframe
                src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                width="100%"
                height="600"
                style="border:1px solid #ccc">
            </iframe>
            <p class="mt-2">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                   target="_blank">
                    Open PDF in new tab
                </a>
            </p>
        </div>
        <?php endif; ?>

        <?php if($course['file_video']): ?>
        <div class="mb-3">
            <h5>Video:</h5>
            <video id="courseVideo" width="100%" controls>
                <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars($course['file_video']) ?>" type="video/mp4">
                Your browser does not support HTML5 video.
            </video>
        </div>
        <?php if($enrollment['status'] === 'completed'): ?>
            <span class="badge bg-success">Completed</span>
            <div class="alert alert-success mt-2">
                You have completed this course 🎓
            </div>
        <?php else: ?>
            <span class="badge bg-warning">Ongoing</span>
        <?php endif; ?>
        <?php endif; ?>

        <?php if(is_student()): ?>
        <div class="mb-3">
            <strong>Time spent:</strong> <span id="timeSpent"><?= intval($enrollment['total_time_seconds']) ?></span> seconds
        </div>

        <!-- Complete Button -->
        <?php if($enrollment['status'] !== 'completed'): ?>
            <button id="completeBtn" class="btn btn-success mb-3">Mark as Complete</button>
        <?php endif; ?>

        <script>
        let totalSeconds = parseInt($('#timeSpent').text());

        // auto update time spent
        setInterval(function(){
            totalSeconds++;
            $('#timeSpent').text(totalSeconds);
            $.post(window.location.href, {seconds:1});
        },1000);

        // PDF / Video Completion
        let pdfReadSeconds = 0;
        let pdfCompleted = false;
        <?php if($course['file_pdf']): ?>
        setInterval(function () {
            if (pdfCompleted) return;
            pdfReadSeconds++;
            if (pdfReadSeconds >= 60) { // 60 seconds reading
                pdfCompleted = true;
                completeCourse();
            }
        }, 1000);
        <?php endif; ?>

        // Video ended
        $('#courseVideo').on('ended', function () {
            completeCourse();
        });

        // Complete button click
        $('#completeBtn').on('click', function(){
            completeCourse();
        });

        function completeCourse(){
            $.post(window.location.href, { mark_completed: 1 }, function (res) {
                try {
                    let data = JSON.parse(res);
                    if (data.success) {
                        alert('Course marked as completed 🎉');
                        location.reload();
                    } else {
                        alert(data.message || 'Unable to complete course.');
                    }
                } catch(e) {
                    alert('Unexpected error.');
                }
            });
        }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
