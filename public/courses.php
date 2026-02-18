<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$isAdmin = is_admin();
$isProponent = is_proponent();

// check user ANY og kors
$stmt = $pdo->prepare("
    SELECT COUNT(*) as ongoing_count 
    FROM enrollments 
    WHERE user_id = ? AND status = 'ongoing'
");
$stmt->execute([$userId]);
$ongoingCount = $stmt->fetch(PDO::FETCH_ASSOC)['ongoing_count'];
$hasOngoingCourse = $ongoingCount > 0;

// enroll info
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
        e.completed_at,
        c.proponent_id,
        -- Determine display status
        CASE 
            WHEN e.id IS NULL THEN 'notenrolled'
            WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
            ELSE e.status
        END AS display_status
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY 
        -- Show ongoing courses first, then completed, then not enrolled
        CASE 
            WHEN e.status = 'ongoing' THEN 1
            WHEN e.status = 'completed' THEN 2
            ELSE 3
        END,
        c.id DESC
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT u.id, u.fname, u.lname 
FROM enrollments e
JOIN users u ON e.user_id = u.id 
WHERE e.course_id = ?');
$stmt->execute(['']);
$enrolledUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Courses - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/profile.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* company report staff sheet  */
    .enrollment-restriction {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        animation: slideDown 0.5s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .enrollment-restriction i {
        font-size: 24px;
        margin-right: 15px;
    }
    
    .restriction-content {
        display: flex;
        align-items: center;
        flex: 1;
    }
    
    .restriction-text {
        font-size: 16px;
        font-weight: 500;
    }
    
    .current-course-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 50px;
        font-size: 14px;
        margin-left: 15px;
    }
    
    .btn-disabled {
        opacity: 0.6;
        cursor: not-allowed;
        pointer-events: none;
        background: #6c757d;
        border-color: #6c757d;
    }
    
    .btn-disabled:hover {
        background: #6c757d;
        border-color: #6c757d;
    }
    
    .tooltip-icon {
        color: #ffc107;
        margin-left: 5px;
        cursor: help;
    }

    .tooltip-icon-ex {
        color: #ff0707;
        margin-left: 5px;
        cursor: help;
    }
    
    .course-locked {
        position: relative;
    }
    
    .lock-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        border-radius: 8px 8px 0 0;
    }
</style>
</head>
<body>
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>
<div class="modern-courses-wrapper">
<h2 class="modern-section-title">All Courses</h2>
        
        <!-- HINDI KOTO BUBURAHIN KASI RESTRICTION TO IF NAKALIMUTAN KO -->
<?php if ($hasOngoingCourse): ?>
<div class="enrollment-restriction">
<div class="restriction-content">
 <i class="fas fa-info-circle"></i>
<span class="restriction-text">
<strong>One course at a time policy:</strong> You need to complete your current course before enrolling in a new one.
</span>
<?php 
                    
$currentCourse = null;
foreach ($courses as $c) {
if ($c['display_status'] === 'ongoing') {
$currentCourse = $c;
break;
}
}
if ($currentCourse): 
?>
<span class="current-course-badge">
<i class="fas fa-play-circle"></i> Current: <?= htmlspecialchars(substr($currentCourse['title'], 0, 30)) ?>...
</span>
<?php endif; ?>
</div>
<a href="my_courses.php" class="btn btn-sm btn-outline-light mt-3">
<i class="fas fa-arrow-right"></i> Go to My Course
</a>
</div>
<?php endif; ?>
        
<?php if (empty($courses)): ?>
<div class="alert alert-info">
<i class="fas fa-info-circle"></i> No courses available at the moment.
</div>
<?php else: ?>
<div class="modern-courses-grid">
<?php foreach ($courses as $c): 
                // Check if course is expired
$isExpired = false;
if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
$isExpired = strtotime($c['course_expires_at']) < time();
}
                
                // enroll stats
$enroll_status = $c['enroll_status'] ?? $c['display_status'];
                
                // check if ufser can enroll in course
$canEnroll = (
$enroll_status === 'notenrolled' && 
!$isExpired && 
(!$hasOngoingCourse || $isAdmin) // addmin can enter idk sa prop if need kopa i add
);
                
                // chech confition if student ata or user can continue course
 $canContinue = (
($enroll_status === 'ongoing' || $enroll_status === 'completed') && 
!$isExpired
 );
                
                // enrollment = reason y btn is off
$enrollDisabledReason = '';
if ($enroll_status !== 'notenrolled') {
$enrollDisabledReason = 'You are already enrolled in this course';
} elseif ($isExpired) {
$enrollDisabledReason = 'This course has expired';
} elseif ($hasOngoingCourse && !$isAdmin) {
$enrollDisabledReason = 'Please Complete current course before enrolling in a new one.';
}
?>
                
<div class="modern-course-card <?= (!$canEnroll && $enroll_status === 'notenrolled') ? 'course-locked' : '' ?>">
<div class="modern-card-img">
<img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" 
alt="<?= htmlspecialchars($c['title']) ?>"
onerror="this.src='<?= BASE_URL ?>/uploads/images/Course Image.png'">
                        
                        <!-- lock oberlay  -->
<?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin): ?>
<div class="lock-overlay">
<i class="fas fa-lock"></i>
</div>
<?php endif; ?>
                    </div>

                    <div class="modern-card-body">
                        <div class="modern-card-title">
                            <h6><?= htmlspecialchars($c['title']) ?></h6>
                            <?php if ($c['display_status'] === 'ongoing'): ?>
                                <span class="modern-badge badge-ongoing">
                                    <i class="fas fa-play-circle"></i> Ongoing
                                </span>
                            <?php elseif ($c['display_status'] === 'completed'): ?>
                                <span class="modern-badge badge-completed">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            <?php elseif ($c['display_status'] === 'expired' || $isExpired): ?>
                                <span class="modern-badge badge-expired">
                                    <i class="fas fa-hourglass-end"></i> Expired
                                </span>
                            <?php elseif ($c['display_status'] === 'notenrolled'): ?>
                                <span class="modern-badge badge-notenrolled">
                                    <i class="fas fa-clock"></i> Not Enrolled
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Description -->
                        <p class="mt-2 mb-2">
                            <?= htmlspecialchars(substr($c['description'] ?? '', 0, 120)) ?>
                            <?php if (strlen($c['description'] ?? '') > 120): ?>...<?php endif; ?>
                        </p>
                        
                        <!-- Course Dates -->
                        <div class="modern-course-info">
                            <?php
                            $startDate = !empty($c['created_at']) 
                                ? date('M d, Y', strtotime($c['created_at']))
                                : 'Not set';
                            
                            $expiryDate = 'No expiry';
                            if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
                                $expiryDate = date('M d, Y', strtotime($c['course_expires_at']));
                            }
                            ?>
                            <p><strong><i class="fas fa-calendar-alt"></i> Start:</strong> <span><?= $startDate ?></span></p>
                            <p><strong><i class="fas fa-hourglass-half"></i> Expires:</strong> <span><?= $expiryDate ?></span></p>
                        </div>

<!-- progress bar -->
<?php if (isset($c['progress']) && $c['display_status'] !== 'notenrolled' && !$isExpired): ?>
<div class="modern-progress-container mt-3">
<div class="d-flex justify-content-between align-items-center mb-1">
<small><i class="fas fa-tasks"></i> Progress:</small>
<?php if ($c['display_status'] === 'completed' && !empty($c['completed_at'])): ?>
<small class="text-success">
<i class="fas fa-check-circle"></i> 
Completed: <?= date('M d, Y', strtotime($c['completed_at'])) ?>
</small>
<?php endif; ?>
</div>
<?php
$progressPercent = intval($c['progress'] ?? 0);
if ($progressPercent > 100) $progressPercent = 100;
?>
<div class="modern-progress">
<div class="modern-progress-bar 
<?= $c['display_status'] === 'completed' ? 'bg-success' : 'bg-info' ?>"
style="width: <?= $progressPercent ?>%;">
</div>
</div>
<small class="text-end d-block mt-1 fw-bold"><?= $progressPercent ?>% completed</small>
</div>
<?php endif; ?>

                  
<div class="modern-card-actions mt-3">
                            <!-- PREVIEW BUTTON - Always visible -->
<a href="<?= BASE_URL ?>/public/course_preview.php?id=<?= $c['id'] ?>"
class="modern-btn-warning modern-btn-sm"
title="Preview course content">
<i class="fas fa-eye"></i> Preview
</a>
                            
<?php if ($isExpired || $c['display_status'] === 'expired'): ?>
                                <!-- EXPIRED COURSE -->
<span class="tooltip-icon-ex" title="<?= htmlspecialchars($enrollDisabledReason) ?>"
data-bs-toggle="tooltip">
<i class="fas fa-info-circle"></i> Expired
</span>
                                
<?php elseif ($canContinue): ?>
                                <!-- change ko ung continue  -->
<a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
class="modern-btn-primary modern-btn-sm">
<?php if ($c['display_status'] === 'completed'): ?>
<i class="fas fa-redo-alt"></i> Review
<?php else: ?>
<i class="fas fa-play-circle"></i> Continue
<?php endif; ?>
</a>
                                
<?php elseif ($enroll_status === 'notenrolled'): ?>
<!--testing enrolmment button -->
<?php if ($canEnroll || $isAdmin): ?>
                         <!-- testing pero second part -->
 <a href="<?= BASE_URL ?>/public/enroll.php?course_id=<?= $c['id'] ?>"
class="modern-btn-success modern-btn-sm"
onclick="return confirm('Enroll in this course?');">
<i class="fas fa-sign-in-alt"></i> Enroll Now
</a>
<?php else: ?>
                                    <!-- disab btn  -->
<span class=""
title="<?= htmlspecialchars($enrollDisabledReason) ?>"
 data-bs-toggle="tooltip"
data-bs-placement="top">
<i class="fas fa-lock"></i>
</span>
<span class="tooltip-icon" 
title="<?= htmlspecialchars($enrollDisabledReason) ?>"
data-bs-toggle="tooltip">
<i class="fas fa-info-circle"></i>Disabled
</span>
<?php endif; ?>
<?php endif; ?>
                            
                            <!-- ADMIN/PROPONENT EDIT BUTTON -->
<?php if ($isAdmin || $isProponent): ?>
<a href="<?= BASE_URL ?>/public/course_edit.php?id=<?= $c['id'] ?>" 
 class="btn btn-sm btn-outline-secondary"
title="Edit course">
<i class="fas fa-edit"></i>
</a>
<?php endif; ?>
</div>                  
<?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && !$isExpired): ?>
<div class="mt-2 small text-muted">
<i class="fas fa-info-circle"></i> 
<?= htmlspecialchars($enrollDisabledReason) ?>
</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
return new bootstrap.Tooltip(tooltipTriggerEl);
});
});
</script>
</body>
</html>