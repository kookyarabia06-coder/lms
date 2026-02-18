<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();
if (!is_admin() && !is_proponent() && !is_superadmin()) {
  echo 'Admin only';
  exit;
}

$act = $_GET['act'] ?? '';

/* =========================
   ADD NEWS
========================= */
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare(
    'INSERT INTO news (title, body, created_by, created_at, is_published)
     VALUES (?, ?, ?, ?, 1)'
  );
  $stmt->execute([
    $_POST['title'],
    $_POST['body'],
    $_SESSION['user']['id'],
    date('Y-m-d H:i:s')
  ]);
  header('Location: news_crud.php');
  exit;
}

/* =========================
   UPDATE NEWS  ✅ FIX
========================= */
if ($act === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare(
    'UPDATE news SET title = ?, body = ? WHERE id = ?'
  );
  $stmt->execute([
    $_POST['title'],
    $_POST['body'],
    (int)$_POST['id']
  ]);
  header('Location: news_crud.php');
  exit;
}

/* =========================
   DELETE NEWS
========================= */
if ($act === 'delete' && isset($_GET['id'])) {
  $pdo->prepare('DELETE FROM news WHERE id = ?')
      ->execute([(int)$_GET['id']]);
  header('Location: news_crud.php');
  exit;
}

/* =========================
   LOAD NEWS LIST
========================= */
$news = $pdo->query(
  'SELECT n.*, u.username
   FROM news n
   LEFT JOIN users u ON n.created_by = u.id
   ORDER BY n.created_at DESC'
)->fetchAll();

/* =========================
   LOAD NEWS FOR EDIT FORM
========================= */
$editNews = null;
if ($act === 'editform' && isset($_GET['id'])) {
  $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
  $stmt->execute([(int)$_GET['id']]);
  $editNews = $stmt->fetch();
  if (!$editNews) {
    die('News not found');
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>News</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="lms-sidebar-container">
  <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="container py-4">
  <h4>News</h4>
  <p>
    <a href="?act=addform" class="btn btn-sm btn-success">Add News</a>
  </p>

<?php if ($act === 'addform'): ?>

  <!-- ADD FORM -->
  <form method="post" action="?act=add">
    <input name="title" class="form-control mb-2" placeholder="Title" required>
    <textarea name="body" class="form-control mb-2" placeholder="Body" required></textarea>
    <button class="btn btn-primary">Create</button>
  </form>

<?php elseif ($act === 'editform'): ?>

  <!-- EDIT FORM -->
  <form method="post" action="?act=edit">
    <input type="hidden" name="id" value="<?= $editNews['id'] ?>">

    <input name="title"
           class="form-control mb-2"
           value="<?= htmlspecialchars($editNews['title']) ?>"
           required>

    <textarea name="body"
              class="form-control mb-2"
              required><?= htmlspecialchars($editNews['body']) ?></textarea>

    <button class="btn btn-primary">Update</button>
  </form>

<?php else: ?>

  <!-- LIST -->
 <div class="card shadow-sm p-3">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Title</th>
        <th style="width:200x;" class="text-center">By</th>
        <th style="width:150px;" class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($news as $n): ?>
      <tr>
        <td><?= htmlspecialchars($n['title']) ?></td>
        <td class="text-center"><?= htmlspecialchars($n['username']) ?></td>
        <td class="text-nowrap text-center">
          <a href="?act=editform&id=<?= $n['id'] ?>"
             class="btn btn-sm btn-warning me-1">
            Edit
          </a>

          <a href="?act=delete&id=<?= $n['id'] ?>"
             class="btn btn-sm btn-danger"
             onclick="return confirm('Delete this news item?')">
            Delete
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

</div>
</body>
</html>
