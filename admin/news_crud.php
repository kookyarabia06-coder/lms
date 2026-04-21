<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();
if (!is_admin() && !is_proponent() && !is_superadmin()) {
  echo 'Admin only';
  exit;
}

$act = $_GET['act'] ?? '';
$current_user_id = $_SESSION['user']['id'];
$is_proponent_only = is_proponent() && !is_admin() && !is_superadmin();

// Initialize session flash messages
if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = [];
}

function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* =========================
   ADD NEWS
========================= */
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // Validate input
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    
    if (empty($title) || empty($body)) {
        set_flash('danger', 'Title and body are required.');
        header('Location: news_crud.php?act=addform');
        exit;
    }
    
    $stmt = $pdo->prepare(
        'INSERT INTO news (title, body, created_by, created_at, updated_at, is_published)
         VALUES (?, ?, ?, NOW(), NOW(), 1)'
    );
    $stmt->execute([
        $title,
        $body,
        $current_user_id
    ]);
    
    set_flash('success', 'News created successfully!');
    header('Location: news_crud.php');
    exit;
}

/* =========================
   UPDATE NEWS
========================= */
if ($act === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    
    if (empty($title) || empty($body)) {
        set_flash('danger', 'Title and body are required.');
        header("Location: news_crud.php?act=editform&id=$id");
        exit;
    }
    
    // Check ownership for proponents
    if ($is_proponent_only) {
        $stmt = $pdo->prepare('SELECT created_by FROM news WHERE id = ?');
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news || $news['created_by'] != $current_user_id) {
            set_flash('danger', 'You do not have permission to edit this news item.');
            header('Location: news_crud.php');
            exit;
        }
    }
    
    $stmt = $pdo->prepare(
        'UPDATE news SET title = ?, body = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$title, $body, $id]);
    
    set_flash('success', 'News updated successfully!');
    header('Location: news_crud.php');
    exit;
}

/* =========================
   DELETE NEWS
========================= */
if ($act === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Check ownership for proponents
    if ($is_proponent_only) {
        $stmt = $pdo->prepare('SELECT created_by FROM news WHERE id = ?');
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news || $news['created_by'] != $current_user_id) {
            set_flash('danger', 'You do not have permission to delete this news item.');
            header('Location: news_crud.php');
            exit;
        }
    }
    
    $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
    
    set_flash('success', 'News deleted successfully!');
    header('Location: news_crud.php');
    exit;
}

/* =========================
   TOGGLE PUBLISH STATUS (AJAX)
   - Admin/Superadmin: Can toggle any news
   - Proponent: Can only toggle their own news
========================= */
if ($act === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Check permission
    if ($is_proponent_only) {
        // Proponent - check ownership
        $stmt = $pdo->prepare('SELECT created_by FROM news WHERE id = ?');
        $stmt->execute([$id]);
        $news = $stmt->fetch();
        
        if (!$news || $news['created_by'] != $current_user_id) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'You do not have permission to toggle this news item.']);
                exit;
            }
            set_flash('danger', 'You do not have permission to toggle this news item.');
            header('Location: news_crud.php');
            exit;
        }
    }
    
    // Toggle the status
    $stmt = $pdo->prepare('UPDATE news SET is_published = NOT is_published, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    
    // Get new status
    $stmt = $pdo->prepare('SELECT is_published, title FROM news WHERE id = ?');
    $stmt->execute([$id]);
    $news = $stmt->fetch();
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_published' => (bool)$news['is_published'],
            'message' => "'" . htmlspecialchars($news['title']) . "' is now " . 
                        ($news['is_published'] ? 'published' : 'unpublished')
        ]);
        exit;
    }
    
    $status = $news['is_published'] ? 'published' : 'unpublished';
    set_flash('success', "'" . htmlspecialchars($news['title']) . "' is now $status.");
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
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
    $stmt->execute([$id]);
    $editNews = $stmt->fetch();
    
    if (!$editNews) {
        set_flash('danger', 'News not found.');
        header('Location: news_crud.php');
        exit;
    }
    
    // Check ownership for proponents
    if ($is_proponent_only && $editNews['created_by'] != $current_user_id) {
        set_flash('danger', 'You do not have permission to edit this news item.');
        header('Location: news_crud.php');
        exit;
    }
}

/* =========================
   VIEW SINGLE NEWS
========================= */
$viewNews = null;
if ($act === 'view' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare(
        'SELECT n.*, u.username
         FROM news n
         LEFT JOIN users u ON n.created_by = u.id
         WHERE n.id = ?'
    );
    $stmt->execute([$id]);
    $viewNews = $stmt->fetch();
    
    if (!$viewNews) {
        set_flash('danger', 'News not found.');
        header('Location: news_crud.php');
        exit;
    }
}

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>News Management</title>
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    
<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.main-content-wrapper {
    height: 100vh;
    overflow-y: auto;
    padding-bottom: 20px;
}

.container {
    max-width: 1500px;
}

.news-content-preview {
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.news-view-content {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    white-space: pre-wrap;
    line-height: 1.6;
}

.news-meta {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.alert-flash {
    margin-bottom: 20px;
}

/* Search Bar Styles */
.search-container {
    margin-bottom: 20px;
}

.search-wrapper {
    position: relative;
    max-width: 400px;
}

.search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.search-wrapper input {
    padding-left: 35px;
    border-radius: 20px;
    border: 1px solid #dee2e6;
    transition: all 0.3s;
}

.search-wrapper input:focus {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

.clear-search {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    cursor: pointer;
    display: none;
}

.clear-search:hover {
    color: #dc3545;
}

/* Sortable Date Header */
.sortable-header {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
}

.sortable-header:hover {
    background-color: #e9ecef;
}

.sort-icons {
    display: inline-flex;
    flex-direction: column;
    margin-left: 5px;
    font-size: 12px;
    line-height: 1;
    vertical-align: middle;
}

.sort-icons i {
    color: #adb5bd;
    transition: color 0.2s;
}

.sort-icons i.active {
    color: #198754;
}

.sort-asc .fa-sort-up {
    color: #198754;
}

.sort-desc .fa-sort-down {
    color: #198754;
}

/* Toggle Switch Styles */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 26px;
    cursor: pointer;
}

.toggle-switch.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #198754;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-label {
    font-size: 12px;
    margin-top: 4px;
    text-align: center;
    color: #6c757d;
    font-weight: 500;
}

/* Fixed Height Table with Scrollable Body */
.table-container {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 250px);
    min-height: 400px;
}

.table-scrollable {
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
}

.table-scrollable table {
    margin-bottom: 0;
    min-width: 1200px;
}

.table-scrollable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f8f9fa;
}

.table-scrollable thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
}

.status-cell {
    min-width: 90px;
}

/* Loading spinner */
.toggle-loading {
    opacity: 0.6;
    pointer-events: none;
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* No results message */
.no-results {
    text-align: center;
    padding: 60px 40px;
    color: #6c757d;
}

.no-results i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.3;
}

/* Card header with search */
.card-header-custom {
    background-color: white;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem;
}

/* Table header background */
.table thead th {
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-container {
        max-height: calc(100vh - 300px);
    }
}
</style>
</head>

<body class="bg-light">
<div class="lms-sidebar-container">
  <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-newspaper me-2"></i>News Management</h4>
    <a href="?act=addform" class="btn btn-success">
      <i class="fas fa-plus me-1"></i>Add News
    </a>
  </div>

  <?php $flash = get_flash(); ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show alert-flash" role="alert">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

<?php if ($act === 'addform'): ?>

  <!-- ADD FORM -->
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add News</h5>
    </div>
    <div class="card-body">
      <form method="post" action="?act=add">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input name="title" class="form-control" placeholder="Enter news title" required maxlength="255">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Content <span class="text-danger">*</span></label>
          <textarea name="body" class="form-control" rows="10" placeholder="Write your news content here..." required></textarea>
        </div>
        
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-1"></i>Publish News
          </button>
          <a href="news_crud.php" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i>Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($act === 'editform'): ?>

  <!-- EDIT FORM -->
  <div class="card shadow-sm">
    <div class="card-header bg-warning">
      <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit News</h5>
    </div>
    <div class="card-body">
      <form method="post" action="?act=edit">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= $editNews['id'] ?>">

        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input name="title"
                 class="form-control"
                 value="<?= htmlspecialchars($editNews['title']) ?>"
                 required maxlength="255">
        </div>

        <div class="mb-3">
          <label class="form-label">Content <span class="text-danger">*</span></label>
          <textarea name="body"
                    class="form-control"
                    rows="10"
                    required><?= htmlspecialchars($editNews['body']) ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-save me-1"></i>Update News
          </button>
          <a href="news_crud.php" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i>Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($act === 'view' && $viewNews): ?>

  <!-- VIEW SINGLE NEWS -->
  <?php $can_manage = !$is_proponent_only || $viewNews['created_by'] == $current_user_id; ?>
  <div class="card shadow-sm">
    <div class="card-header bg-info text-white">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-eye me-2"></i>View News</h5>
        <div>
          <?php if ($can_manage): ?>
            <a href="?act=editform&id=<?= $viewNews['id'] ?>" class="btn btn-warning btn-sm me-2">
              <i class="fas fa-edit me-1"></i>Edit
            </a>
          <?php endif; ?>
          <a href="news_crud.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back to List
          </a>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <h3><?= htmlspecialchars($viewNews['title']) ?></h3>
        <?php if ($can_manage): ?>
          <div class="text-center">
            <label class="toggle-switch" for="viewToggle<?= $viewNews['id'] ?>">
              <input type="checkbox" 
                     id="viewToggle<?= $viewNews['id'] ?>" 
                     <?= $viewNews['is_published'] ? 'checked' : '' ?>
                     onchange="togglePublish(<?= $viewNews['id'] ?>, this)">
              <span class="toggle-slider"></span>
            </label>
            <div class="toggle-label" id="viewToggleLabel<?= $viewNews['id'] ?>">
              <?= $viewNews['is_published'] ? 'Published' : 'Draft' ?>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center">
            <label class="toggle-switch disabled">
              <input type="checkbox" <?= $viewNews['is_published'] ? 'checked' : '' ?> disabled>
              <span class="toggle-slider"></span>
            </label>
            <div class="toggle-label">
              <?= $viewNews['is_published'] ? 'Published' : 'Draft' ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="news-meta">
        <i class="fas fa-user me-1"></i> Posted by: <?= htmlspecialchars($viewNews['username']) ?> 
        <span class="mx-2">|</span>
        <i class="fas fa-calendar me-1"></i> <?= date('F d, Y h:i A', strtotime($viewNews['created_at'])) ?>
        <?php if ($viewNews['updated_at'] && $viewNews['updated_at'] != $viewNews['created_at']): ?>
          <span class="mx-2">|</span>
          <i class="fas fa-edit me-1"></i> Updated: <?= date('F d, Y h:i A', strtotime($viewNews['updated_at'])) ?>
        <?php endif; ?>
      </div>
      
      <div class="news-view-content">
        <?= nl2br(htmlspecialchars($viewNews['body'])) ?>
      </div>
    </div>
  </div>

<?php else: ?>

  <!-- LIST -->
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
          <i class="fas fa-list me-2"></i>News List
          <span class="badge bg-secondary ms-2" id="newsCount"><?= count($news) ?></span>
        </h5>
        
        <!-- Search Bar -->
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" 
                 id="newsSearch" 
                 class="form-control" 
                 placeholder="Search news by title, content, or author...">
          <i class="fas fa-times clear-search" id="clearSearch"></i>
        </div>
      </div>
    </div>
    
    <!-- Scrollable Table Container -->
    <div class="table-container">
      <div class="table-scrollable">
        <table class="table table-hover align-middle" id="newsTable">
          <thead>
            <tr>
              <th style="min-width: 200px;">Title</th>
              <th style="min-width: 300px;">Content</th>
              <th class="text-center" style="min-width: 100px;">Status</th>
              <th class="text-center" style="min-width: 120px;">Author</th>
              <th class="text-center sortable-header" id="dateHeader" onclick="sortTable()" style="min-width: 120px;">
                Date
                <span class="sort-icons" id="sortIcons">
                  <i class="fas fa-sort-up" id="sortAsc"></i>
                  <i class="fas fa-sort-down" id="sortDesc"></i>
                </span>
              </th>
              <th class="text-center" style="min-width: 140px;">Actions</th>
            </tr>
          </thead>
          <tbody id="newsTableBody">
          <?php if (empty($news)): ?>
            <tr class="news-row">
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-newspaper fa-3x mb-3" style="opacity: 0.3;"></i>
                <p>No news articles found. Click "Add News" to create one.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($news as $n): ?>
              <?php 
              $can_manage = !$is_proponent_only || $n['created_by'] == $current_user_id;
              ?>
              <tr class="news-row" 
                  data-title="<?= htmlspecialchars(strtolower($n['title'])) ?>"
                  data-content="<?= htmlspecialchars(strtolower(strip_tags($n['body']))) ?>"
                  data-author="<?= htmlspecialchars(strtolower($n['username'])) ?>"
                  data-date="<?= strtotime($n['created_at']) ?>"
                  data-original-date="<?= $n['created_at'] ?>">
                <td>
                  <strong class="news-title"><?= htmlspecialchars($n['title']) ?></strong>
                </td>
                <td>
                  <div class="news-content-preview" title="<?= htmlspecialchars($n['body']) ?>">
                    <?php 
                    $preview = strip_tags($n['body']);
                    $preview = strlen($preview) > 100 ? substr($preview, 0, 100) . '...' : $preview;
                    echo htmlspecialchars($preview);
                    ?>
                  </div>
                </td>
                <td class="text-center status-cell">
                  <?php if ($can_manage): ?>
                    <div>
                      <label class="toggle-switch" for="toggle<?= $n['id'] ?>">
                        <input type="checkbox" 
                               id="toggle<?= $n['id'] ?>" 
                               <?= $n['is_published'] ? 'checked' : '' ?>
                               onchange="togglePublish(<?= $n['id'] ?>, this)">
                        <span class="toggle-slider"></span>
                      </label>
                      <div class="toggle-label" id="toggleLabel<?= $n['id'] ?>">
                        <?= $n['is_published'] ? 'Published' : 'Draft' ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div>
                      <label class="toggle-switch disabled">
                        <input type="checkbox" <?= $n['is_published'] ? 'checked' : '' ?> disabled>
                        <span class="toggle-slider"></span>
                      </label>
                      <div class="toggle-label">
                        <?= $n['is_published'] ? 'Published' : 'Draft' ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-center news-author"><?= htmlspecialchars($n['username']) ?></td>
                <td class="text-center news-date">
                  <small><?= date('M d, Y', strtotime($n['created_at'])) ?></small>
                </td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    <a href="?act=view&id=<?= $n['id'] ?>"
                       class="btn btn-sm btn-info"
                       title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php if ($can_manage): ?>
                      <a href="?act=editform&id=<?= $n['id'] ?>"
                         class="btn btn-sm btn-warning"
                         title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      
                      <a href="?act=delete&id=<?= $n['id'] ?>"
                         class="btn btn-sm btn-danger"
                         title="Delete"
                         onclick="return confirm('Are you sure you want to delete this news item?')">
                        <i class="fas fa-trash"></i>
                      </a>
                    <?php else: ?>
                      <button class="btn btn-sm btn-secondary" disabled title="You can only manage your own news">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn btn-sm btn-secondary" disabled title="You can only manage your own news">
                        <i class="fas fa-trash"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- No Results Message (Hidden by default) -->
    <div id="noResultsMessage" class="no-results" style="display: none;">
      <i class="fas fa-search"></i>
      <h5>No Results Found</h5>
      <p>No news articles match your search criteria.</p>
    </div>
  </div>

<?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global variables for sorting
let currentSortDirection = 'desc'; // 'asc' or 'desc'
let searchTerm = '';

// Toggle Publish Status with AJAX
function togglePublish(id, checkbox) {
    const toggleSwitch = checkbox.closest('.toggle-switch');
    const labelDiv = document.getElementById('toggleLabel' + id) || document.getElementById('viewToggleLabel' + id);
    
    toggleSwitch.classList.add('toggle-loading');
    const originalLabel = labelDiv.textContent;
    labelDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('?act=toggle&id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            labelDiv.textContent = data.is_published ? 'Published' : 'Draft';
            showToast(data.message, 'success');
        } else {
            checkbox.checked = !checkbox.checked;
            labelDiv.textContent = originalLabel;
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        labelDiv.textContent = originalLabel;
        showToast('An error occurred. Please try again.', 'danger');
    })
    .finally(() => {
        toggleSwitch.classList.remove('toggle-loading');
    });
}

// Real-time search functionality
function filterTable() {
    const rows = document.querySelectorAll('.news-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const title = row.dataset.title || '';
        const content = row.dataset.content || '';
        const author = row.dataset.author || '';
        
        const matches = title.includes(searchTerm) || 
                       content.includes(searchTerm) || 
                       author.includes(searchTerm);
        
        if (matches) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update count badge
    const countBadge = document.getElementById('newsCount');
    if (countBadge) {
        countBadge.textContent = visibleCount;
    }
    
    // Show/hide no results message
    const noResultsMsg = document.getElementById('noResultsMessage');
    const tableContainer = document.querySelector('.table-container');
    
    if (visibleCount === 0 && rows.length > 0) {
        tableContainer.style.display = 'none';
        noResultsMsg.style.display = 'block';
    } else {
        tableContainer.style.display = 'flex';
        noResultsMsg.style.display = 'none';
    }
    
    // Update clear button visibility
    updateClearButton();
}

// Sort table by date
function sortTable() {
    const tbody = document.getElementById('newsTableBody');
    const rows = Array.from(document.querySelectorAll('.news-row'));
    
    // Filter out any hidden rows first (respect search)
    const visibleRows = rows.filter(row => row.style.display !== 'none');
    const hiddenRows = rows.filter(row => row.style.display === 'none');
    
    // Sort visible rows
    visibleRows.sort((a, b) => {
        const dateA = parseInt(a.dataset.date);
        const dateB = parseInt(b.dataset.date);
        
        if (currentSortDirection === 'asc') {
            return dateA - dateB;
        } else {
            return dateB - dateA;
        }
    });
    
    // Update date display format based on sort direction
    visibleRows.forEach(row => {
        const dateCell = row.querySelector('.news-date small');
        const originalDate = row.dataset.originalDate;
        if (dateCell && originalDate) {
            const date = new Date(originalDate);
            dateCell.textContent = date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: '2-digit', 
                year: 'numeric' 
            });
        }
    });
    
    // Reorder rows in DOM
    visibleRows.forEach(row => tbody.appendChild(row));
    hiddenRows.forEach(row => tbody.appendChild(row));
    
    // Toggle sort direction
    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
    
    // Update sort icons
    updateSortIcons();
}

// Update sort icons based on current direction
function updateSortIcons() {
    const dateHeader = document.getElementById('dateHeader');
    const sortAsc = document.getElementById('sortAsc');
    const sortDesc = document.getElementById('sortDesc');
    
    dateHeader.classList.remove('sort-asc', 'sort-desc');
    
    if (currentSortDirection === 'asc') {
        dateHeader.classList.add('sort-asc');
        sortAsc.classList.add('active');
        sortDesc.classList.remove('active');
    } else {
        dateHeader.classList.add('sort-desc');
        sortDesc.classList.add('active');
        sortAsc.classList.remove('active');
    }
}

// Update clear search button visibility
function updateClearButton() {
    const searchInput = document.getElementById('newsSearch');
    const clearBtn = document.getElementById('clearSearch');
    
    if (searchInput.value.length > 0) {
        clearBtn.style.display = 'block';
    } else {
        clearBtn.style.display = 'none';
    }
}

// Clear search
function clearSearch() {
    const searchInput = document.getElementById('newsSearch');
    searchInput.value = '';
    searchTerm = '';
    filterTable();
    searchInput.focus();
}

// Toast notification function
function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.style.minWidth = '300px';
    toast.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
        if (toastContainer.children.length === 0) {
            toastContainer.remove();
        }
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('newsSearch');
    const clearBtn = document.getElementById('clearSearch');
    
    // Search input event listener
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            searchTerm = e.target.value.toLowerCase().trim();
            filterTable();
        });
    }
    
    // Clear search button
    if (clearBtn) {
        clearBtn.addEventListener('click', clearSearch);
    }
    
    // Initialize sort icons (default DESC)
    updateSortIcons();
    
    // Initialize clear button
    updateClearButton();
    
    // Set initial news count
    const countBadge = document.getElementById('newsCount');
    if (countBadge) {
        const visibleRows = document.querySelectorAll('.news-row:not([style*="display: none"])').length;
        countBadge.textContent = visibleRows;
    }
});
</script>
</body>
</html>