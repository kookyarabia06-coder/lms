<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$isAdmin = is_admin();
$isProponent = is_proponent();

// Fetch all courses with enrollment info
$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description, 
        c.thumbnail,
        c.created_at, 
        c.expires_at AS course_expires_at,
        e.status AS enroll_status,
        e.progress, 
        e.total_time_seconds,
        e.enrolled_at,
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
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if we're getting data
// echo "<pre>Number of courses: " . count($courses) . "</pre>";
// if (!empty($courses)) {
//     echo "<pre>First course: ";
//     print_r($courses[0]);
//     echo "</pre>";
// }
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    <div class="modern-courses-wrapper">
        <h2 class="modern-section-title">All Courses</h2>
        
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
                
                // Get enroll_status from display_status if not directly available
                $enroll_status = $c['enroll_status'] ?? $c['display_status'];
                ?>
                
                <div class="modern-course-card">
                    <div class="modern-card-img">
                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" 
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                    </div>

                    <div class="modern-card-body">
                        <div class="modern-card-title">
                            <h6><?= htmlspecialchars($c['title']) ?></h6>
                            <?php if ($c['display_status'] === 'ongoing'): ?>
                                <span class="modern-badge badge-ongoing">Ongoing</span>
                            <?php elseif ($c['display_status'] === 'completed'): ?>
                                <span class="modern-badge badge-completed">Completed</span>
                            <?php elseif ($c['display_status'] === 'expired' || $isExpired): ?>
                                <span class="modern-badge badge-expired">Expired</span>
                            <?php elseif ($c['display_status'] === 'notenrolled'): ?>
                                <span class="modern-badge badge-notenrolled">Not Enrolled</span>
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
                            <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                            <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
                        </div>

                        <!-- Progress Bar - Only show for enrolled users -->
                        <?php if (isset($c['progress']) && $c['display_status'] !== 'notenrolled' && !$isExpired): ?>
                            <div class="modern-progress-container mt-3">
                                <small>Progress:</small>
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
                                <small class="text-end d-block mt-1"><?= $progressPercent ?>% completed</small>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="modern-card-actions mt-3">
                            <?php if ($isExpired || $c['display_status'] === 'expired'): ?>
                                <a href="#"
                                class="modern-btn-secondary modern-btn-sm"
                                onclick="return confirm('This course is expired. You can no longer enroll or continue.');">
                                    <i class="fas fa-ban"></i> Expired
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
                                class="modern-btn-primary modern-btn-sm">
                                    <?php if ($c['display_status'] === 'notenrolled'): ?>
                                        <i class="fas fa-sign-in-alt"></i> Enroll Now
                                    <?php else: ?>
                                        <i class="fas fa-play-circle"></i> Continue
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($isAdmin || $isProponent): ?>
                                <a href="<?= BASE_URL ?>/public/course_edit.php?id=<?= $c['id'] ?>" 
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Debug output (remove in production) -->
    <!-- <div style="position: fixed; bottom: 10px; right: 10px; background: white; padding: 10px; border: 1px solid #ccc; z-index: 9999;">
        <small>Courses found: <?= count($courses) ?></small>
    </div> -->
</body>
</html>

