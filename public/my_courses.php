<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

// Fetch only courses the student is enrolled in
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.created_at, c.expires_at,
           e.progress, e.total_time_seconds, 
           CASE 
               WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
               ELSE e.status 
           END AS enroll_status
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Courses - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.main { margin-left: 240px; padding: 20px; }
.card-img-top { height: 150px; object-fit: cover; }
</style>
</head>
<body>
    
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="modern-courses-wrapper">
        <h2 class="modern-section-title">My Courses</h2>
        
        <?php if (!empty($myCourses)): ?>
            <div class="modern-courses-grid">
                <?php foreach ($myCourses as $c): ?>
                <div class="modern-course-card">
                    <div class="modern-card-img">
                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" 
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                    </div>
                    
                    <div class="modern-card-body">
                        <div class="modern-card-title">



                            <h6><?= htmlspecialchars($c['title']) ?></h6>
                            <?php if ($c['enroll_status']): ?>
                                <?php if ($c['enroll_status'] === 'ongoing'): ?>
                                    <span class="modern-badge badge-ongoing">Ongoing</span>
                                <?php elseif ($c['enroll_status'] === 'completed'): ?>
                                    <span class="modern-badge badge-completed">Completed</span>
                                <?php elseif ($c['enroll_status'] === 'expired'): ?>
                                    <span class="modern-badge badge-expired">Expired</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="modern-badge badge-notenrolled">Not Enrolled</span>
                            <?php endif; ?>
                        </div>
                        
                        <p><?= htmlspecialchars(substr($c['description'], 0, 120)) ?>...</p>
                        
                        <div class="modern-course-info">
                            <?php
                                $startDate = date('M, d, Y', strtotime($c['created_at']));
                                $expiryDate = $c['expires_at']
                                    ? date('M, d, Y', strtotime($c['expires_at']))
                                    : 'No expiry';
                            ?>
                            <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                            <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
                        </div>

                        <!-- Progress Bar remove -->
                        <!-- <?php if ($c['enroll_status'] && $c['enroll_status'] !== 'expired'): ?>
                            <div class="modern-progress-container">
                                <small>Progress:</small>
                                <?php
                                    $progressPercent = intval($c['progress']);
                                    if ($progressPercent > 100) $progressPercent = 100;
                                ?>
                                <div class="modern-progress">
                                    <div class="modern-progress-bar 
                                        <?= $c['enroll_status'] === 'completed' ? 'bg-success' : 'bg-info' ?>"
                                        style="width: <?= $progressPercent ?>%;">
                                    </div>
                                </div>
                                <small class="text-end d-block mt-1"><?= $progressPercent ?>% completed</small>
                            </div>
                        <?php endif; ?> -->
                        
                        <div class="modern-card-actions">

<a href="<?= BASE_URL ?>/public/course_preview.php?id=<?= $c['id'] ?>"
class="modern-btn-warning modern-btn-sm"
title="Preview course content">
<i class="fas fa-eye"></i> Preview
</a>            
<?php if ($c['enroll_status'] === 'expired'): ?>
<a href="#"
class="modern-btn-sm modern-btn-secondary" style="cursor: not-allowed;"
onclick="return confirm('This course is already expired. You can no longer enroll or continue.');">Expired</a>
<?php else: ?>
<a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="modern-btn-primary modern-btn-sm">Start / Continue</a>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>You are not enrolled in any courses yet.</p>
        <?php endif; ?>
    </div>
</body>

<script>
//expried    
</script>
</html>