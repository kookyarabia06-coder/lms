<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

// Get full user data from database
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id'] ?? 0]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$createdAt = $userData['created_at'] ?? null;

// Function to get role display name
function get_role_display_name($role) {
    $roles = [
        'admin' => 'Administrator',
        'proponent' => 'Proponent',
        'user' => 'Student',

    ];
    return $roles[$role] ?? ucfirst($role);
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
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="profile-wrapper">
        <div class="profile-header">
            <h1>My Profile</h1>
            <p>View and manage your account information</p>
        </div>

        <div class="profile-card">
            <!-- Avatar -->
            <div class="profile-avatar">
                <?php
                $initials = 'U';
                if(isset($u['fname']) && !empty($u['fname'])) {
                    $initials = '';
                    $nameParts = explode(' ', $u['fname'] . ' ' . ($u['lname'] ?? ''));
                    foreach($nameParts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                        if(strlen($initials) >= 2) break;
                    }
                }
                echo $initials;
                ?>
            </div>

            <!-- Basic Info -->
            <div class="user-info">
                <h2 class="user-name"><?= htmlspecialchars($u['fname'] . ' ' . ($u['lname'] ?? '')) ?></h2>
                <div class="user-role <?= $u['role'] ?? 'guest' ?>">
                    <?= htmlspecialchars(ucfirst($u['role'] ?? '')) ?>
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
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $userStats['total_courses'] ?? 0 ?></div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $userStats['completed'] ?? 0 ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $userStats['ongoing'] ?? 0 ?></div>
                    <div class="stat-label">Ongoing</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="text-center">
                <a href="<?= BASE_URL ?>/public/edit_profile.php" class="modern-btn-warning modern-btn-sm">  
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>
</html>