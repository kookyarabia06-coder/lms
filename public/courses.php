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
    SELECT c.id, c.title, c.description, c.thumbnail,
           c.created_at, c.expires_at,
           e.status AS enroll_status, e.progress, e.total_time_seconds,
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
    <div class="modern-courses-grid">

<?php foreach ($courses as $c): 
$isExpired = false;
if (!empty($c['expires_at'])) {
    $isExpired = strtotime($c['expires_at']) < time();
}
?>
    <div class="modern-course-card">
        <!-- Checkbox -->
        <?php if($isAdmin): ?>
            <div class="modern-card-checkbox">
                <input type="checkbox" name="selected_courses[]" value="<?= $c['id'] ?>">
            </div>
        <?php endif; ?>

        <div class="modern-card-img">
            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" alt="Course Image">
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

            <!-- Progress Bar -->
            <?php if ($c['enroll_status'] && $c['enroll_status'] !== 'expired'): ?>
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
            <?php endif; ?>

            <!-- Actions -->
            <div class="modern-card-actions">

                <?php if ($isExpired): ?>
                    <a href="#"
                       class="modern-btn-sm modern-btn-secondary"
                       onclick="return confirm('This course is already expired. You can no longer enroll or continue.');">
                        Enroll
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
                       class="modern-btn-sm modern-btn-primary">
                        <?= $c['enroll_status'] ? 'Continue' : 'Enroll Now' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
</div>
</body>
</html>
