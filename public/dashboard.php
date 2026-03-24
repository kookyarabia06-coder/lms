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

// Fetch modules with progress for current user
$stmt = $pdo->prepare("
    SELECT m.id, m.title, m.description, m.thumbnail, m.committee_id, 
           c.name as committee_name,
           mp.pdf_completed, mp.video_completed,
           mp.pdf_progress, mp.video_progress,
           -- Calculate overall progress for the module
           CASE 
               WHEN m.file_pdf IS NOT NULL AND m.file_video IS NOT NULL THEN 
                   ROUND((COALESCE(mp.pdf_progress, 0) + COALESCE(mp.video_progress, 0)) / 2)
               WHEN m.file_pdf IS NOT NULL THEN 
                   COALESCE(mp.pdf_progress, 0)
               WHEN m.file_video IS NOT NULL THEN 
                   COALESCE(mp.video_progress, 0)
               ELSE 0
           END AS module_progress,
           CASE 
               WHEN (m.file_pdf IS NOT NULL AND m.file_video IS NOT NULL AND mp.pdf_completed = 1 AND mp.video_completed = 1) THEN 'completed'
               WHEN (m.file_pdf IS NOT NULL AND m.file_video IS NOT NULL AND (mp.pdf_completed = 1 OR mp.video_completed = 1)) THEN 'ongoing'
               WHEN (m.file_pdf IS NOT NULL AND mp.pdf_completed = 1) THEN 'completed'
               WHEN (m.file_video IS NOT NULL AND mp.video_completed = 1) THEN 'completed'
               WHEN (m.file_pdf IS NOT NULL AND mp.pdf_progress > 0) THEN 'ongoing'
               WHEN (m.file_video IS NOT NULL AND mp.video_progress > 0) THEN 'ongoing'
               ELSE 'not_started'
           END AS module_status
    FROM modules m
    LEFT JOIN committees c ON m.committee_id = c.id
    LEFT JOIN module_progress mp ON m.id = mp.module_id AND mp.user_id = ?
    ORDER BY m.created_at DESC
    LIMIT 8
");
$stmt->execute([$userId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Fetch mini audit trail for admin users - FIXED QUERY
$audit_entries = [];
if ($isAdmin) {
    try {
        // Check if audit_log table exists
        $pdo->query("SELECT 1 FROM audit_log LIMIT 1");
        
        // Get recent audit entries - show all actions, not just courses
        $auditStmt = $pdo->prepare("
            SELECT 
                a.id,
                a.table_name,
                a.record_id,
                a.action,
                a.user_id,
                a.created_at as audit_time,
                u.username as user_name,
                u.role as user_role,
                -- Try to get a meaningful description based on table_name
                CASE 
                    WHEN a.table_name = 'courses' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.title')), 'Course')
                    WHEN a.table_name = 'users' THEN 
                        CONCAT('User ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.username')), ''))
                    WHEN a.table_name = 'departments' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.name')), 'Department')
                    WHEN a.table_name = 'depts' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.name')), 'Department')
                    WHEN a.table_name = 'committees' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.name')), 'Committee')
                    WHEN a.table_name = 'modules' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.new_data, '$.title')), 'Module')
                    ELSE 
                        CONCAT(a.table_name, ' ID: ', a.record_id)
                END AS record_name
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 8
        ");
        $auditStmt->execute();
        $audit_entries = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Audit table doesn't exist or other error
        error_log("Audit fetch error: " . $e->getMessage());
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
    <link href="<?= BASE_URL ?>/assets/css/dashboard.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional module progress badge styles */
        .badge-ongoing {
            background: #ffc107;
            color: #1a202c;
        }
        .badge-completed {
            background: #28a745;
            color: white;
        }
        .badge-notenrolled {
            background: #3498db;
            color: white;
        }
        .badge-not_started {
            background: #6c757d;
            color: white;
        }
        
        /* Audit trail specific styles */
        .audit-table-mini {
            font-size: 12px;
            width: 100%;
        }
        
        .audit-table-mini th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            color: #495057;
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .audit-table-mini td {
            padding: 10px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .audit-badge-mini {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .audit-badge-insert {
            background: #28a745;
            color: white;
        }
        
        .audit-badge-update {
            background: #ffc107;
            color: #212529;
        }
        
        .audit-badge-delete {
            background: #dc3545;
            color: white;
        }
        
        .audit-content-scroll {
            max-height: 400px;
            overflow-y: auto;
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
            <p><?= $isAdmin ? 'Monitor system activity and announcements' : 'Track your learning progress and stay updated with announcements' ?></p>
        </div>

        <!-- Stats Cards - Visible for BOTH admin and users -->
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

        <!-- Conditional Dashboard Layout based on User Role -->
        
        <?php if ($isAdmin): ?>
            <!-- ADMIN DASHBOARD: Two Column Layout - News & Announcements + Recent Activity -->
            <div class="dashboard-two-column">
                
                <!-- Column 1: News & Announcements (Scrollable) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-newspaper me-2"></i>News & Announcements</h3>
                        <a href="<?= BASE_URL ?>/admin/news_crud.php" class="view-all-link">
                            <i class="fas fa-external-link-alt me-1"></i>Manage
                        </a>
                    </div>
                    
                    <div class="news-column-list scrollable-column" id="newsColumn">
                        <?php if (!empty($news)): ?>
                            <?php foreach ($news as $item): ?>
                                <div class="news-column-item" onclick="toggleNews(this)">
                                    <h5><?= htmlspecialchars($item['title']) ?></h5>
                                    <div class="news-column-content">
                                        <span class="news-content-short"><?= htmlspecialchars(substr($item['content'], 0, 80)) ?>...</span>
                                        <span class="news-content-full" style="display: none;"><?= htmlspecialchars($item['content']) ?></span>
                                    </div>
                                    <div class="news-column-meta">
                                        <span><i class="fas fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                        <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($item['author']) ?></span>
                                    </div>
                                    <span class="news-column-expand"><i class="fas fa-chevron-down"></i></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="column-empty-state">
                                <i class="fas fa-newspaper"></i>
                                <h6>No announcements yet</h6>
                                <p>Create your first announcement</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Column 2: Recent Activity (Audit Trail) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-history me-2 text-primary"></i>Recent Activity</h3>
                        <a href="<?= BASE_URL ?>/admin/audit_courses.php" class="view-all-link">
                            <i class="fas fa-external-link-alt me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (!empty($audit_entries)): ?>
                        <div class="audit-content-scroll" id="auditScroll">
                            <table class="audit-table-mini">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Item</th>
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
                                                <strong><?= htmlspecialchars($entry['record_name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($entry['table_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="audit-badge-mini audit-badge-<?= strtolower($entry['action']) ?>">
                                                    <?php if ($entry['action'] == 'INSERT'): ?>
                                                        <i class="fas fa-plus-circle me-1"></i>Added
                                                    <?php elseif ($entry['action'] == 'UPDATE'): ?>
                                                        <i class="fas fa-edit me-1"></i>Edited
                                                    <?php elseif ($entry['action'] == 'DELETE'): ?>
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
                        <div class="column-empty-state">
                            <i class="fas fa-history"></i>
                            <h6>No recent activity</h6>
                            <p>Activity will appear here when changes are made</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- USER DASHBOARD: Three Column Layout - News + Modules + Recent Courses (ALL SCROLLABLE) -->
            <div class="dashboard-three-column">
                
                <!-- Column 1: News & Announcements (Scrollable) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-newspaper me-2"></i>News & Announcements</h3>
                        <a href="<?= BASE_URL ?>/public/news.php" class="view-all-link">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                    
                    <div class="news-column-list scrollable-column" id="newsColumn">
                        <?php if (!empty($news)): ?>
                            <?php foreach ($news as $item): ?>
                                <div class="news-column-item" onclick="toggleNews(this)">
                                    <h5><?= htmlspecialchars($item['title']) ?></h5>
                                    <div class="news-column-content">
                                        <span class="news-content-short"><?= htmlspecialchars(substr($item['content'], 0, 80)) ?>...</span>
                                        <span class="news-content-full" style="display: none;"><?= htmlspecialchars($item['content']) ?></span>
                                    </div>
                                    <div class="news-column-meta">
                                        <span><i class="fas fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                        <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($item['author']) ?></span>
                                    </div>
                                    <span class="news-column-expand"><i class="fas fa-chevron-down"></i></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="column-empty-state">
                                <i class="fas fa-newspaper"></i>
                                <h6>No announcements yet</h6>
                                <p>Check back later for updates</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Column 2: Modules (Scrollable) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-cube me-2"></i>Modules</h3>
                        <a href="<?= BASE_URL ?>/public/modules.php" class="view-all-link">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (!empty($modules)): ?>
                        <div class="courses-column-list scrollable-column" id="modulesColumn">
                            <?php foreach ($modules as $mod): 
                                $moduleUrl = BASE_URL . "/public/module_view.php?id={$mod['id']}";
                                $moduleStatus = $mod['module_status'] ?? 'not_started';
                                $moduleProgress = $mod['module_progress'] ?? 0;
                            ?>
                                <a href="<?= $moduleUrl ?>" class="course-column-item">
                                    <div class="course-column-img">
                                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($mod['thumbnail'] ?: 'placeholder.png') ?>" 
                                             alt="Module Image"
                                             onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                                    </div>
                                    <div class="course-column-info">
                                        <h5><?= htmlspecialchars($mod['title']) ?></h5>
                                        <p><?= htmlspecialchars(substr($mod['description'] ?? '', 0, 60)) ?>...</p>
                                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                            <?php if ($mod['committee_name']): ?>
                                                <span class="course-column-badge" style="background-color: #8227a9; color: white;">
                                                    <i class="fas fa-users me-1"></i><?= htmlspecialchars($mod['committee_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="course-column-badge <?= $moduleStatus === 'completed' ? 'badge-completed' : ($moduleStatus === 'ongoing' ? 'badge-ongoing' : 'badge-not_started') ?>">
                                                <?php if ($moduleStatus === 'completed'): ?>
                                                    <i class="fas fa-check-circle me-1"></i>Completed
                                                <?php elseif ($moduleStatus === 'ongoing'): ?>
                                                    <i class="fas fa-play-circle me-1"></i><?= $moduleProgress ?>%
                                                <?php else: ?>
                                                    <i class="fas fa-clock me-1"></i>Not Started
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="column-empty-state">
                            <i class="fas fa-cube"></i>
                            <h6>No modules available</h6>
                            <p>Check back later for new modules</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Column 3: Recent Courses (Scrollable) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-clock me-2"></i>Recent Courses</h3>
                        <a href="<?= BASE_URL ?>/public/courses.php" class="view-all-link">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (!empty($courses)): ?>
                        <div class="recent-column-list scrollable-column" id="recentColumn">
                            <?php 
                            $recentCourses = array_slice($courses, 0, 8);
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
                                <a href="<?= $courseUrl ?>" class="recent-column-item">
                                    <div class="recent-column-img">
                                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
                                    </div>
                                    <div class="recent-column-info">
                                        <h6><?= htmlspecialchars($c['title']) ?></h6>
                                        <div class="recent-column-meta">
                                            <span class="recent-column-badge <?= $c['enroll_status'] ? 'badge-' . $c['enroll_status'] : 'badge-notenrolled' ?>">
                                                <?= $c['enroll_status'] ? ucfirst($c['enroll_status']) : 'Not Enrolled' ?>
                                            </span>
                                            <?php if ($c['enroll_status'] && $c['enroll_status'] === 'ongoing'): ?>
                                                <span class="recent-progress">
                                                    <i class="fas fa-chart-line me-1"></i><?= $progressPercent ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="column-empty-state">
                            <i class="fas fa-clock"></i>
                            <h6>No recent courses</h6>
                            <p>Enroll in courses to see them here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to setup auto-hide scrollbar for any element
        function setupScrollbarAutoHide(element, hideDelay = 3000) {
            if (!element) return;
            
            let hideTimeout;
            
            // Function to hide scrollbar
            function hideScrollbar() {
                element.classList.add('scrollbar-hidden');
            }
            
            // Function to show scrollbar
            function showScrollbar() {
                element.classList.remove('scrollbar-hidden');
            }
            
            // Function to reset timer and show scrollbar
            function resetScrollbarTimer() {
                showScrollbar();
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(hideScrollbar, hideDelay);
            }
            
            // Initial hide after delay (so scrollbar starts visible then hides)
            hideTimeout = setTimeout(hideScrollbar, hideDelay);
            
            // Show scrollbar on mouse enter and reset timer
            element.addEventListener('mouseenter', resetScrollbarTimer);
            
            // Reset timer on mouse move (user is actively looking)
            element.addEventListener('mousemove', resetScrollbarTimer);
            
            // Show scrollbar when scrolling and reset timer
            element.addEventListener('scroll', resetScrollbarTimer);
            
            // Reset timer on mouse leave to ensure it hides after delay
            element.addEventListener('mouseleave', function() {
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(hideScrollbar, hideDelay);
            });
        }

        // Animation for cards on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .column-card, .course-column-item, .recent-column-item, .news-column-item');

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

            // Apply auto-hide scrollbar to ALL scrollable columns
            const newsColumn = document.getElementById('newsColumn');
            const modulesColumn = document.getElementById('modulesColumn');
            const recentColumn = document.getElementById('recentColumn');
            const auditScroll = document.getElementById('auditScroll');
            
            // Setup auto-hide for all scrollable columns
            setupScrollbarAutoHide(newsColumn, 3000);
            
            if (modulesColumn) {
                setupScrollbarAutoHide(modulesColumn, 3000);
            }
            
            if (recentColumn) {
                setupScrollbarAutoHide(recentColumn, 3000);
            }
            
            if (auditScroll) {
                setupScrollbarAutoHide(auditScroll, 3000);
            }
        });

        // Toggle news item expand/collapse
        function toggleNews(element) {
            element.classList.toggle('expanded');
            const shortContent = element.querySelector('.news-content-short');
            const fullContent = element.querySelector('.news-content-full');
            const icon = element.querySelector('.news-column-expand i');
            
            if (element.classList.contains('expanded')) {
                shortContent.style.display = 'none';
                fullContent.style.display = 'inline';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                shortContent.style.display = 'inline';
                fullContent.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
            
            // Reset scrollbar timer when toggling news
            const newsColumn = document.getElementById('newsColumn');
            if (newsColumn) {
                const event = new Event('scroll');
                newsColumn.dispatchEvent(event);
            }
        }
    </script>
</body>
</html>