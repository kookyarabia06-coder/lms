<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

if (!is_admin() && !is_proponent()) {
    http_response_code(403);
    exit('Access denied');
}

$act = $_GET['act'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

/**
 * Calculate expiration date
 */
function calculateExpiry($expires_at, $valid_days) {
    if (!empty($valid_days) && is_numeric($valid_days)) {
        return date('Y-m-d', strtotime('+' . (int)$valid_days . ' days'));
    }
    return !empty($expires_at) ? $expires_at : null;
}

/**
 * Handle file upload
 */
function uploadFile($input, $dir, $allowed = []) {
    if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
    if ($allowed && !in_array($ext, $allowed)) {
        return null;
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($_FILES[$input]['tmp_name'], __DIR__ . "/../uploads/$dir/$filename");

    return $filename;
}

/* =========================
   ADD COURSE
========================= */
if ($act === 'addform' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $expires_at = calculateExpiry(
        $_POST['expires_at'] ?? null,
        $_POST['valid_days'] ?? null
    );

    $stmt = $pdo->prepare("
        INSERT INTO courses (
            title, description, thumbnail, file_pdf, file_video,
            proponent_id, created_at, expires_at, is_active
        ) VALUES (
            :title, :description, :thumbnail, :pdf, :video,
            :proponent_id, NOW(), :expires_at, 1
        )
    ");

    $stmt->execute([
        ':title'        => $_POST['title'],
        ':description'  => $_POST['description'],
        ':thumbnail'    => uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']),
        ':pdf'          => uploadFile('file_pdf', 'pdf', ['pdf']),
        ':video'        => uploadFile('file_video', 'video', ['mp4','webm']),
        ':proponent_id' => $_SESSION['user']['id'],
        ':expires_at'   => $expires_at
    ]);

    header('Location: courses_crud.php');
    exit;
}

/* =========================
   EDIT COURSE
========================= */
if ($act === 'edit' && $id) {

    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $course = $stmt->fetch();

    if (!$course) {
        exit('Course not found');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $expires_at = calculateExpiry(
            $_POST['expires_at'] ?? null,
            $_POST['valid_days'] ?? null
        );

        $stmt = $pdo->prepare("
            UPDATE courses SET
                title       = :title,
                description = :description,
                expires_at  = :expires_at,
                thumbnail   = :thumbnail,
                file_pdf    = :pdf,
                file_video  = :video
            WHERE id = :id
        ");

        $stmt->execute([
            ':title'       => $_POST['title'],
            ':description' => $_POST['description'],
            ':expires_at'  => $expires_at,
            ':thumbnail'   => uploadFile('thumbnail','images',['jpg','jpeg','png','webp']) ?? $course['thumbnail'],
            ':pdf'         => uploadFile('file_pdf','pdf',['pdf']) ?? $course['file_pdf'],
            ':video'       => uploadFile('file_video','video',['mp4','webm']) ?? $course['file_video'],
            ':id'          => $id
        ]);

        header('Location: courses_crud.php');
        exit;
    }
}

/* =========================
   DELETE COURSE
========================= */
if ($act === 'delete' && $id && is_admin()) {
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: courses_crud.php');
    exit;
}

/* =========================
   FETCH COURSES
========================= */
$courses = $pdo->query("
    SELECT c.*, u.username 
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id
    ORDER BY c.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Courses CRUD</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
<h3 class="mb-4">Courses Management</h3>

<?php if ($act === 'addform' || $act === 'edit'): ?>
<?php $editing = ($act === 'edit'); ?>

<div class="card p-4 mb-4 shadow-sm bg-white rounded">
<form method="post" enctype="multipart/form-data">

    <div class="mb-3">
        <input name="title" class="form-control" placeholder="Title" required
               value="<?= $editing ? htmlspecialchars($course['title']) : '' ?>">
    </div>

    <div class="mb-3">
        <textarea name="description" class="form-control" rows="4" required
                  placeholder="Description"><?= $editing ? htmlspecialchars($course['description']) : '' ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Expiration Date</label>
            <input type="date" name="expires_at" id="expires_at" class="form-control"
                   value="<?= $editing && $course['expires_at'] ? $course['expires_at'] : '' ?>">
        </div>

        <div class="col-md-6 mb-3">
            <label>Validity (Days)</label>
            <input type="number" name="valid_days" id="valid_days" class="form-control"
                   placeholder="Example: 5"
                   value="<?= ($editing && !empty($course['expires_at']))
                       ? max(0, (int) ceil((strtotime($course['expires_at']) - time()) / 86400))
                       : '' ?>">
            <small class="text-muted">Auto-calculated when date is selected</small>
        </div>
    </div>

    <div class="mb-3">
        <label>Thumbnail</label>
        <input type="file" name="thumbnail" class="form-control">
        <?php if ($editing && $course['thumbnail']): ?>
            <img src="<?= BASE_URL ?>/uploads/images/<?= $course['thumbnail'] ?>" width="120" class="mt-2">
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label>PDF</label>
        <input type="file" name="file_pdf" class="form-control">
        <?php if ($editing && $course['file_pdf']): ?>
            <a href="<?= BASE_URL ?>/uploads/pdf/<?= $course['file_pdf'] ?>" target="_blank">View PDF</a>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label>Video</label>
        <input type="file" name="file_video" class="form-control">
        <?php if ($editing && $course['file_video']): ?>
            <a href="<?= BASE_URL ?>/uploads/video/<?= $course['file_video'] ?>" target="_blank">View Video</a>
        <?php endif; ?>
    </div>

    <button class="btn btn-primary"><?= $editing ? 'Update Course' : 'Add Course' ?></button>
    <a href="courses_crud.php" class="btn btn-secondary ms-2">Back</a>
</form>
</div>

<?php else: ?>

<a href="?act=addform" class="btn btn-success mb-3">Add New Course</a>

<div class="modern-courses-grid">
    <?php foreach ($courses as $c): ?>
        <div class="modern-course-card">
            <div class="modern-card-img">
                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'placeholder.png') ?>" alt="Course Image">
            </div>
        <div class="modern-card-body">
            <div class="modern-card-title">
                <div class="modern-card-title">
                    <h6><?= htmlspecialchars($c['title']) ?></h6>
                </div>
            </div>
        <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>
            <div class="modern-course-info">
                <?php
                $startDate = date('M, d, Y', strtotime($c['created_at']));
                $expiryDate = $c['expires_at']
                    ? date('M, d, Y', strtotime($c['expires_at']))
                    : 'No expiry';
                ?>
            <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
            <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
            </div>
        <div class="modern-card-actions">
            <a href="?act=edit&id=<?= $c['id'] ?>" class="modern-btn-warning modern-btn-sm">Edit</a>
                <?php if (is_admin()): ?>
                    <a href="?act=delete&id=<?= $c['id'] ?>" class="modern-btn-danger modern-btn-sm"
                       onclick="return confirm('Delete this course?')">Delete</a>
                <?php endif; ?>
        </div>  
        </div>
        </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const expires = document.getElementById('expires_at');
    const days = document.getElementById('valid_days');

    if (!expires || !days) return;

    expires.addEventListener('change', function () {
        if (!this.value) {
            days.value = '';
            return;
        }

        const today = new Date();
        today.setHours(0,0,0,0);

        const exp = new Date(this.value);
        exp.setHours(0,0,0,0);

        const diff = Math.ceil((exp - today) / (1000 * 60 * 60 * 24));
        days.value = diff >= 0 ? diff : 0;
    });
});
</script>

</body>
</html>
