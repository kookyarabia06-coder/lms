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

// Fetch news/announcements - CORRECTED QUERY
try {
    $newsStmt = $pdo->prepare("
        SELECT n.id, n.title, n.body AS content, n.created_at, u.username AS author 
        FROM news n 
        LEFT JOIN users u ON n.created_by = u.id 
        WHERE n.is_published = 1 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $newsStmt->execute();
    $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $news = [];
    error_log("News fetch error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LMS Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/dashboard.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Welcome Back, <?= htmlspecialchars($_SESSION['user']['fname'] ?? 'User') ?>!</h1>
            <p>Track your learning progress and stay updated with announcements</p>
        </div>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <h3><i class="fas fa-graduation-cap me-2"></i>Your Learning Journey</h3>
            <p>You have <?= $counter['ongoing'] ?> ongoing courses and <?= $counter['completed'] ?> completed courses. Keep up the great work!</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-card-ongoing">
                <div class="stat-number"><?= $counter['ongoing'] ?></div>
                <div class="stat-label">Ongoing Courses</div>
            </div>
            
            <div class="stat-card stat-card-completed">
                <div class="stat-number"><?= $counter['completed'] ?></div>
                <div class="stat-label">Completed Courses</div>
            </div>
            
            <div class="stat-card stat-card-notenrolled">
                <div class="stat-number"><?= $counter['not_enrolled'] ?></div>
                <div class="stat-label">Available Courses</div>
            </div>
        </div>

        <!-- News & Courses Section -->
        <div class="content-section">
            <!-- News Section -->
            <div class="news-section">
                <div class="section-header">
                    <h3><i class="fas fa-newspaper me-2"></i>News & Announcements</h3>
                    <?php if(is_admin()): ?>
                        <a href="<?= BASE_URL ?>/admin/news_crud.php">View All</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($news)): ?>
                    <?php foreach ($news as $item): ?>
                        <div class="news-item">
                            <h5><?= htmlspecialchars($item['title']) ?></h5>
                            <p><?= htmlspecialchars(substr($item['content'], 0, 100)) ?>...</p>
                            <div class="news-meta">
                                <span><i class="fas fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($item['author']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-newspaper"></i>
                        <h4>No announcements yet</h4>
                        <p>Check back later for updates</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Courses Section -->
            <div class="courses-section">
                <div class="section-header">
                    <h3><i class="fas fa-book me-2"></i>Recent Courses</h3>
                </div>
                
                <?php if (!empty($courses)): ?>
                    <div class="course-grid">
                        <?php 
                        $recentCourses = array_slice($courses, 0, 4);
                        foreach ($recentCourses as $c): 
                            $progressPercent = 0;
                            if ($c['enroll_status'] && $c['progress'] && ($c['file_pdf'] || $c['file_video'])) {
                                $totalDuration = 0;
                                if ($c['file_pdf']) $totalDuration += 60;
                                if ($c['file_video']) $totalDuration += 300;
                                if ($totalDuration > 0) {
                                    $progressPercent = min(100, round(($c['progress'] / $totalDuration) * 100));
                                }
                            }
                            $courseUrl = BASE_URL . "/public/course_view.php?id={$c['id']}";
                        ?>
                            <a href="<?= $courseUrl ?>" class="course-card-link">
                                <div class="course-card">
                                    <div class="course-card-img">
                                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" alt="Course Image">
                                    </div>
                                    <div class="course-card-body">
                                        <div class="course-card-title">
                                            <h6><?= htmlspecialchars($c['title']) ?></h6>
                                            <span class="course-badge <?= $c['enroll_status'] ? 'badge-' . $c['enroll_status'] : 'badge-notenrolled' ?>">
                                                <?= $c['enroll_status'] ? ucfirst($c['enroll_status']) : 'Not Enrolled' ?>
                                            </span>
                                        </div>
                                        <p><?= htmlspecialchars(substr($c['description'], 0, 80)) ?>...</p>
                                        
                                        <?php if ($c['enroll_status'] && $c['enroll_status'] === 'ongoing'): ?>
                                            <div class="course-progress">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?= $progressPercent ?>%; background: linear-gradient(90deg, #ffc107, #ffd54f);"></div>
                                                </div>
                                                <div class="progress-percent"><?= $progressPercent ?>% Complete</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <?php if($u && is_admin()): ?>
                            <a href="<?= BASE_URL ?>/admin/courses_crud.php" class="view-all-btn">
                                <i class="fas fa-eye"></i> View All Courses
                            </a>
                        <?php else: ?>
                        <a href="<?= BASE_URL ?>/public/courses.php" class="view-all-btn">
                            <i class="fas fa-eye"></i> View All Courses
                        </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h4>No courses available</h4>
                        <p>Check back later for new courses</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animation for cards on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .course-card, .news-item');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>