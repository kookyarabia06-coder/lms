<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$courseId = intval($_GET['id'] ?? 0);
if(!$courseId) die('Invalid course ID');

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if(!$course) die('Course not found');

// Fetch enrollment if student
$enrollment = null;

if (is_student()) {

    // BLOCK if course expired or inactive
    $today = date('Y-m-d');

    if (
        $course['is_active'] == 0 ||
        ($course['expires_at'] && $today > $course['expires_at'])
    ) {
       
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

// Handle completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed']) && is_student()) {
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'completed', completed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$enrollment['id']]);

    echo json_encode(['success' => true]);
    exit;
}


//list all studen enrolled in course
$stmt = $pdo->prepare('
    SELECT u.id, u.fname, u.lname 
    FROM enrollments e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.course_id = ? AND e.status = "completed" 
');
$stmt->execute([$courseId]);
$enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

//list of users
$stmt = $pdo->prepare('SELECT id, fname, lname FROM users');
// $stmt->execute($users);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

//lif of users who is enrolled to this course
$stmt = $pdo->prepare('SELECT u.id, u.fname, u.lname 
FROM enrollments e
JOIN users u ON e.user_id = u.id 
WHERE e.course_id = ?');
$stmt->execute([$courseId]);
$enrolledUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?=htmlspecialchars($course['title'])?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
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
                    <?= substr($course['fname'] ?? 'I', 0, 1) . substr($course['lname'] ?? 'nstructor', 0, 1) ?>
                </div>
                <div class="instructor-info">
                    <h5><?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></h5>
                    <p>Course Instructor</p>
                </div>
            </div>
        </div>

        <!-- Progress Section for Students -->
        <?php if(is_student()): ?>
        <div class="progress-section">
            <div class="progress-header">
                <h5><i class="fas fa-chart-line me-2"></i>Your Progress</h5>
                <div class="time-spent">
                    Time spent: <span id="timeSpent"><?= intval($enrollment['total_time_seconds']) ?></span> seconds
                </div>
            </div>
            
            <div class="status-container">
                <?php if($enrollment['status'] === 'completed'): ?>
                    <span class="badge bg-success">
                        <i class="fas fa-check-circle me-2"></i>Completed
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning">
                        <i class="fas fa-spinner me-2"></i>Ongoing
                    </span>
                <?php endif; ?>
            </div>

            <?php if($enrollment['status'] === 'completed'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-graduation-cap me-2"></i>Congratulations! You have successfully completed this course 🎓
                </div>
            <?php endif; ?>

            <!-- Complete Button -->
            <?php if($enrollment['status'] !== 'completed'): ?>
                <button id="completeBtn" class="btn btn-success">
                    <i class="fas fa-check-circle me-2"></i>Mark as Complete
                </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- PDF Content -->
        <?php if($course['file_pdf']): ?>
        <div class="content-card">
            <h5><i class="fas fa-file-pdf text-danger"></i>Course PDF Material</h5>
            
            <div class="pdf-viewer">
                <iframe
                    src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                    width="100%"
                    height="600"
                    style="border:none">
                </iframe>
            </div>

            <p class="mt-3">
                <a class="btn btn-outline-primary"
                   href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                   target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>Open PDF in new tab
                </a>
            </p>
        </div>
        <?php endif; ?>

        <!-- Video Content -->
        <?php if($course['file_video']): ?>
        <div class="content-card">
            <h5><i class="fas fa-video text-primary"></i>Course Video</h5>
            
            <div class="video-player">
                <video id="courseVideo" width="100%" controls>
                    <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars($course['file_video']) ?>" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
            </div>
        </div>
        <?php endif; ?>

      
      <!-- //list of users who is enrolled to this course -->
        <?php if(is_admin() || is_proponent()): ?>
            <?php if(count($enrolledUsers) > 0): ?>     
                <div class="content-card">
                    <h5><i class="fas fa-users text-info"></i>Enrolled Students</h5>
                        <ul class="list-group">
                            <?php foreach($enrolledUsers as $eu): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-user me-2"></i>
                                    <?= htmlspecialchars($eu['fname']) ?> <?= htmlspecialchars($eu['lname']) ?> 
                                </li>
                            <?php endforeach; ?>
                        </ul>
                </div>  
            <?php endif; ?>
        <?php endif; ?>

    <script>
    <?php if(is_student()): ?>
    let totalSeconds = parseInt($('#timeSpent').text());

    // auto update time spent
    setInterval(function(){
        totalSeconds++;
        $('#timeSpent').text(totalSeconds);
        $.post(window.location.href, {seconds:1});
    },1000);

    // PDF / Video Completion
// kapag natapos ung video mag oon ung button
const video = document.getElementById('courseVideo');if (video) {
const button = document.getElementById('completeBtn'); button.disabled = true;}

// sa button to
video.addEventListener('ended', (event) => {
   
    button.disabled = false;
});
    let pdfReadSeconds = 0;
    let pdfCompleted = false;
    <?php if($course['file_pdf']): ?>
    setInterval(function () {
        if (pdfCompleted) return;
        pdfReadSeconds++;
        if (pdfReadSeconds >= auto) { // 60 seconds reading
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
        $.post(window.location.href, { mark_completed: 1 }, function () {
            alert('Course marked as completed 🎉');
            location.reload();
        });
    }
    <?php endif; ?>
    
    // Add animations
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.content-card, .progress-section, .course-info-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
    </script>
</body>
</html>