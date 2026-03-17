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

    /* Stats Cards - Minimized (for both admin and users) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: linear-gradient(145deg, #ffffff, #f8fafc);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
        line-height: 1;
    }

    .stat-label {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }

    /* Card Colors */
    .stat-card-ongoing .stat-number {
        background: linear-gradient(135deg, #ffc107, #ffd54f);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-card-completed .stat-number {
        background: linear-gradient(135deg, #28a745, #34d058);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-card-notenrolled .stat-number {
        background: linear-gradient(135deg, #3498db, #1a75d2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Scrollable Column Styles - For ALL scrollable columns */
    .scrollable-column {
        max-height: 450px;
        overflow-y: auto;
        padding-right: 5px;
        scrollbar-width: thin;
        -ms-overflow-style: auto;
        transition: scrollbar-color 0.3s ease;
    }

    /* Webkit scrollbar styles for scrollable columns */
    .scrollable-column::-webkit-scrollbar {
        width: 8px;
        height: 8px;
        transition: all 0.3s ease;
    }

    .scrollable-column::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .scrollable-column::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .scrollable-column::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Class to hide scrollbar */
    .scrollable-column.scrollbar-hidden {
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important; /* IE/Edge */
    }

    .scrollable-column.scrollbar-hidden::-webkit-scrollbar {
        display: none !important; /* Chrome/Safari/Opera */
    }

    /* Courses and Recent Courses - NOW SCROLLABLE */
    .courses-column-list,
    .recent-column-list {
        max-height: 450px;
        overflow-y: auto;
        padding-right: 5px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* Two Column Layout for Admin */
    .dashboard-two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    /* Three Column Layout for Users */
    .dashboard-three-column {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    /* Column Cards */
    .column-card {
        background: linear-gradient(145deg, #ffffff, #f8fafc);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        height: fit-content;
        display: flex;
        flex-direction: column;
    }

    .column-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .column-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: #1e3c72;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .column-header h3 i {
        color: #3498db;
    }

    /* News Column Items */
    .news-column-item {
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }

    .news-column-item:last-child {
        border-bottom: none;
    }

    .news-column-item:hover {
        background: rgba(52, 152, 219, 0.05);
        border-radius: 8px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .news-column-item h5 {
        font-size: 15px;
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 5px;
        padding-right: 25px;
    }

    .news-column-content {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .news-column-meta {
        display: flex;
        gap: 12px;
        font-size: 11px;
        color: #94a3b8;
    }

    .news-column-expand {
        position: absolute;
        top: 12px;
        right: 5px;
        color: #3498db;
        font-size: 11px;
    }

    /* Courses in Column - NOW SCROLLABLE */
    .courses-column-list {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 5px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .course-column-item {
        display: flex;
        gap: 12px;
        text-decoration: none;
        color: inherit;
        padding: 10px;
        border-radius: 10px;
        transition: all 0.3s ease;
        background: white;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    .course-column-item:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        background: #f8fafc;
    }

    .course-column-img {
        width: 70px;
        height: 70px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .course-column-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .course-column-info {
        flex: 1;
    }

    .course-column-info h5 {
        font-size: 15px;
        font-weight: 600;
        color: #1e3c72;
        margin: 0 0 5px 0;
        line-height: 1.3;
    }

    .course-column-info p {
        font-size: 12px;
        color: #64748b;
        margin: 0 0 5px 0;
        line-height: 1.4;
    }

    .course-column-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
    }

    /* Recent Courses Column - NOW SCROLLABLE */
    .recent-column-list {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 5px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .recent-column-item {
        display: flex;
        gap: 12px;
        text-decoration: none;
        color: inherit;
        padding: 10px;
        border-radius: 10px;
        transition: all 0.3s ease;
        background: white;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    .recent-column-item:hover {
        transform: translateX(3px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .recent-column-img {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .recent-column-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .recent-column-info {
        flex: 1;
    }

    .recent-column-info h6 {
        font-size: 14px;
        font-weight: 600;
        color: #1e3c72;
        margin: 0 0 3px 0;
        line-height: 1.3;
    }

    .recent-column-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
    }

    .recent-column-badge {
        padding: 2px 6px;
        border-radius: 20px;
        font-size: 9px;
        font-weight: 600;
    }

    .badge-ongoing {
        background: linear-gradient(135deg, #ffc107, #ffd54f);
        color: #1a202c;
    }

    .badge-completed {
        background: linear-gradient(135deg, #28a745, #34d058);
        color: white;
    }

    .badge-notenrolled {
        background: linear-gradient(135deg, #3498db, #1a75d2);
        color: white;
    }

    .recent-progress {
        color: #64748b;
    }

    /* Empty State for Columns */
    .column-empty-state {
        text-align: center;
        padding: 30px 15px;
        color: #94a3b8;
    }

    .column-empty-state i {
        font-size: 36px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .column-empty-state h6 {
        color: #64748b;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .column-empty-state p {
        font-size: 12px;
        margin: 0;
    }

    /* Audit trail scrollbar if needed */
    .audit-content-scroll {
        max-height: 300px;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .audit-content-scroll::-webkit-scrollbar {
        width: 8px;
    }

    .audit-content-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .audit-content-scroll::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .dashboard-three-column,
        .dashboard-two-column {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .dashboard-three-column,
        .dashboard-two-column {
            grid-template-columns: 1fr;
        }
        
        .courses-column-list,
        .recent-column-list,
        .news-column-list {
            max-height: 350px;
        }
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
                        <div class="column-empty-state">
                            <i class="fas fa-history"></i>
                            <h6>No recent activity</h6>
                            <p>Activity will appear here when changes are made</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- USER DASHBOARD: Three Column Layout - News + Courses + Recent Courses (ALL SCROLLABLE) -->
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

                <!-- Column 2: Courses (NOW SCROLLABLE) -->
                <div class="column-card">
                    <div class="column-header">
                        <h3><i class="fas fa-graduation-cap me-2"></i>Courses</h3>
                        <a href="<?= BASE_URL ?>/public/courses.php" class="view-all-link">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                    
                    <?php if (!empty($courses)): ?>
                        <div class="courses-column-list scrollable-column" id="coursesColumn">
                            <?php 
                            $mainCourses = array_slice($courses, 0, 10); // Show more courses now that it's scrollable
                            foreach ($mainCourses as $c): 
                                $courseUrl = BASE_URL . "/public/course_view.php?id={$c['id']}";
                            ?>
                                <a href="<?= $courseUrl ?>" class="course-column-item">
                                    <div class="course-column-img">
                                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
                                    </div>
                                    <div class="course-column-info">
                                        <h5><?= htmlspecialchars($c['title']) ?></h5>
                                        <p><?= htmlspecialchars(substr($c['description'], 0, 60)) ?>...</p>
                                        <span class="course-column-badge <?= $c['enroll_status'] ? 'badge-' . $c['enroll_status'] : 'badge-notenrolled' ?>">
                                            <?= $c['enroll_status'] ? ucfirst($c['enroll_status']) : 'Not Enrolled' ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="column-empty-state">
                            <i class="fas fa-book-open"></i>
                            <h6>No courses available</h6>
                            <p>Check back later for new courses</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Column 3: Recent Courses (NOW SCROLLABLE) -->
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
                            $recentCourses = array_slice($courses, 0, 8); // Show more recent courses now that it's scrollable
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
            const coursesColumn = document.getElementById('coursesColumn');
            const recentColumn = document.getElementById('recentColumn');
            const auditScroll = document.getElementById('auditScroll');
            
            // Setup auto-hide for all scrollable columns
            setupScrollbarAutoHide(newsColumn, 3000);
            
            if (coursesColumn) {
                setupScrollbarAutoHide(coursesColumn, 3000);
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