<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Get database connection
global $pdo;

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$courseId = intval($_GET['id'] ?? 0);

if (!$courseId) {
    die('Invalid course ID');
}
























// Fetch course with enrollment info for current user
$stmt = $pdo->prepare('
    SELECT 
        c.*, 
        u.fname, 
        u.lname,
        e.status AS enroll_status,
        e.progress,
        e.total_time_seconds,
        e.enrolled_at
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.id = ? AND c.is_active = 1
');






























$stmt->execute([$userId, $courseId]);
$course = $stmt->fetch();

if (!$course) {
    die('Course not found');
}






























// Check if course is expired
$isExpired = false;
if (!empty($course['expires_at'])) {
    $expiresAt = strtotime($course['expires_at']);
    $now = time();
    $isExpired = ($expiresAt < $now);
}

// Set enrollment status (override if expired)
$enrollStatus = $course['enroll_status'] ?? 'notenrolled';
if ($isExpired && $enrollStatus === 'ongoing') {
    $enrollStatus = 'expired';
}






























// CHECK IF USER HAS ANY ACTIVE ENROLLMENT (excluding current course)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_count 
    FROM enrollments 
    WHERE user_id = ? AND status = 'ongoing' AND course_id != ?
");
$stmt->execute([$userId, $courseId]);
$activeEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$hasActiveEnrollment = ($activeEnrollment['active_count'] > 0);

// Get the active course details if exists
$activeCourseId = null;
$activeCourseTitle = null;
if ($hasActiveEnrollment) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title 
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ? AND e.status = 'ongoing' AND e.course_id != ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $courseId]);
    $activeCourse = $stmt->fetch();
    if ($activeCourse) {
        $activeCourseId = $activeCourse['id'];
        $activeCourseTitle = $activeCourse['title'];
    }
}



























// Fetch all courses with enrollment info (for other queries you might need)
$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description, 
        c.summary,
        c.thumbnail,
        c.created_at, 
        c.expires_at AS course_expires_at,
        CASE 
            WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
            ELSE COALESCE(e.status, 'notenrolled')
        END AS enroll_status,
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

// Fetch user's enrolled courses
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.created_at, c.expires_at,
           e.progress, e.total_time_seconds, 
           CASE 
               WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
               ELSE e.status 
           END AS enroll_status
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);





















// Handle enrollment POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    // Check if course is expired
    if ($isExpired) {
        $_SESSION['error'] = "Cannot enroll: This course has expired.";
        header("Location: course.php?id=$courseId");
        exit;
    }
    
    // Check if already enrolled
    if ($enrollStatus === 'ongoing') {
        $_SESSION['error'] = "You are already enrolled in this course.";
        header("Location: course.php?id=$courseId");
        exit;
    }
    
    // CHECK FOR ACTIVE ENROLLMENT
    if ($hasActiveEnrollment) {
        $_SESSION['error'] = "You can only be enrolled in one course at a time. Please complete or drop your current course: <strong>" . htmlspecialchars($activeCourseTitle) . "</strong>";
        header("Location: course.php?id=$courseId");
        exit;
    }
    
    // Proceed with enrollment
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO enrollments (user_id, course_id, status, enrolled_at, progress, total_time_seconds) 
            VALUES (?, ?, 'ongoing', NOW(), 0, 0)
        ");
        $stmt->execute([$userId, $courseId]);
        
        $pdo->commit();
        $_SESSION['success'] = "Successfully enrolled in the course!";
        header("Location: " . BASE_URL . "/public/course_view.php?id=$courseId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Enrollment failed: " . $e->getMessage();
        header("Location: course.php?id=$courseId");
        exit;
    }
}
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
<style>
        .btn-expired {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: white !important;
            cursor: not-allowed !important;
            opacity: 0.65;
            pointer-events: none;
        }
        .btn-enrolled {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
            cursor: not-allowed !important;
            opacity: 0.65;
            pointer-events: none;
        }
        .btn-locked {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #212529 !important;
            cursor: not-allowed !important;
            opacity: 0.65;
            pointer-events: none;
        }
        .expired-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            background-color: #dc3545;
            margin-left: 10px;
        }
        .active-course-alert {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .active-course-link {
            color: #533f03;
            font-weight: bold;
            text-decoration: underline;
        }
</style>
</head>
<body>
    <!-- Sidebar -->
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

    <!-- Main Content -->
<div class="course-content-wrapper">
        <!-- Display session messages -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
<?= $_SESSION['success'] ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
<?= $_SESSION['error'] ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

        <!-- Warning if user is already enrolled in another course -->
<?php if ($hasActiveEnrollment && $enrollStatus !== 'ongoing' && !$isExpired): ?>
<div class="active-course-alert">
<i class="fas fa-info-circle"></i> 
<strong>You have an active enrollment:</strong> 
You are currently enrolled in <a href="course.php?id=<?= $activeCourseId ?>" class="active-course-link"><?= htmlspecialchars($activeCourseTitle) ?></a>. 
You can only be enrolled in one course at a time. Please complete or drop your current course before enrolling in a new one.
</div>
<?php endif; ?>

        <!-- Course Header -->
<div class="course-header">
<h3>
<?=htmlspecialchars($course['title'])?>
<?php if ($isExpired): ?>
<span class="expired-badge">EXPIRED</span>
<?php endif; ?>
</h3>
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
<div class="meta-item">
<i class="fas fa-calendar-alt"></i>
<span>Created on: <?= date('F j, Y', strtotime($course['created_at'] ?? '')) ?></span>
</div>
<div class="meta-item">
<i class="fas fa-clock"></i>
<span>Expires on: <?= $course['expires_at'] ? date('F j, Y', strtotime($course['expires_at'])) : 'No expiration' ?></span>
 <?php if ($isExpired): ?>
 <span class="badge bg-danger ms-2">Expired</span>
<?php endif; ?>
</div>
</div> 
</div>

<div class="modern-card-actions mt-3">
<?php if ($isExpired): ?>
                    <!-- EXPIRED - Gray Button -->
<button class="btn btn-expired" disabled>
<i class="fas fa-hourglass-end"></i> Course Expired
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> This course expired on <?= date('F j, Y', strtotime($course['expires_at'])) ?>
</small>
                    
<?php elseif ($enrollStatus === 'ongoing'): ?>
                    <!-- ALREADY ENROLLED - Green Button -->
<button class="btn btn-enrolled" disabled>
<i class="fas fa-check-circle"></i> Already Enrolled
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> You are already enrolled in this course. 
<a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $course['id'] ?>" class="text-primary">Continue Learning</a>
 </small>
                    
<?php elseif ($hasActiveEnrollment): ?>
<!-- LOCKED - Yellow Button (has other active enrollment) -->
<button class="btn btn-locked" disabled>
<i class="fas fa-lock"></i> Enrollment Locked
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> You are currently enrolled in 
  <a href="course.php?id=<?= $activeCourseId ?>"><?= htmlspecialchars($activeCourseTitle) ?></a>. 
 Complete that course first.
 </small>
                    
<?php else: ?>
                    <!-- ENROLL NOW POST form -->
<form method="POST" style="display: inline;">
<button type="submit" name="enroll" class="btn btn-primary">
<i class="fas fa-sign-in-alt"></i> Enroll Now
</button>
</form>
<?php if ($course['expires_at']): ?>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> This course expires on <?= date('F j, Y', strtotime($course['expires_at'])) ?>
</small>
<?php endif; ?>
<?php endif; ?>
</div>
</div>

<!-- Course Preview Section -->
<div class="mt-4">
<h4>Course Preview</h4>
<div class="modern-course-info-content p-3 border rounded">
<?= $course['summary'] ?? '<p class="text-muted">No preview available.</p>' ?>
</div>
</div>
</div>

<script>
$(document).ready(function() {
    // Confirmation for enrollment
    $('button[name="enroll"]').click(function(e) {
        if (!confirm('Are you sure you want to enroll in this course? You can only be enrolled in one course at a time.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>