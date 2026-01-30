<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!is_admin()) {
  echo 'Admin only';
  exit;
}
$act = $_GET['act'] ?? '';
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare('INSERT INTO news (title,body,created_by,created_at,is_published) VALUES (?,?,?,?,1)');
  $stmt->execute([$_POST['title'], $_POST['body'], $_SESSION['user']['id'], date('Y-m-d H:i:s')]);
  header('Location: news_crud.php');
  exit;
}
if ($act === 'delete' && isset($_GET['id'])) {
  $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([(int)$_GET['id']]);
  header('Location: news_crud.php');
  exit;
}
$news = $pdo->query('SELECT n.*, u.username FROM news n LEFT JOIN users u ON n.created_by = u.id ORDER BY n.created_at DESC')->fetchAll();
?>
<!doctype html>
<html>

<head>
  <meta charset='utf-8'>
  <title>News</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
<body class="bg-light">
  <div class="container py-4">
    <h4>News</h4>
    <p><a href="?act=addform" class="btn btn-sm btn-success">Add News</a></p>
    <?php if ($act === 'addform'): ?>
      <form method="post" action="?act=add"><input name="title" class="form-control mb-2" placeholder="Title"><textarea name="body" class="form-control mb-2" placeholder="Body"></textarea><button class="btn btn-primary">Create</button></form>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Title</th>
            <th>By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody><?php foreach ($news as $n): ?><tr>
              <td><?= htmlspecialchars($n['title']) ?></td>
              <td><?= htmlspecialchars($n['username']) ?></td>
              <td><a href="?act=delete&id=<?= $n['id'] ?>" class="btn btn-sm btn-danger">Delete</a></td>
            </tr><?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </div>
</body>

</html>