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
<link href="<?= BASE_URL ?>/assets/css/index.css" rel="stylesheet">
</head>
<body class="home-page" bgcolor="#faf4ed">

  <!-- Hero Dashboard Section -->
  <div class="home-dashboard">
    <h1>Learning Management System</h1>
    <a href="<?= BASE_URL ?>/public/login.php" class="home-btn">Login</a>
    <a href="<?= BASE_URL ?>/public/register.php" class="home-btn1">Register</a>
  </div>

  <!-- Main Content -->
  <div class="home-container">
    <div class="home-row">
      <div class="home-col-left">
        <h5>News & Updates</h5>
        <?php foreach($news as $n): ?>
          <div class="home-card">
            <h6><?= htmlspecialchars($n['title']) ?></h6>
            <p><?= nl2br(htmlspecialchars(substr($n['body'],0,300))) ?></p>
            <small><?= htmlspecialchars($n['created_at']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="home-col-right">
        <h5>Quick Links</h5>
        <ul class="home-links">
          <li><a href="<?= BASE_URL ?>/public/courses.php">Browse Courses</a></li>
        </ul>
      </div>
    </div>
  </div>

</body>
</html>





