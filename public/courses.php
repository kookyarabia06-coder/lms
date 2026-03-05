<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// PLSSSSSS WAG GALAWIN UNG DEPARTMENT HAHAHAHAHAH
$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$isAdmin = is_admin();
$isProponent = is_proponent();

// Define maximum concurrent courses for students
define('MAX_CONCURRENT_COURSES', 5);

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$custom_start = $_GET['start_date'] ?? '';
$custom_end = $_GET['end_date'] ?? '';

// Check how many ongoing courses the user has
$stmt = $pdo->prepare("
    SELECT COUNT(*) as ongoing_count 
    FROM enrollments 
    WHERE user_id = ? AND status = 'ongoing'
");
$stmt->execute([$userId]);
$ongoingCount = $stmt->fetch(PDO::FETCH_ASSOC)['ongoing_count'];
$hasReachedCourseLimit = $ongoingCount >= MAX_CONCURRENT_COURSES;
$availableSlots = MAX_CONCURRENT_COURSES - $ongoingCount;

// First, check if course_departments table exists
try {
    $pdo->query("SELECT 1 FROM course_departments LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, we'll just show no departments
}

// Build the WHERE clause based on filter
$whereConditions = ["c.is_active = 1"];
$params = [$userId];

if ($filter !== 'all') {
    switch ($filter) {
        case 'today':
            $whereConditions[] = "DATE(c.created_at) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "YEARWEEK(c.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $whereConditions[] = "MONTH(c.created_at) = MONTH(CURDATE()) AND YEAR(c.created_at) = YEAR(CURDATE())";
            break;
        case 'year':
            $whereConditions[] = "YEAR(c.created_at) = YEAR(CURDATE())";
            break;
        case 'expiring_soon':
            $whereConditions[] = "c.expires_at IS NOT NULL AND c.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'expired':
            $whereConditions[] = "c.expires_at IS NOT NULL AND c.expires_at < CURDATE()";
            break;
        case 'custom':
            if (!empty($custom_start)) {
                $whereConditions[] = "DATE(c.created_at) >= ?";
                $params[] = $custom_start;
            }
            if (!empty($custom_end)) {
                $whereConditions[] = "DATE(c.created_at) <= ?";
                $params[] = $custom_end;
            }
            break;
    }
}

// Build the complete WHERE clause
$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// enroll info with departments
$query = "
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
        (
            SELECT GROUP_CONCAT(d.name SEPARATOR '||') 
            FROM departments d
            INNER JOIN course_departments cd ON d.id = cd.department_id
            WHERE cd.course_id = c.id
        ) AS department_names,
        (
            SELECT GROUP_CONCAT(d.id SEPARATOR ',') 
            FROM departments d
            INNER JOIN course_departments cd ON d.id = cd.department_id
            WHERE cd.course_id = c.id
        ) AS department_ids,
        -- Determine display status
        CASE 
            WHEN e.id IS NULL THEN 'notenrolled'
            WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
            ELSE e.status
        END AS display_status
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    $whereClause
    ORDER BY 
        -- Show ongoing courses first, then completed, then not enrolled
        CASE 
            WHEN e.status = 'ongoing' THEN 1
            WHEN e.status = 'completed' THEN 2
            ELSE 3
        END,
        c.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process department names into arrays
foreach ($courses as &$course) {
    if (!empty($course['department_names'])) {
        $course['departments'] = explode('||', $course['department_names']);
    } else {
        $course['departments'] = [];
    }
}

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
    
    .course-limit-info {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .course-limit-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .course-limit-progress {
        flex: 1;
        height: 8px;
        background: rgba(255,255,255,0.3);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .course-limit-progress-bar {
        height: 100%;
        background: white;
        border-radius: 10px;
        transition: width 0.3s ease;
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
    
    /* Department badge styles - DONT TOUCH */
    .department-badge {
        display: inline-block;
        background-color: #e9ecef;
        color: #495057;
        padding: 0.25rem 0.5rem;
        margin: 0.125rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        border: 1px solid #dee2e6;
    }
    
    .department-container {
        margin: 10px 0;
        padding: 5px 0;
        border-top: 1px solid #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .department-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    /* Filter styles */
    .filter-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filter-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: #333;
    }
    
    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        background: white;
        color: #495057;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .filter-btn:hover {
        background: #e9ecef;
        border-color: #adb5bd;
    }
    
    .filter-btn.active {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }
    
    .filter-btn.danger.active {
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }
    
    .filter-btn.warning.active {
        background: #ffc107;
        color: #212529;
        border-color: #ffc107;
    }
    
    .custom-date-range {
        display: <?= $filter === 'custom' ? 'flex' : 'none' ?>;
        gap: 15px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
        align-items: flex-end;
    }
    
    .date-input-group {
        flex: 1;
    }
    
    .date-input-group label {
        display: block;
        margin-bottom: 5px;
        font-size: 0.85rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .date-input-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        font-size: 0.9rem;
    }
    
    .apply-filter-btn {
        padding: 8px 20px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        height: 38px;
    }
    
    .apply-filter-btn:hover {
        background: #218838;
    }
    
    .clear-filter {
        color: #dc3545;
        text-decoration: none;
        font-size: 0.9rem;
        margin-left: 10px;
    }
    
    .clear-filter:hover {
        text-decoration: underline;
    }
    
    .results-info {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        font-size: 0.95rem;
        color: #495057;
    }

    .search-container {
        margin-bottom: 30px;
        position: relative;
    }
    .search-box {
        position: relative;
        width: 100%;
        max-width: 500px;
    }
    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 16px;
        z-index: 10;
    }
    .search-box input {
        width: 100%;
        padding: 12px 20px 12px 45px;
        border: 2px solid #e2e8f0;
        border-radius: 50px;
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
        background: white;
    }
    .search-box input:focus {
        border-color: #667eea;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
    }
    .search-box input::placeholder {
        color: #a0aec0;
        font-size: 14px;
    }
    .search-results-count {
        margin-left: 15px;
        color: #6c757d;
        font-size: 14px;
        background: #f8f9fa;
        padding: 5px 12px;
        border-radius: 20px;
    }

</style>
</head>
<body>
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>
<div class="modern-courses-wrapper">
    <h2 class="modern-section-title">All Courses</h2>
    
    <!-- Course Limit Info for Students -->
    <?php if (!$isAdmin && !$isProponent): ?>
    <div class="course-limit-info">
        <i class="fas fa-info-circle fa-2x"></i>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold">Course Enrollment Limit</span>
                <span class="course-limit-badge">
                    <?= $ongoingCount ?> / <?= MAX_CONCURRENT_COURSES ?> Courses
                </span>
            </div>
            <div class="course-limit-progress">
                <div class="course-limit-progress-bar" 
                     style="width: <?= ($ongoingCount / MAX_CONCURRENT_COURSES) * 100 ?>%;"></div>
            </div>
            <small class="d-block mt-1">
                <?php if ($hasReachedCourseLimit): ?>
                    <i class="fas fa-exclamation-triangle"></i> You've reached the maximum of <?= MAX_CONCURRENT_COURSES ?> concurrent courses. Complete an ongoing course to enroll in new ones.
                <?php else: ?>
                    You can enroll in up to <?= MAX_CONCURRENT_COURSES ?> courses at a time. You have <?= $availableSlots ?> slot(s) available.
                <?php endif; ?>
            </small>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filter Section -->
    <div class="filter-container">
        <div class="filter-title">
            <i class="fas fa-filter"></i> Filter by Date
        </div>
        
        <div class="filter-buttons">
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All Courses
            </a>
            <a href="?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> This Week
            </a>
            <a href="?filter=month" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> This Month
            </a>
            <a href="?filter=year" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i> This Year
            </a>
            <a href="?filter=expiring_soon" class="filter-btn warning <?= $filter === 'expiring_soon' ? 'active' : '' ?>">
                <i class="fas fa-hourglass-half"></i> Expiring Soon
            </a>
            <a href="?filter=expired" class="filter-btn danger <?= $filter === 'expired' ? 'active' : '' ?>">
                <i class="fas fa-hourglass-end"></i> Expired
            </a>
            <a href="?filter=custom" class="filter-btn <?= $filter === 'custom' ? 'active' : '' ?>">
                <i class="fas fa-sliders-h"></i> Custom Range
            </a>
        </div>
        
        <!-- Custom Date Range -->
        <form method="GET" class="custom-date-range" id="customDateRange">
            <input type="hidden" name="filter" value="custom">
            <div class="date-input-group">
                <label><i class="fas fa-calendar-plus"></i> Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($custom_start) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="date-input-group">
                <label><i class="fas fa-calendar-check"></i> End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($custom_end) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="apply-filter-btn">
                <i class="fas fa-check"></i> Apply
            </button>
            <?php if ($filter === 'custom'): ?>
                <a href="?filter=all" class="clear-filter">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="results-info">
        <i class="fas fa-info-circle"></i> 
        Showing <strong><?= count($courses) ?></strong> course(s)
        <?php if ($filter !== 'all'): ?>
            <?php
            $filterText = '';
            switch ($filter) {
                case 'today': $filterText = 'created today'; break;
                case 'week': $filterText = 'created this week'; break;
                case 'month': $filterText = 'created this month'; break;
                case 'year': $filterText = 'created this year'; break;
                case 'expiring_soon': $filterText = 'expiring within 7 days'; break;
                case 'expired': $filterText = 'expired'; break;
                case 'custom': 
                    if ($custom_start && $custom_end) {
                        $filterText = 'created from ' . date('M d, Y', strtotime($custom_start)) . ' to ' . date('M d, Y', strtotime($custom_end));
                    } elseif ($custom_start) {
                        $filterText = 'created from ' . date('M d, Y', strtotime($custom_start));
                    } elseif ($custom_end) {
                        $filterText = 'created until ' . date('M d, Y', strtotime($custom_end));
                    }
                    break;
            }
            if ($filterText) {
                echo " <strong>($filterText)</strong>";
            }
            ?>
        <?php endif; ?>
    </div>
    
    <!-- Enrollment Restriction Banner (shown only when limit is reached) -->
    <?php if ($hasReachedCourseLimit && !$isAdmin && !$isProponent): ?>
    <div class="enrollment-restriction">
        <div class="restriction-content">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="restriction-text">
                <strong>Course limit reached:</strong> You have <?= $ongoingCount ?> ongoing courses (maximum <?= MAX_CONCURRENT_COURSES ?>). Complete an ongoing course to enroll in new ones.
            </span>
        </div>
        <a href="my_courses.php" class="btn btn-sm btn-outline-light">
            <i class="fas fa-arrow-right"></i> Go to My Courses
        </a>
    </div>
    <?php endif; ?>
    
    <?php if (empty($courses)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No courses available for the selected filter.
        <?php if ($filter !== 'all'): ?>
            <a href="?filter=all" class="alert-link">View all courses</a>
        <?php endif; ?>
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
            
            // Check if user can enroll in course (NEW LOGIC: allow up to 5 concurrent courses)
            $canEnroll = (
                $enroll_status === 'notenrolled' && 
                !$isExpired && 
                (!$hasReachedCourseLimit || $isAdmin || $isProponent) // Allow enrollment if under limit OR if admin/proponent
            );
            
            // check condition if student or user can continue course
            $canContinue = (
                ($enroll_status === 'ongoing' || $enroll_status === 'completed') && 
                !$isExpired
            );
            
            // enrollment reason why button is off
            $enrollDisabledReason = '';
            if ($enroll_status !== 'notenrolled') {
                $enrollDisabledReason = 'You are already enrolled in this course';
            } elseif ($isExpired) {
                $enrollDisabledReason = 'This course has expired';
            } elseif ($hasReachedCourseLimit && !$isAdmin && !$isProponent) {
                $enrollDisabledReason = 'You have reached the maximum of ' . MAX_CONCURRENT_COURSES . ' concurrent courses. Please complete an ongoing course first.';
            }
        ?>
            
        <div class="modern-course-card <?= (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && !$isProponent) ? 'course-locked' : '' ?>">
            <div class="modern-card-img">
                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" 
                     alt="<?= htmlspecialchars($c['title']) ?>"
                     onerror="this.src='<?= BASE_URL ?>/uploads/images/Course Image.png'">
                
                <!-- lock overlay - only show for regular students who can't enroll -->
                <?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && !$isProponent): ?>
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
                
                <!-- Department Display DONT TOUCH THIS FUCKING THING  -->
                <?php if (!empty($c['departments'])): ?>
                <div class="department-container">
                    <div class="department-label">
                        Departments:
                    </div>
                    <div>
                        <?php foreach ($c['departments'] as $dept): ?>
                            <span class="department-badge">
                                <?= htmlspecialchars($dept) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
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
    // If course is completed, force progress to 100%
    if ($c['display_status'] === 'completed') {
        $progressPercent = 100;
    } else {
        $progressPercent = intval($c['progress'] ?? 0);
    }
    
    // Ensure progress doesn't exceed 100%
    if ($progressPercent > 100) $progressPercent = 100;
    ?>
    <div class="modern-progress">
        <div class="modern-progress-bar 
            <?= $c['display_status'] === 'completed' ? 'bg-success' : 'bg-info' ?>"
            style="width: <?= $progressPercent ?>%;">
        </div>
    </div>
    <small class="text-end d-block mt-1 fw-bold">
        <?php if ($c['display_status'] === 'completed'): ?>
            <span class="text-success">✓ Completed</span>
        <?php else: ?>
            <?= $progressPercent ?>% completed
        <?php endif; ?>
    </small>
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
                        <!-- testing enrolment button -->
                        <?php if ($canEnroll || $isAdmin || $isProponent): ?>
                            <!-- testing pero second part -->
                            <a href="<?= BASE_URL ?>/public/enroll.php?course_id=<?= $c['id'] ?>"
                               class="modern-btn-primary modern-btn-sm"
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
                
                <?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && !$isProponent && !$isExpired): ?>
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
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Show/hide custom date range based on filter selection
    const filterLinks = document.querySelectorAll('.filter-btn');
    const customRange = document.getElementById('customDateRange');
    
    filterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.includes('filter=custom')) {
                // Let the custom range be visible after page reload
                return true;
            }
        });
    });
    
    // Validate date range
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            if (this.value) {
                endDate.min = this.value;
            }
        });
        
        endDate.addEventListener('change', function() {
            if (startDate.value && this.value && this.value < startDate.value) {
                alert('End date cannot be earlier than start date');
                this.value = '';
            }
        });
    }
});
</script>
</body>
</html>