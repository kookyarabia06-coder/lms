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

// Calculate counters
$counter = ['ongoing' => 0, 'completed' => 0, 'not_enrolled' => 0];
foreach ($courses as $c) {
    if (!$c['enroll_status']) $counter['not_enrolled']++;
    elseif ($c['enroll_status'] === 'ongoing') $counter['ongoing']++;
    elseif ($c['enroll_status'] === 'completed') $counter['completed']++;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LMS Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
</head>
<body>
    <!-- Wrap sidebar in a fixed container -->
    <div class="sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="main">
        <h3>COURSES</h3>

        <!-- Counters -->
        <div class="row my-4 g-3">
            <div class="col-md-4">
                <div class="counter-card">
                    <h3><?= $counter['ongoing'] ?></h3>
                    <p>Ongoing Courses</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-card">
                    <h3><?= $counter['completed'] ?></h3>
                    <p>Completed Courses</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-card">
                    <h3><?= $counter['not_enrolled'] ?></h3>
                    <p>Not Enrolled</p>
                </div>
            </div>
        </div>

<!-- Courses -->
<div class="course-card-grid">
    <?php foreach ($courses as $c):
        $totalDuration = 0;
        if ($c['file_pdf']) $totalDuration += 60;
        if ($c['file_video']) $totalDuration += 300;

        $secondsSpent = isset($c['progress']) ? (int)$c['progress'] : 0;

        if ($totalDuration > 0) {
            $progressPercent = min(100, round(($secondsSpent / $totalDuration) * 100));
        } else {
            $progressPercent = 0;
        }
        
        $completedAt = $c['completed_at'] ?? null;
        $startedAt = $c['started_at'] ?? null;
        $totalTime = $c['total_time_seconds'] ?? 0;
        $courseUrl = BASE_URL . "/public/course_view.php?id={$c['id']}";

        $secondsSpent = isset($c['progress']) ? (int)$c['progress'] : 0;
        $days = floor($secondsSpent / 86400);
        $hours = floor(($secondsSpent % 86400) / 3600);
        $minutes = floor(($secondsSpent % 3600) / 60);
        $seconds = $secondsSpent % 60;

        $formattedTime = '';
        if($days > 0) $formattedTime .= $days . 'd ';
        if($hours > 0 || $days > 0) $formattedTime .= $hours . 'h ';
        if($minutes > 0 || $hours > 0 || $days > 0) $formattedTime .= $minutes . 'm ';
        $formattedTime .= $seconds . 's';
        
        // Determine status for styling
        $statusClass = $c['enroll_status'] ? $c['enroll_status'] : 'not-enrolled';
    ?>
        <div class="course-card-col">
            <a href="<?= $courseUrl ?>" class="course-card-wrapper">
                <div class="course-card" data-status="<?= $statusClass ?>">
                    <!-- Fixed image container -->
                    <div class="course-card-img-container">
                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" alt="Course Image">
                    </div>
                    
                    <!-- Card body -->
                    <div class="course-card-body">
                        <div class="course-card-content">
                            <!-- Title and badge -->
                            <div class="course-card-title">
                                <h6><?= htmlspecialchars($c['title']) ?></h6>
                                <div class="course-card-badge">
                                    <?php if ($c['enroll_status'] === 'ongoing'): ?>
                                        <span class="badge bg-warning">Ongoing</span>
                                    <?php elseif ($c['enroll_status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Not Enrolled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Time display -->
                            <span class="course-card-time">Total time: <?= $formattedTime ?></span>

                            <?php if ($c['enroll_status'] && $c['enroll_status'] !== 'expired'): ?>
                                <!-- Progress section -->
                                <div class="course-card-progress">
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $progressPercent ?>%;" 
                                             aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= $progressPercent ?>%
                                        </div>
                                    </div>

                                    <!-- Timestamps -->
                                    <div class="course-card-timestamps">
                                        <?php if ($startedAt): ?>
                                            <small>Started: <?= date('M d, Y H:i', strtotime($startedAt)) ?></small>
                                        <?php endif; ?>
                                        <?php if ($c['enroll_status'] === 'completed' && $completedAt): ?>
                                            <small>Completed: <?= date('M d, Y H:i', strtotime($completedAt)) ?></small>
                                        <?php endif; ?>
                                        <?php if ($totalTime): ?>
                                            <small>Total time: <?= $formattedTime ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>