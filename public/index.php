<?php
require_once __DIR__ . '/../inc/config.php';

// show latest news
$stmt = $pdo->query('SELECT * FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT 5');
$news = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset='utf-8'>
<title>LMS Home</title>
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Learning Management System</h3>
    <div><a href="<?= BASE_URL ?>/public/login.php" class="btn btn-sm btn-primary">Login</a></div>
  </div>
  <div class="row">
    <div class="col-md-8">
      <h5>News & Updates</h5>
      <?php foreach($news as $n): ?>
        <div class="card mb-2">
          <div class="card-body">
            <h6><?= htmlspecialchars($n['title']) ?></h6>
            <p><?= nl2br(htmlspecialchars(substr($n['body'],0,300))) ?></p>
            <small class="text-muted"><?= htmlspecialchars($n['created_at']) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="col-md-4">
      <h5>Quick Links</h5>
      <ul class="list-group">
        <li class="list-group-item"><a href="<?= BASE_URL ?>/public/courses.php">Browse Courses</a></li>
        <li class="list-group-item"><a href="<?= BASE_URL ?>/public/register.php">Register</a></li>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
