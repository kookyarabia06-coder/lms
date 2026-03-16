<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role']; // Get user role

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

// Fetch news/announcements
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

// Check if user is admin or superadmin
$isAdmin = is_admin() || is_superadmin();

// Fetch mini audit trail for admin users
$audit_entries = [];
if ($isAdmin) {
    try {
        // Check if audit_log table exists
        $pdo->query("SELECT 1 FROM audit_log LIMIT 1");
        
        // Get recent audit entries for courses
        $auditStmt = $pdo->prepare("
            SELECT 
                a.created_at as audit_time,
                a.action as audit_action,
                a.record_id,
                a.user_id,
                COALESCE(c.title, 'Deleted Course') as course_title,
                u.username as user_name,
                u.role as user_role
            FROM audit_log a
            LEFT JOIN courses c ON a.record_id = c.id AND a.table_name = 'courses'
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.table_name = 'courses'
            AND a.action IN ('INSERT', 'UPDATE', 'DELETE')
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $auditStmt->execute();
        $audit_entries = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Audit table doesn't exist or other error
        $audit_entries = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LMS Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
   <style>
    /* Mini audit trail styles */
    .audit-table-mini {
        font-size: 13px;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 6px;
    }

    .audit-table-mini th {
        background: #f8f9fa;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #495057;
        padding: 12px 15px;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }

    .audit-table-mini td {
        padding: 14px 15px;
        vertical-align: middle;
        background-color: #ffffff;
        border-bottom: 1px solid #e9ecef;
    }

    .audit-table-mini tbody tr {
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    .audit-table-mini tbody tr:hover {
        background-color: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .audit-table-mini tbody tr td:first-child {
        border-radius: 8px 0 0 8px;
    }

    .audit-table-mini tbody tr td:last-child {
        border-radius: 0 8px 8px 0;
    }

    .audit-badge-mini {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.1px;
    }

    .audit-badge-insert {
        background: #28a745;
        color: white;
        border: none;
    }

    .audit-badge-update {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .audit-badge-delete {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .view-all-link {
        font-size: 12px;
        padding: 6px 12px;
        background: #f8f9fa;
        border-radius: 6px;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s;
        border: 1px solid #dee2e6;
    }

    .view-all-link:hover {
        background: #e9ecef;
        color: #212529;
        border-color: #ced4da;
    }

    .view-all-btn {
        font-size: 13px;
        padding: 8px 20px;
        background: #f8f9fa;
        border-radius: 6px;
        color: #495057;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
        border: 1px solid #dee2e6;
    }

    .view-all-btn:hover {
        background: #e9ecef;
        color: #212529;
        border-color: #ced4da;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* News section scrollable area */
    .news-section {
        max-height: 500px;
        overflow-y: auto;
        padding-right: 10px;
        scrollbar-width: thin;
        -ms-overflow-style: auto;
        transition: scrollbar-color 0.3s ease;
    }

    /* Webkit scrollbar styles for news section */
    .news-section::-webkit-scrollbar {
        width: 8px;
        height: 8px;
        transition: all 0.3s ease;
    }

    .news-section::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .news-section::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .news-section::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Class to hide scrollbar in news section */
    .news-section.scrollbar-hidden {
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
    }

    .news-section.scrollbar-hidden::-webkit-scrollbar {
        display: none; /* Chrome/Safari/Opera */
    }

    
</style>
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

        <!-- News & Content Section -->
        <div class="content-section">
            <!-- News Section -->
            <div class="news-section" id="newsSection">
                <div class="section-header">
                    <h3><i class="fas fa-newspaper me-2"></i>News & Announcements</h3>
                    <?php if(is_admin() || is_superadmin()): ?>
                        <a href="<?= BASE_URL ?>/admin/news_crud.php">View All</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($news)): ?>
                    <?php foreach ($news as $item): ?>
                        <div class="news-item" onclick="toggleNews(this)">
                            <h5><?= htmlspecialchars($item['title']) ?></h5>
                            <div class="news-content-wrapper">
                                <p class="news-content-short"><?= htmlspecialchars(substr($item['content'], 0, 100)) ?>...</p>
                                <p class="news-content-full"><?= htmlspecialchars($item['content']) ?></p>
                            </div>
                            <div class="news-meta">
                                <span><i class="fas fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($item['author']) ?></span>
                            </div>
                            <span class="expand-indicator"><i class="fas fa-chevron-down"></i></span>
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

            <!-- Content Section - Shows either Recent Courses or Mini Audit Trail based on role -->
            <div class="courses-section">
                <div class="section-header">
                    <?php if ($isAdmin): ?>
                        <h3><i class="fas fa-history me-2 text-primary"></i>Recent Activity</h3>
                        <a href="<?= BASE_URL ?>/admin/audit_courses.php" class="view-all-link">
                            <i class="fas fa-external-link-alt me-1"></i>Full Audit Trail
                        </a>
                    <?php else: ?>
                        <h3><i class="fas fa-book me-2"></i>Recent Courses</h3>
                    <?php endif; ?>
                </div>
                
                <?php if ($isAdmin): ?>
                    <!-- Mini Audit Trail for Admin/Superadmin -->
                    <?php if (!empty($audit_entries)): ?>
                        <div class="audit-content-scroll" id="auditScroll">
                            <table class="audit-table-mini">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Course</th>
                                        <th>Action</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_entries as $entry): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                    $timestamp = strtotime($entry['audit_time']);
                                                    echo date('M d, h:i A', $timestamp);
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($entry['course_title']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="audit-badge-mini audit-badge-<?= strtolower($entry['audit_action']) ?>">
                                                    <?php if ($entry['audit_action'] == 'INSERT'): ?>
                                                        <i class="fas fa-plus-circle me-1"></i>Added
                                                    <?php elseif ($entry['audit_action'] == 'UPDATE'): ?>
                                                        <i class="fas fa-edit me-1"></i>Edited
                                                    <?php elseif ($entry['audit_action'] == 'DELETE'): ?>
                                                        <i class="fas fa-trash-alt me-1"></i>Deleted
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-user-circle me-1 text-muted"></i>
                                                <?= htmlspecialchars($entry['user_name'] ?? 'System') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No recent activity</h4>
                            <p>Audit trail will appear here when changes are made</p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Recent Courses for Regular Users -->
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
                                            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image.png">
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
                            <a href="<?= BASE_URL ?>/public/courses.php" class="view-all-btn">
                                <i class="fas fa-eye"></i> View All Courses
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h4>No courses available</h4>
                            <p>Check back later for new courses</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animation for cards on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .course-card, .news-item, .audit-table-mini');

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

            // Auto-hide scrollbar for news section after 3 seconds
            const newsSection = document.getElementById('newsSection');
            
            if (newsSection) {
                let newsHideTimeout;
                
                // Function to hide scrollbar
                function hideNewsScrollbar() {
                    newsSection.classList.add('scrollbar-hidden');
                }
                
                // Function to show scrollbar
                function showNewsScrollbar() {
                    newsSection.classList.remove('scrollbar-hidden');
                }
                
                // Function to reset timer and show scrollbar
                function resetNewsScrollbar() {
                    showNewsScrollbar();
                    clearTimeout(newsHideTimeout);
                    newsHideTimeout = setTimeout(hideNewsScrollbar, 3000);
                }
                
                // Initial hide after 3 seconds
                newsHideTimeout = setTimeout(hideNewsScrollbar, 3000);
                
                // Show scrollbar on mouse enter and reset timer
                newsSection.addEventListener('mouseenter', resetNewsScrollbar);
                
                // Reset timer on mouse move (user is actively looking)
                newsSection.addEventListener('mousemove', resetNewsScrollbar);
                
                // Show scrollbar when scrolling and reset timer
                newsSection.addEventListener('scroll', resetNewsScrollbar);
            }

            // Auto-hide scrollbar for audit trail after 3 seconds
            const auditScroll = document.getElementById('auditScroll');
            
            if (auditScroll) {
                let auditHideTimeout;
                
                // Function to hide scrollbar
                function hideAuditScrollbar() {
                    auditScroll.classList.add('scrollbar-hidden');
                }
                
                // Function to show scrollbar
                function showAuditScrollbar() {
                    auditScroll.classList.remove('scrollbar-hidden');
                }
                
                // Function to reset timer and show scrollbar
                function resetAuditScrollbar() {
                    showAuditScrollbar();
                    clearTimeout(auditHideTimeout);
                    auditHideTimeout = setTimeout(hideAuditScrollbar, 3000);
                }
                
                // Initial hide after 3 seconds
                auditHideTimeout = setTimeout(hideAuditScrollbar, 3000);
                
                // Show scrollbar on mouse enter and reset timer
                auditScroll.addEventListener('mouseenter', resetAuditScrollbar);
                
                // Reset timer on mouse move
                auditScroll.addEventListener('mousemove', resetAuditScrollbar);
                
                // Show scrollbar when scrolling and reset timer
                auditScroll.addEventListener('scroll', resetAuditScrollbar);
            }
        });

        // Toggle news item expand/collapse
        function toggleNews(element) {
            element.classList.toggle('expanded');
            const shortContent = element.querySelector('.news-content-short');
            const fullContent = element.querySelector('.news-content-full');
            const icon = element.querySelector('.expand-indicator i');
            
            if (element.classList.contains('expanded')) {
                shortContent.style.display = 'none';
                fullContent.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                shortContent.style.display = 'block';
                fullContent.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
            
            // Reset scrollbar timer when toggling news
            const newsSection = document.getElementById('newsSection');
            if (newsSection) {
                // Trigger the reset function
                const event = new Event('scroll');
                newsSection.dispatchEvent(event);
            }
        }
    </script>
</body>
</html>