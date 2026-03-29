<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$userId = $_SESSION['user']['id'];
$u = current_user();

// Get fresh user data from database to ensure we have latest
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id'] ?? 0]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Update session with fresh data (optional but good for consistency)
if ($userData) {
    $_SESSION['user'] = $userData;
    $u = $userData; // Update local variable
}

$createdAt = $userData['created_at'] ?? null;

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress, e.badge_issued, e.completed_at
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");

$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate counters
$counter = ['ongoing' => 0, 'completed' => 0];
$badgedCourses = [];

foreach ($courses as $c) {
    if ($c['enroll_status'] === 'ongoing') $counter['ongoing']++;
    elseif ($c['enroll_status'] === 'completed') {
        $counter['completed']++;
        // Collect badged courses (completed and badge_issued = 1)
        if ($c['badge_issued'] == 1) {
            $badgedCourses[] = $c;
        }
    }
}

// Function to get role display name
function get_role_display_name($role) {
    $roles = [
        'superadmin' => 'SuperAdmin',
        'admin' => 'Administrator',
        'proponent' => 'Proponent',
        'user' => 'Student',
    ];
    return $roles[$role] ?? ucfirst($role);
}

// Get user's assignments based on role
$divisions = [];
$departments = [];
$committees = [];

if ($u['role'] === 'user') {
    // Student: Get divisions and departments
    $stmt = $pdo->prepare("
        SELECT dept.id as division_id, dept.name as division_name,
               d.id as department_id, d.name as department_name
        FROM user_departments ud
        JOIN depts d ON ud.dept_id = d.id
        JOIN departments dept ON d.department_id = dept.id
        WHERE ud.user_id = ?
    ");
    $stmt->execute([$u['id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        if (!in_array(['id' => $row['division_id'], 'name' => $row['division_name']], $divisions)) {
            $divisions[] = ['id' => $row['division_id'], 'name' => $row['division_name']];
        }
        $departments[] = ['id' => $row['department_id'], 'name' => $row['department_name']];
    }
} else {
    // Proponent/Admin: Get committees
    $stmt = $pdo->prepare("
        SELECT c.id, c.name
        FROM user_departments ud
        JOIN committees c ON ud.committee_id = c.id
        WHERE ud.user_id = ? AND ud.committee_id IS NOT NULL
    ");
    $stmt->execute([$u['id']]);
    $committees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?= BASE_URL ?>/assets/css/profile.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <style>
        .department-badge {
            display: inline-block;
            background-color: #6610f2;
            color: white;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .committee-badge {
            display: inline-block;
            background-color: #1a5644;
            color: white;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .division-badge {
            display: inline-block;
            background-color: #9c3098;
            color: white;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .assignments-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .assignments-title {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 600;
        }

        /* Badges Earned Section */
        .badges-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        .badges-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badges-title i {
            color: #fd7e14;
            font-size: 1.3rem;
        }
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .badge-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .badge-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(253, 126, 20, 0.15);
            border-color: #fd7e14;
        }
        .badge-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .badge-icon i {
            font-size: 28px;
            color: white;
        }
        .badge-info {
            flex: 1;
        }
        .badge-course-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
            color: #1e293b;
        }
        .badge-date {
            font-size: 0.7rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .badge-status {
            display: inline-block;
            background: #fd7e14;
            color: white;
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 500;
        }
        .empty-badges {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #6c757d;
        }
        .empty-badges i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        .empty-badges p {
            margin: 0;
            font-size: 0.9rem;
        }
        .empty-badges small {
            font-size: 0.8rem;
            color: #adb5bd;
        }

        /* View Course Button */
        .view-course-link {
            font-size: 0.7rem;
            color: #fd7e14;
            text-decoration: none;
            margin-top: 5px;
            display: inline-block;
        }
        .view-course-link:hover {
            text-decoration: underline;
        }

        /* Profile Layout Enhancement */
        .profile-wrapper {
            margin-left: 280px;
            padding: 30px;
            max-width: calc(100% - 280px);
        }
        .profile-card {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Stats Grid - 3 columns */
        .stats-grid {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 25px 0;
        }
        .stat-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 8px;
        }

        @media (max-width: 992px) {
            .profile-wrapper {
                margin-left: 0;
                padding: 20px;
                max-width: 100%;
            }
            .badges-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>              
</div>

<div class="profile-wrapper">
    <!-- Success Message -->
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="profile-card">
        <!-- Avatar -->
        <div class="profile-avatar <?= strtolower(trim($u['role'] ?? 'guest')) ?>">
            <?php
            $initials = 'U';
            if(isset($u['fname']) && !empty($u['fname'])) {
                $initials = '';
                $nameParts = explode(' ', $u['fname'] . ' ' . ($u['lname'] ?? ''));
                foreach($nameParts as $part) {
                    if(!empty(trim($part))) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    if(strlen($initials) >= 2) break;
                }
            }
            echo $initials ?: 'U';
            ?>
        </div>

        <!-- Basic Info -->
        <div class="user-info">
            <h2 class="user-name"><?= htmlspecialchars($u['fname'] . ' ' . ($u['lname'] ?? '')) ?></h2>
            <div class="user-role <?= $u['role'] ?? 'guest' ?>">
                <?= htmlspecialchars(get_role_display_name($u['role'] ?? '')) ?>
            </div>
            <p class="user-email">
                <i class="fas fa-envelope me-2"></i>
                <?= htmlspecialchars($u['email'] ?? 'No email provided') ?>
            </p>
            <p class="member-since">
                <i class="fas fa-calendar-alt me-2"></i>
                Member since: 
                <?php 
                if($createdAt && !empty($createdAt)) {
                    echo date('F j, Y', strtotime($createdAt));
                } else {
                    echo 'Unknown';
                }
                ?>
            </p>

            <!-- Assignments Section -->
            <div class="assignments-section">
                <?php if ($u['role'] === 'user'): ?>
                    <!-- Student: Show Divisions and Departments -->
                    <?php if (!empty($divisions)): ?>
                        <div class="assignments-title">
                            <i class="fas fa-building me-2"></i>Division:
                        </div>
                        <div class="mb-3">
                            <?php foreach ($divisions as $division): ?>
                                <span class="division-badge">
                                    <i class="fas fa-building me-1"></i>
                                    <?= htmlspecialchars($division['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($departments)): ?>
                        <div class="assignments-title">
                            <i class="fas fa-sitemap me-2"></i>Department:
                        </div>
                        <div>
                            <?php foreach ($departments as $department): ?>
                                <span class="department-badge">
                                    <i class="fas fa-sitemap me-1"></i>
                                    <?= htmlspecialchars($department['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Proponent/Admin: Show Committees -->
                    <?php if (!empty($committees)): ?>
                        <div class="assignments-title">
                            <i class="fas fa-users me-2"></i>Committees:
                        </div>
                        <div>
                            <?php foreach ($committees as $committee): ?>
                                <span class="committee-badge">
                                    <i class="fas fa-users me-1"></i>
                                    <?= htmlspecialchars($committee['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($divisions) && empty($departments) && empty($committees)): ?>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        No assignments yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Grid - 3 columns -->
        <?php if ($u['role'] === 'user'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $counter['ongoing'] ?></div>
                <div class="stat-label">Ongoing Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counter['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($badgedCourses) ?></div>
                <div class="stat-label">Badges Earned</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="text-center mb-4">
            <a href="<?= BASE_URL ?>/public/edit_profile.php" class="modern-btn-warning modern-btn-sm">  
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>

        <!-- Badges Earned Section -->
        <?php if ($u['role'] === 'user'): ?>
        <div class="badges-section">
            <div class="badges-title">
                <i class="fas fa-medal"></i>
                <span>Badges Earned</span>
            </div>

            <?php if (!empty($badgedCourses)): ?>
                <div class="badges-grid">
                    <?php foreach ($badgedCourses as $badge): ?>
                        <div class="badge-card">
                            <div class="badge-icon">
                                <i class="fas fa-medal"></i>
                            </div>
                            <div class="badge-info">
                                <div class="badge-course-title"><?= htmlspecialchars($badge['title']) ?></div>
                                <div class="badge-date">
                                    <i class="fas fa-calendar-check me-1"></i>
                                    Earned: <?= $badge['completed_at'] ? date('M d, Y', strtotime($badge['completed_at'])) : 'Recently' ?>
                                </div>
                                <span class="badge-status">
                                    <i class="fas fa-check-circle me-1"></i>Completed
                                </span>
                                <div>
                                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $badge['id'] ?>" class="view-course-link">
                                        <i class="fas fa-eye me-1"></i>View Course
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-badges">
                    <i class="fas fa-medal"></i>
                    <p>No badges earned yet</p>
                    <small>Complete courses and pass assessments to earn badges</small>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-dismiss success alert after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    }
});
</script>

</body>
</html>