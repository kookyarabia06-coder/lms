<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$u = current_user();

// Dynamically define BASE_URL if not defined
if(!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/lms');
}

// Function to get role icon - WITH DEFAULT VALUE
function get_role_icon($role = '') {
    $icons = [
        'admin' => 'fa-user-shield',
        'user' => 'fa-user-graduate',
    ];
    return $icons[$role] ?? 'fa-user';
}

// Get pending courses count for admin/superadmin
$pendingCoursesCount = 0;
if (is_admin() || is_superadmin()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE status = 'pending'");
    $stmt->execute();
    $pendingCoursesCount = $stmt->fetchColumn();
}

// Get pending users count for admin/superadmin
$pendingUsersCount = 0;
if (is_admin() || is_superadmin()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $stmt->execute();
    $pendingUsersCount = $stmt->fetchColumn();
}

if (is_admin() || is_superadmin()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM training_requests WHERE status = 'pending'");
    $stmt->execute();
    $reqCount = $stmt->fetchColumn();
}
?>

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sidebar</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</head>
<body>
    <div class="lms-sidebar-container"> 
        <nav class="sidebar lms-sidebar">
            <div class="sidebar-logo">
                <img src="<?= BASE_URL ?>/uploads/images/armmc-logo.png" 
                     alt="armmc logo" 
                     class="logo-img" style="max-width: 120px; margin-bottom: 10px;">
            </div>

            <!-- Space after logo (built into CSS) -->
            
            <ul class="nav flex-column">
                <!-- Profile -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/profile.php">
                        <div class="profile-icon-mini">
                            <i class="fas <?= get_role_icon($u['role'] ?? '') ?>"></i>
                        </div>
                        <div class="profile-details-mini">
                            <h6 title="<?= htmlspecialchars($u['fname'] ?? '') ?>">
                                 <?= htmlspecialchars($u['fname'] ?? '') ?>
                                 <?= htmlspecialchars($u['lname'] ?? '') ?>
                            </h6>
                            <small>
                                <?= htmlspecialchars(ucfirst($u['role'] ?? 'Guest')) ?>
                            </small>
                        </div>
                    </a>
                </li>

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/dashboard.php">
                        <i class="fa fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>

                <!-- DIVIDER 1 -->
                <li class="nav-divider"></li>

                <!-- ALL COURSES SECTION -->
                <li class="nav-item" style="margin-top: 10px;">
                    <div style="color: rgba(255,255,255,0.7); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; padding: 8px 12px;">
                        <i class="fa fa-book me-1" style="font-size: 10px;"></i> COURSES
                    </div>
                </li>

                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/courses_crud.php">
                            <i class="fa fa-sliders"></i> Manage Courses
                        </a>
                    </li>
                    <?php if($u && (is_admin() || is_superadmin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/pending_courses.php">
                            <i class="fa fa-book-open-reader"></i> Pending Courses
                            <?php if ($pendingCoursesCount > 0): ?>
                                <span class="notification-badge"><?= $pendingCoursesCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/public/courses.php">
                            <i class="fa fa-stack-overflow"></i> All Courses
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if($u && is_student()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/my_courses.php">
                        <i class="fa fa-book-bookmark"></i> My Courses
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if($u && (is_proponent() || is_admin())): ?>
                   <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/proponent/all_course.php">
                            <i class="fa-solid fa-folder-tree"></i> All Courses
                        </a>
                    </li>
                <?php endif; ?>

                <!-- MODULE MANAGEMENT SECTION -->
                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/admin/module_crud.php">
                        <i class="fa fa-cubes"></i> Manage Modules
                    </a>
                </li>
                <?php endif; ?>

                <!-- USER MANAGEMENT SECTION -->
                <?php if($u && (is_admin() || is_superadmin())): ?>
                <li class="nav-item" style="margin-top: 15px;">
                    <div style="color: rgba(255,255,255,0.7); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; padding: 8px 12px;">
                        <i class="fa fa-users me-1" style="font-size: 10px;"></i> USER MANAGEMENT
                    </div>
                </li>
                <?php endif; ?>

                <?php if($u && (is_admin() || is_superadmin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/users_crud.php">
                            <i class="fa fa-user"></i> User List
                            <?php if ($pendingUsersCount > 0): ?>
                                <span class="notification-badge"><?= $pendingUsersCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/deptcommittee_crud.php">
                            <i class="fa fa-building-user"></i> Departments & Committees
                        </a>
                    </li>
                <?php endif; ?>

                <!-- DIVIDER 2 -->
                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                    <li class="nav-divider"></li>
                <?php endif; ?>

                <!-- TRAINING REQUEST SECTION -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/training_request.php">
                        <i class="fa fa-clipboard-list"></i> Training Request
                         <span class="notification-badge"><?= $reqCount ?></span>
                    </a>
                </li>

                <!-- News -->
                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/news_crud.php">
                            <i class="fa fa-newspaper"></i> News
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Audit Trail -->
                <?php if($u && (is_superadmin() || is_admin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/audit_crud.php">
                            <i class="fa-solid fa-clock-rotate-left"></i> Audit Trail
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Contact Messages -->
                <?php if($u && (is_admin() || is_superadmin())): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/admin_contacts.php">
                            <i class="fa fa-envelope"></i> Contact Messages
                            <?php
                            // Get unread count
                            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
                            $countStmt->execute();
                            $unread = $countStmt->fetchColumn();
                            if ($unread > 0):
                            ?>
                                <span class="badge bg-danger float-end"><?= $unread ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- DIVIDER 3 -->
                <?php if($u && (is_admin() || is_superadmin())): ?>
                    <li class="nav-divider"></li>
                <?php endif; ?>

                <!-- Logout -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/logout.php">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>