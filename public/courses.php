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
// $stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Courses - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.main { margin-left: 240px; padding: 20px; }
.card { display: flex; flex-direction: column; height: 100%; position: relative; }
.card-img-top { height: 150px; object-fit: cover; }
.card-body { flex: 1 1 auto; }
.card-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
}
.card-actions {
    margin-top: 10px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>

<div class="main">
<h3>All Courses</h3>

<?php if($isAdmin || $isProponent): ?>
    <p>
        <a href="<?= BASE_URL ?>/admin/courses_crud.php?act=addform" class="btn btn-success btn-sm">Add New Course</a>
    </p>
<?php endif; ?>

<form method="post" action="">
<div class="row row-cols-1 row-cols-md-4 g-4 mt-3">
<?php foreach ($courses as $c): 
    
$isExpired = false;
if (!empty($c['expires_at'])) {
    $isExpired = strtotime($c['expires_at']) < time();
}
?>
    <div class="col">
        <div class="card shadow-sm h-100">
            <!-- Checkbox -->
            <?php if($isAdmin): ?>
                <div class="card-checkbox">
                    <input type="checkbox" name="selected_courses[]" value="<?= $c['id'] ?>">
                </div>
            <?php endif; ?>

            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" class="card-img-top" alt="Course Image">

            <div class="card-body d-flex flex-column">
                <h6>
                    <?= htmlspecialchars($c['title']) ?>
                    <?php if ($c['enroll_status']): ?>
                        <?php if ($c['enroll_status'] === 'ongoing'): ?>
                            <span class="badge bg-warning">Ongoing</span>
                        <?php elseif ($c['enroll_status'] === 'completed'): ?>
                            <span class="badge bg-success">Completed</span>
                        <?php elseif ($c['enroll_status'] === 'expired'): ?>
                            <span class="badge bg-danger">Expired</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-primary">Not Enrolled</span>
                    <?php endif; ?>
                </h6>
                <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>
                <?php
                    $startDate = date('M, d, Y', strtotime($c['created_at']));
                    $expiryDate = $c['expires_at']
                        ? date('M, d, Y', strtotime($c['expires_at']))
                        : 'No expiry';
                    ?>

                    <p class="mb-1">
                        <small class="text-muted">
                            <strong>Start:</strong> <?= $startDate ?>
                        </small>
                    </p>

                    <p class="mb-2">
                        <small class="text-muted">
                            <strong>Expires:</strong> <?= $expiryDate ?>
                        </small>
                    </p>

                <!-- Actions -->
                <div class="card-actions mt-auto">
                    <?php if($isAdmin): ?>
                        <a href="<?= BASE_URL ?>/admin/courses_crud.php?act=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="<?= BASE_URL ?>/admin/courses_crud.php?act=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this course?')">Delete</a>
                    <?php endif; ?>
                    <!--<a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary"><?= $c['enroll_status'] ? 'Start / Continue' : 'Enroll' ?>
                </a>--> 
                <?php if ($isExpired): ?>
    <!-- Expired course: show prompt -->
    <a href="#"
       class="btn btn-sm btn-secondary"
       onclick="return confirm('This course is already expired. You can no longer enroll or continue.');">
        Enroll
    </a>
<?php else: ?>
    <!-- Active course: go to course view -->
    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
       class="btn btn-sm btn-primary">
        <?= $c['enroll_status'] ? 'Start / Continue' : 'Enroll' ?>
    </a>
<?php endif; ?>

                    
                        </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
</form>

</div>
</body>
</html>
