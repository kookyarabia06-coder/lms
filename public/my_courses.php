<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

// Fetch only courses the student is enrolled in
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail,
           e.progress, e.total_time_seconds, e.status AS enroll_status
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Courses - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.main { margin-left: 240px; padding: 20px; }
.card-img-top { height: 150px; object-fit: cover; }
</style>
</head>
<body>
<?php include __DIR__ . '/../inc/sidebar.php'; ?>

<div class="main">
    <h3>My Courses</h3>
    <?php if ($myCourses): ?>
    <div class="row row-cols-1 row-cols-md-4 g-4 mt-3">
        <?php foreach ($myCourses as $c): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" class="card-img-top" alt="Course image">
                <div class="card-body d-flex flex-column">
                    <h6><?= htmlspecialchars($c['title']) ?>
                        <?php if ($c['enroll_status'] === 'ongoing'): ?>
                            <span class="badge bg-success">Ongoing</span>
                        <?php elseif ($c['enroll_status'] === 'completed'): ?>
                            <span class="badge bg-secondary">Completed</span>
                        <?php elseif ($c['enroll_status'] === 'expired'): ?>
                            <span class="badge bg-danger">Expired</span>
                        <?php endif; ?>
                    </h6>
                    <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>
                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary mt-auto">Start / Continue</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p>You are not enrolled in any courses yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
