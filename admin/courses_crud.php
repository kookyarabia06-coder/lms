<?php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
http_response_code(403);
exit('Access denied');
}

$act = $_GET['act'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fetch all departments for dropdown
$dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$departments = $dept_stmt->fetchAll();

// First, check if course_departments table exists and create it if not
try {
$pdo->query("SELECT 1 FROM course_departments LIMIT 1");
} catch (Exception $e) {
// Table doesn't exist, create it
$pdo->exec("
CREATE TABLE IF NOT EXISTS `course_departments` (
`course_id` int(11) NOT NULL,
`department_id` int(11) NOT NULL,
PRIMARY KEY (`course_id`, `department_id`),
KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Add foreign key constraints if the tables exist
try {
$pdo->exec("
ALTER TABLE `course_departments`
ADD CONSTRAINT `course_departments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `course_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
");
} catch (Exception $e) {
// Foreign keys might already exist or tables don't exist yet - ignore
}
}

// Check if updated_at column exists and add it if not
try {
$pdo->query("SELECT updated_at FROM courses LIMIT 1");
} catch (Exception $e) {
// Column doesn't exist, add it
try {
$pdo->exec("ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
} catch (Exception $e) {
// Column might already exist
}
}

// Fetch course departments if editing
$course_departments = [];
if ($act === 'edit' && $id) {
$dept_course_stmt = $pdo->prepare("
SELECT department_id 
FROM course_departments 
WHERE course_id = :course_id
");
$dept_course_stmt->execute([':course_id' => $id]);
$course_departments = $dept_course_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get departments for user profile (for filtering in form)
$user_departments = [];
if (isset($_SESSION['user']['id'])) {
// Check if user_departments table exists
try {
$pdo->query("SELECT 1 FROM user_departments LIMIT 1");
$stmt = $pdo->prepare("
SELECT d.id, d.name 
FROM departments d
JOIN user_departments ud ON ud.department_id = d.id
WHERE ud.user_id = ?
ORDER BY d.name
");
$stmt->execute([$_SESSION['user']['id']]);
$user_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
// If table doesn't exist, show all departments
$user_departments = $departments;
}
}

/**
 * Calculate expiration date
 */
function calculateExpiry($expires_at, $valid_days) {
if (!empty($valid_days) && is_numeric($valid_days) && $valid_days > 0) {
return date('Y-m-d', strtotime("+{$valid_days} days"));
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
$upload_dir = __DIR__ . "/../uploads/$dir/";

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
mkdir($upload_dir, 0777, true);
}

if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $filename)) {
return $filename;
}

return null;
}

/**
 * Check if current user can edit/delete course
 * Returns true for admins OR if user owns the course
 */
function canModifyCourse($course_id, $pdo) {
if (is_admin() || is_superadmin()) {
return true;
}

$stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = :id");
$stmt->execute([':id' => $course_id]);
$course = $stmt->fetch();

return $course && $course['proponent_id'] == $_SESSION['user']['id'];
}

/**
 * Save course departments
 */
function saveCourseDepartments($course_id, $department_ids, $pdo) {
// Delete existing department associations
$stmt = $pdo->prepare("DELETE FROM course_departments WHERE course_id = :course_id");
$stmt->execute([':course_id' => $course_id]);

// Insert new department associations
if (!empty($department_ids) && is_array($department_ids)) {
$insert_stmt = $pdo->prepare("
INSERT INTO course_departments (course_id, department_id) 
VALUES (:course_id, :department_id)
");

foreach ($department_ids as $dept_id) {
// Skip empty values
if (empty($dept_id)) continue;

$insert_stmt->execute([
':course_id' => $course_id,
':department_id' => $dept_id
]);
}
}
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
title, description, summary, thumbnail, file_pdf, file_video,
proponent_id, created_at, expires_at, is_active
) VALUES (
:title, :description, :summary, :thumbnail, :pdf, :video,
:proponent_id, NOW(), :expires_at, 1
)
");

$stmt->execute([
':title'         => $_POST['title'],
':description'   => $_POST['description'],
':summary'       => $_POST['summary'],
':thumbnail'     => uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']),
':pdf'           => uploadFile('file_pdf', 'pdf', ['pdf']),
':video'         => uploadFile('file_video', 'video', ['mp4','webm']),
':proponent_id'  => $_SESSION['user']['id'],
':expires_at'    => $expires_at
]);

$course_id = $pdo->lastInsertId();

// Save department associations
if (isset($_POST['departments']) && is_array($_POST['departments'])) {
saveCourseDepartments($course_id, $_POST['departments'], $pdo);
}

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

// Check if user can edit this course
if (!canModifyCourse($id, $pdo)) {
http_response_code(403);
exit('Access denied: You can only edit your own courses');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$expires_at = calculateExpiry(
$_POST['expires_at'] ?? null,
$_POST['valid_days'] ?? null
);

// Handle file uploads
$thumbnail = uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']);
$pdf = uploadFile('file_pdf', 'pdf', ['pdf']);
$video = uploadFile('file_video', 'video', ['mp4','webm']);

$stmt = $pdo->prepare("
UPDATE courses SET
title       = :title,
description = :description,
summary     = :summary,
expires_at  = :expires_at,
thumbnail   = COALESCE(:thumbnail, thumbnail),
file_pdf    = COALESCE(:pdf, file_pdf),
file_video  = COALESCE(:video, file_video),
updated_at  = NOW()
WHERE id = :id
");

$stmt->execute([
':title'       => $_POST['title'],
':description' => $_POST['description'],
':summary'     => $_POST['summary'],
':expires_at'  => $expires_at,
':thumbnail'   => $thumbnail,
':pdf'         => $pdf,
':video'       => $video,
':id'          => $id
]);

// Save department associations
if (isset($_POST['departments']) && is_array($_POST['departments'])) {
saveCourseDepartments($id, $_POST['departments'], $pdo);
} else {
// If no departments selected, remove all associations
saveCourseDepartments($id, [], $pdo);
}

header('Location: courses_crud.php');
exit;
}
}

/* =========================
DELETE COURSE
========================= */
if ($act === 'delete' && $id) {
// Check if user can delete this course
if (!canModifyCourse($id, $pdo)) {
http_response_code(403);
exit('Access denied: You can only delete your own courses');
}

$stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
$stmt->execute([':id' => $id]);
header('Location: courses_crud.php');
exit;
}

/* =========================
FETCH COURSES WITH UPDATED AT AND DEPARTMENTS
========================= */

// Build query based on user role
if (is_admin() || is_superadmin()) {
// Admins see all courses
$stmt = $pdo->query("
SELECT c.*, u.username 
FROM courses c 
LEFT JOIN users u ON c.proponent_id = u.id 
ORDER BY c.updated_at DESC, c.created_at DESC
");
} else {
// Proponents see only their courses
$stmt = $pdo->prepare("
SELECT c.*, u.username 
FROM courses c 
LEFT JOIN users u ON c.proponent_id = u.id 
WHERE c.proponent_id = :user_id
ORDER BY c.updated_at DESC, c.created_at DESC
");
$stmt->execute([':user_id' => $_SESSION['user']['id']]);
}
$courses = $stmt->fetchAll();

// Fetch departments for each course
foreach ($courses as &$course) {
$dept_stmt = $pdo->prepare("
SELECT d.id, d.name 
FROM departments d
JOIN course_departments cd ON d.id = cd.department_id
WHERE cd.course_id = ?
ORDER BY d.name
");
$dept_stmt->execute([$course['id']]);
$course['departments'] = $dept_stmt->fetchAll();
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Courses CRUD</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
.department-badge {
display: inline-block;
background-color: #e9ecef;
color: #495057;
padding: 0.25rem 0.5rem;
margin: 0.125rem;
border-radius: 0.25rem;
font-size: 0.75rem;
font-weight: 500;
}
.department-selector {
max-height: 150px;
overflow-y: auto;
border: 1px solid #ced4da;
border-radius: 0.25rem;
padding: 0.5rem;
}
.department-selector .form-check {
margin-bottom: 0.25rem;
}
.btn-group-action {
display: flex;
gap: 5px;
}
</style>
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
<label>Course Title</label>
<input name="title" class="form-control" placeholder="Title" required
value="<?= $editing ? htmlspecialchars($course['title']) : '' ?>">
</div>

<div class="mb-3">
<label>Course Description</label>
<input name="description" class="form-control" placeholder="Description" required
value="<?= $editing ? htmlspecialchars($course['description']) : '' ?>">
</div>

<div class="mb-3">
<label>Course Summary</label>
<textarea name="summary" class="form-control" rows="4" required
placeholder="Course Summary"><?= $editing ? htmlspecialchars($course['summary']) : '' ?></textarea>
</div>

<!-- Department Selection - Multiple select with checkboxes -->
<div class="mb-3">
<label class="form-label fw-bold">Course Departments</label>
<div class="department-selector border p-3 rounded">
<?php 
// Determine which departments to show
$display_departments = $departments;

if (empty($display_departments)): ?>
<p class="text-muted mb-0">No departments available.</p>
<?php else: 
// Get selected departments from POST if form was submitted, otherwise from database
$selected_depts = [];

// Check if form was submitted (POST data exists)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['departments'])) {
$selected_depts = $_POST['departments'];
} 
// Otherwise use the course_departments from database (for edit mode)
else if ($editing && !empty($course_departments)) {
$selected_depts = $course_departments;
}

foreach ($display_departments as $dept): 
?>
<div class="form-check">
<input class="form-check-input" type="checkbox" 
name="departments[]" 
value="<?= $dept['id'] ?>" 
id="dept_<?= $dept['id'] ?>"
<?= in_array($dept['id'], $selected_depts) ? 'checked' : '' ?>>
<label class="form-check-label" for="dept_<?= $dept['id'] ?>">
<?= htmlspecialchars($dept['name']) ?>
</label>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<small class="text-muted">You can select multiple departments</small>
</div>

<!-- Date fields -->
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
<input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
<?php if ($editing && $course['thumbnail']): ?>
<div class="mt-2">
<img src="<?= BASE_URL ?>/uploads/images/<?= $course['thumbnail'] ?>" width="120" class="border rounded">
<small class="text-muted d-block">Leave empty to keep current image</small>
</div>
<?php endif; ?>
</div>

<div class="mb-3">
<label>PDF</label>
<input type="file" name="file_pdf" class="form-control" accept=".pdf">
<?php if ($editing && $course['file_pdf']): ?>
<div class="mt-2">
<a href="<?= BASE_URL ?>/uploads/pdf/<?= $course['file_pdf'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
<i class="fas fa-file-pdf"></i> View PDF
</a>
<small class="text-muted d-block">Leave empty to keep current file</small>
</div>
<?php endif; ?>
</div>

<div class="mb-3">
<label>Video</label>
<input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
<?php if ($editing && $course['file_video']): ?>
<div class="mt-2">
<a href="<?= BASE_URL ?>/uploads/video/<?= $course['file_video'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
<i class="fas fa-video"></i> View Video
</a>
<small class="text-muted d-block">Leave empty to keep current file</small>
</div>
<?php endif; ?>
</div>

<div class="d-flex gap-2">
<button type="submit" class="btn btn-primary">
<?= $editing ? 'Update Course' : 'Add Course' ?>
</button>

<?php if ($editing): ?>
<a href="../admin/assessment_crud.php?course_id=<?= $id ?>" class="btn btn-info">
<i class="fas fa-file-alt"></i> Create Assessment
</a>
<?php endif; ?>

<a href="courses_crud.php" class="btn btn-secondary">Back</a>
</div>
</form>
</div>

<?php else: ?>

<a href="?act=addform" class="btn btn-success mb-3">Add New Course</a>

<div class="modern-courses-grid">
<?php if (empty($courses)): ?>
<div class="alert alert-info">
<i class="fas fa-info-circle"></i> No courses found. Click "Add New Course" to create one.
</div>
<?php else: ?>
<?php foreach ($courses as $c): ?>
<div class="modern-course-card">
<div class="modern-card-img">
<img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
</div>
<div class="modern-card-body">
<div class="modern-card-title">
<h6><?= htmlspecialchars($c['title']) ?></h6>
</div>
<p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>

<!-- Display departments -->
<?php if (!empty($c['departments'])): ?>
<div class="mb-2">
<strong>Departments:</strong><br>
<?php foreach ($c['departments'] as $dept): ?>
<span class="department-badge"><?= htmlspecialchars($dept['name']) ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modern-course-info">
<?php
$startDate = date('M d, Y', strtotime($c['created_at']));
$expiryDate = $c['expires_at']
? date('M d, Y', strtotime($c['expires_at']))
: 'No expiry';

// Format updated date
$updatedDate = !empty($c['updated_at']) 
? date('M d, Y h:i A', strtotime($c['updated_at'])) 
: 'Never';
?>
<p><strong>Start:</strong> <span><?= $startDate ?></span></p>
<p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
<p><strong>Last Edited:</strong> 
<span class="<?= $c['updated_at'] ? 'text-primary' : 'text-muted' ?>">
<?= $updatedDate ?>
<?php if($c['updated_at']): ?>
<i class="fas fa-pen-alt ms-1" style="font-size: 11px;"></i>
<?php endif; ?>
</span>
</p>
</div>

<div class="btn-group-action">
<a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">View</a>

<?php if (canModifyCourse($c['id'], $pdo)): ?>
<a href="?act=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
<a href="?act=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
onclick="return confirm('Delete this course?')">Delete</a>
<?php endif; ?>
</div>  
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
const expires = document.getElementById('expires_at');
const days = document.getElementById('valid_days');

if (expires && days) {
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
}
});
</script>

</body>
</html>