<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$courseId = intval($_GET['id'] ?? 0);
if(!$courseId) die('Invalid course ID');

// Check which columns exist in enrollments table
$existing_columns = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM enrollments");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
} catch (Exception $e) {
    // If table doesn't exist, create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `enrollments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `course_id` int(11) NOT NULL,
            `status` enum('ongoing','completed') DEFAULT 'ongoing',
            `progress` int(11) DEFAULT 0,
            `total_time_seconds` int(11) DEFAULT 0,
            `enrolled_at` datetime DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $existing_columns = ['id', 'user_id', 'course_id', 'status', 'progress', 'total_time_seconds', 'enrolled_at', 'completed_at'];
}

// Check if assessment exists for this course
$hasAssessment = false;
$assessmentId = null;

try {
    $stmt = $pdo->prepare("SELECT id FROM assessments WHERE course_id = ? LIMIT 1");
    $stmt->execute([$courseId]);
    $assessmentData = $stmt->fetch();
    $hasAssessment = $assessmentData ? true : false;
    $assessmentId = $assessmentData ? $assessmentData['id'] : null;
} catch (Exception $e) {
    // If tables don't exist, just continue without assessment
    $hasAssessment = false;
    $assessmentId = null;
}

// Define all possible columns we want
$all_columns = [
    'video_progress', 'pdf_progress', 'video_completed', 
    'pdf_completed', 'pdf_current_page', 'pdf_total_pages'
];

// Check which ones actually exist
$columns_exist = [];
foreach ($all_columns as $col) {
    $columns_exist[$col] = in_array($col, $existing_columns);
}

// For backward compatibility, set default values
$has_video_tracking = $columns_exist['video_progress'] && $columns_exist['video_completed'];
$has_pdf_tracking = $columns_exist['pdf_progress'] && $columns_exist['pdf_completed'] && 
                    $columns_exist['pdf_current_page'] && $columns_exist['pdf_total_pages'];

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if(!$course) die('Course not found');

// Check if course has materials
$hasVideo = !empty($course['file_video']);
$hasPdf = !empty($course['file_pdf']);

// Fetch enrollment if student
$enrollment = null;

if (is_student()) {

// BLOCK if course expired or inactive
$today = date('Y-m-d');

if (
$course['is_active'] == 0 ||
($course['expires_at'] && $today > $course['expires_at'])
) {
die('<div class="alert alert-danger m-4">
<h5>Course Unavailable</h5>
<p>This course has expired or is no longer active.</p>
</div>');
}

// Build SELECT query based on existing columns
$select_fields = ['id', 'user_id', 'course_id', 'status', 'progress', 'total_time_seconds', 'enrolled_at', 'completed_at'];
foreach ($all_columns as $col) {
    if (in_array($col, $existing_columns)) {
        $select_fields[] = $col;
    }
}
$select_sql = implode(', ', $select_fields);

$stmt = $pdo->prepare("SELECT $select_sql FROM enrollments WHERE user_id=? AND course_id=?");
$stmt->execute([$u['id'], $courseId]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
// Build INSERT query based on existing columns
$insert_fields = ['user_id', 'course_id', 'enrolled_at', 'status', 'progress', 'total_time_seconds'];
$insert_placeholders = ['?', '?', 'NOW()', '?', '?', '?'];
$params = [$u['id'], $courseId, 'ongoing', 0, 0];

foreach ($all_columns as $col) {
    if (in_array($col, $existing_columns)) {
        $insert_fields[] = $col;
        $insert_placeholders[] = '?';
        $params[] = 0;
    }
}

$insert_sql = "INSERT INTO enrollments (" . implode(', ', $insert_fields) . ") 
               VALUES (" . implode(', ', $insert_placeholders) . ")";

$stmt = $pdo->prepare($insert_sql);
$stmt->execute($params);

$enrollmentId = $pdo->lastInsertId();

// Fetch the newly created enrollment
$stmt = $pdo->prepare("SELECT $select_sql FROM enrollments WHERE id=?");
$stmt->execute([$enrollmentId]);
$enrollment = $stmt->fetch();
}

// Ensure all expected fields exist in enrollment array
if (!isset($enrollment['video_progress'])) $enrollment['video_progress'] = 0;
if (!isset($enrollment['pdf_progress'])) $enrollment['pdf_progress'] = 0;
if (!isset($enrollment['video_completed'])) $enrollment['video_completed'] = 0;
if (!isset($enrollment['pdf_completed'])) $enrollment['pdf_completed'] = 0;
if (!isset($enrollment['pdf_current_page'])) $enrollment['pdf_current_page'] = 1;
if (!isset($enrollment['pdf_total_pages'])) $enrollment['pdf_total_pages'] = 0;
}

// Handle AJAX time tracking
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['seconds']) && is_student()) {
$seconds = intval($_POST['seconds']);
$total_seconds = $enrollment['total_time_seconds'] + $seconds;

// Calculate overall progress based on completed materials
$videoCompleted = $enrollment['video_completed'] ?? 0;
$pdfCompleted = $enrollment['pdf_completed'] ?? 0;

$totalMaterials = ($hasVideo ? 1 : 0) + ($hasPdf ? 1 : 0);
$completedMaterials = ($videoCompleted ? 1 : 0) + ($pdfCompleted ? 1 : 0);

$progress = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 50;

$stmt = $pdo->prepare('UPDATE enrollments SET total_time_seconds=?, progress=? WHERE id=?');
$stmt->execute([$total_seconds, $progress, $enrollment['id']]);
echo json_encode(['success'=>true]);
exit;
}

// Handle video completion - only if columns exist
if($has_video_tracking && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['video_completed']) && is_student()) {
$video_completed = intval($_POST['video_completed']);

// Update video progress and completion
$stmt = $pdo->prepare('UPDATE enrollments SET video_progress=100, video_completed=? WHERE id=?');
$stmt->execute([$video_completed, $enrollment['id']]);

// Recalculate overall progress
$videoCompleted = $video_completed;
$pdfCompleted = $enrollment['pdf_completed'] ?? 0;

$totalMaterials = ($hasVideo ? 1 : 0) + ($hasPdf ? 1 : 0);
$completedMaterials = ($videoCompleted ? 1 : 0) + ($pdfCompleted ? 1 : 0);

$progress = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 100;

$stmt = $pdo->prepare('UPDATE enrollments SET progress=? WHERE id=?');
$stmt->execute([$progress, $enrollment['id']]);

echo json_encode(['success'=>true, 'progress' => $progress]);
exit;
}

// Handle PDF completion - only if columns exist
if($has_pdf_tracking && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pdf_completed']) && is_student()) {
$pdf_completed = intval($_POST['pdf_completed']);

// Update PDF progress and completion
$stmt = $pdo->prepare('UPDATE enrollments SET pdf_progress=100, pdf_completed=? WHERE id=?');
$stmt->execute([$pdf_completed, $enrollment['id']]);

// Recalculate overall progress
$videoCompleted = $enrollment['video_completed'] ?? 0;
$pdfCompleted = $pdf_completed;

$totalMaterials = ($hasVideo ? 1 : 0) + ($hasPdf ? 1 : 0);
$completedMaterials = ($videoCompleted ? 1 : 0) + ($pdfCompleted ? 1 : 0);

$progress = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 100;

$stmt = $pdo->prepare('UPDATE enrollments SET progress=? WHERE id=?');
$stmt->execute([$progress, $enrollment['id']]);

echo json_encode(['success'=>true, 'progress' => $progress]);
exit;
}

// Handle video progress tracking - only if columns exist
if($has_video_tracking && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['video_progress']) && is_student()) {
$video_progress = intval($_POST['video_progress']);

// Only update if not already completed
if (($enrollment['video_completed'] ?? 0) == 0) {
$stmt = $pdo->prepare('UPDATE enrollments SET video_progress=? WHERE id=?');
$stmt->execute([$video_progress, $enrollment['id']]);
}

echo json_encode(['success'=>true]);
exit;
}

// Handle PDF page tracking - only if columns exist
if($has_pdf_tracking && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pdf_page']) && is_student()) {
$current_page = intval($_POST['pdf_page']);
$total_pages = intval($_POST['total_pages'] ?? 0);

// Calculate progress based on pages
$pdf_progress = $total_pages > 0 ? round(($current_page / $total_pages) * 100) : 0;

// Update PDF page and progress
$stmt = $pdo->prepare('UPDATE enrollments SET pdf_current_page=?, pdf_total_pages=?, pdf_progress=? WHERE id=?');
$stmt->execute([$current_page, $total_pages, $pdf_progress, $enrollment['id']]);

// Check if PDF is completed (100% of pages viewed)
$pdf_completed = $pdf_progress >= 100;
if ($pdf_completed && ($enrollment['pdf_completed'] ?? 0) == 0) {
    $stmt = $pdo->prepare('UPDATE enrollments SET pdf_completed=1 WHERE id=?');
    $stmt->execute([$enrollment['id']]);
}

echo json_encode(['success'=>true, 'progress' => $pdf_progress, 'completed' => $pdf_completed]);
exit;
}

// Handle completion - NO MAIL FUNCTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed']) && is_student()) {

// Check if all requirements are met
$requirements_met = true;
$missing_requirements = [];

// Check materials completion
$materials_complete = true;
if ($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) {
    $materials_complete = false;
    $missing_requirements[] = 'Watch the complete video';
}
if ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1) {
    $materials_complete = false;
    $missing_requirements[] = 'View all PDF pages';
}

if (!$materials_complete) {
    $requirements_met = false;
}

if (!$requirements_met) {
echo json_encode([
'success' => false,
'message' => 'Please complete all requirements first: ' . implode(', ', $missing_requirements)
]);
exit;
}

// Start transaction
$pdo->beginTransaction();

try {
// Update enrollment status
$stmt = $pdo->prepare("
UPDATE enrollments 
SET status = 'completed', completed_at = NOW(), progress = 100
WHERE id = ?
");
$stmt->execute([$enrollment['id']]);

$pdo->commit();

echo json_encode([
'success' => true,
'message' => 'Course completed successfully!'
]);

} catch (Exception $e) {
$pdo->rollBack();
echo json_encode([
'success' => false,
'message' => 'Error completing course: ' . $e->getMessage()
]);
}

exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($course['title'])?> - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- PDF.js library for PDF page tracking -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<!-- Video.js for enhanced video player -->
<link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
<script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
<style>
/* ===== VARIABLES ===== */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --shadow-sm: 0 2px 10px rgba(0,0,0,0.1);
    --border-color: #dee2e6;
    --bg-light: #f8f9fa;
    --text-muted: #6c757d;
}

/* ===== LAYOUT ===== */
body {
    background: #f4f6f9;
    overflow-x: hidden;
}

.main-content-wrapper {
    margin-left: 280px;
    padding: 20px;
    transition: all 0.3s ease;
}

/* ===== FULLSCREEN MODE - TRUE FULLSCREEN ===== */
.pdf-fullscreen-mode {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    z-index: 999999 !important;
    background: #fff !important;
    overflow: hidden !important;
}

.pdf-fullscreen-mode .lms-sidebar-container,
.pdf-fullscreen-mode .course-header,
.pdf-fullscreen-mode .course-info-card,
.pdf-fullscreen-mode .video-player-container,
.pdf-fullscreen-mode .action-buttons,
.pdf-fullscreen-mode .pdf-progress-container {
    display: none !important;
}

.pdf-fullscreen-mode .material-card {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: #1a1a1a !important;
    display: flex !important;
    flex-direction: column !important;
}

.pdf-fullscreen-mode .material-header {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1000000 !important;
    background: rgba(30, 30, 30, 0.95) !important;
    backdrop-filter: blur(10px) !important;
    padding: 12px 20px !important;
    margin: 0 !important;
    border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    color: white !important;
}

.pdf-fullscreen-mode .material-title h5,
.pdf-fullscreen-mode .material-title i {
    color: white !important;
}

.pdf-fullscreen-mode .material-status {
    background: rgba(255,255,255,0.15) !important;
    color: white !important;
}

.pdf-fullscreen-mode #fullscreenPdfBtn {
    background: rgba(255,255,255,0.2) !important;
    color: white !important;
    border-color: rgba(255,255,255,0.3) !important;
}

.pdf-fullscreen-mode #fullscreenPdfBtn:hover {
    background: rgba(255,255,255,0.3) !important;
}

.pdf-fullscreen-mode .pdf-viewer-container {
    position: fixed !important;
    top: 60px !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: calc(100vh - 60px) !important;
    border: none !important;
    border-radius: 0 !important;
    background: #2d2d2d !important;
}

.pdf-fullscreen-mode .pdf-pages-container {
    padding: 30px 20px !important;
    background: #2d2d2d !important;
}

.pdf-fullscreen-mode .pdf-page-wrapper {
    max-width: 900px !important;
    margin: 0 auto !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
    background: white !important;
}

.pdf-fullscreen-mode #fullscreenPdfBtn i {
    transform: rotate(180deg);
}

/* Exit fullscreen hint */
.fullscreen-hint {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    z-index: 1000001;
    pointer-events: none;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.2);
    animation: fadeInOut 3s ease;
}

.fullscreen-hint i {
    margin-right: 6px;
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(10px); }
    10% { opacity: 1; transform: translateY(0); }
    90% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-10px); }
}

/* ===== TOAST NOTIFICATIONS ===== */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* ===== MATERIAL CARDS ===== */
.material-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.material-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.material-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.material-title i {
    font-size: 24px;
}

.material-status {
    font-size: 14px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

/* ===== VIDEO PLAYER ===== */
.video-player-container {
    position: relative;
    width: 100%;
    height: 500px;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}

.video-js {
    width: 100%;
    height: 100% !important;
    position: absolute;
    top: 0;
    left: 0;
}

.video-js .vjs-tech {
    object-fit: contain;
    width: 100%;
    height: 100%;
}

/* ===== PDF VIEWER ===== */
.pdf-viewer-container {
    position: relative;
    width: 100%;
    height: 700px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: auto;
    background: var(--bg-light);
    transition: all 0.3s ease;
}

.pdf-pages-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    gap: 20px;
}

.pdf-page-wrapper {
    position: relative;
    background: white;
    box-shadow: var(--shadow-sm);
    border-radius: 8px;
    padding: 10px;
    max-width: 100%;
}

.pdf-page-canvas {
    width: 100%;
    height: auto;
    display: block;
}

.page-viewed-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #28a745;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 10;
}

/* ===== PDF PROGRESS ===== */
.pdf-progress-container {
    margin-top: 15px;
    padding: 10px;
    background: var(--bg-light);
    border-radius: 8px;
}

.pdf-progress-text {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 14px;
}

.pdf-progress-bar {
    height: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.pdf-progress-fill {
    height: 100%;
    background: var(--primary-gradient);
    transition: width 0.3s ease;
}

.pdf-progress-fill.completed {
    background: linear-gradient(90deg, #28a745, #20c997);
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: var(--shadow-sm);
}

.action-btn {
    padding: 12px 40px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
    min-width: 200px;
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.action-btn i {
    margin-right: 8px;
}

.btn-pulse {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

/* ===== FULLSCREEN BUTTON ===== */
#fullscreenPdfBtn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

#fullscreenPdfBtn:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* ===== UTILITY CLASSES ===== */
.text-muted { color: var(--text-muted); }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.me-1 { margin-right: 0.25rem; }
.me-2 { margin-right: 0.5rem; }
.ms-auto { margin-left: auto; }
.small { font-size: 0.875em; }
.gap-2 { gap: 0.5rem; }
</style>
</head>
<body>
<!-- Sidebar -->
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<!-- Toast notification container -->
<div id="toastContainer" class="toast-notification"></div>

<!-- Main Content -->
<div class="course-content-wrapper">
<!-- Course Header -->
<div class="course-header">
<div class="d-flex justify-content-between align-items-start">
<div>
<h3><?=htmlspecialchars($course['title'])?></h3>
<p><?=nl2br(htmlspecialchars($course['description']))?></p>
</div>
</div>
</div>

<!-- Course Info -->
<div class="course-info-card">
<div class="course-instructor">
<div class="instructor-avatar">
<?= substr($course['fname'] ?? 'I', 0, 1) . substr($course['lname'] ?? 'nstructor', 0, 1) ?>
</div>
<div class="instructor-info">
<h5><?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></h5>
<p>Course Instructor</p>
</div>
</div>
</div>

<!-- Video Material -->
<?php if($hasVideo): ?>
<div class="material-card">
<div class="material-header">
<div class="material-title">
<i class="fas fa-video text-primary"></i>
<h5 class="mb-0">Course Video</h5>
</div>
<?php if(is_student() && $has_video_tracking): ?>
    <?php if(($enrollment['video_completed'] ?? 0) == 1): ?>
        <span class="material-status status-completed">
            <i class="fas fa-check-circle me-1"></i>Completed
        </span>
    <?php else: ?>
        <span class="material-status status-pending">
            <i class="fas fa-clock me-1"></i>Pending
        </span>
    <?php endif; ?>
<?php endif; ?>
</div>

<div class="video-player-container">
<video-js id="courseVideo"
         class="video-js vjs-default-skin"
         controls
         preload="auto"
         width="100%"
         height="100%"
         data-setup='{"fluid": false}'>
<source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars($course['file_video']) ?>" type="video/mp4">
<p class="vjs-no-js">
To view this video please enable JavaScript, and consider upgrading to a
web browser that <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
</p>
</video-js>
</div>

<?php if(is_student() && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1): ?>
    <div class="mt-2 text-muted small">
        <i class="fas fa-info-circle"></i>
        You must watch the entire video to complete this requirement.
    </div>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- PDF Material -->
<?php if($hasPdf): ?>
<div class="material-card" id="pdfMaterialCard">
<div class="material-header">
<div class="material-title">
<i class="fas fa-file-pdf text-danger"></i>
<h5 class="mb-0">Course PDF Material</h5>
</div>
<div class="d-flex align-items-center gap-2">
    <?php if(is_student() && $has_pdf_tracking): ?>
        <?php if(($enrollment['pdf_completed'] ?? 0) == 1): ?>
            <span class="material-status status-completed">
                <i class="fas fa-check-circle me-1"></i>Completed
            </span>
        <?php else: ?>
            <span class="material-status status-pending">
                <i class="fas fa-clock me-1"></i>Pending
            </span>
        <?php endif; ?>
    <?php endif; ?>
    <button class="btn btn-outline-primary btn-sm" id="fullscreenPdfBtn" title="Enter full screen (ESC to exit)">
        <i class="fas fa-expand"></i>
    </button>
</div>
</div>

<?php if($has_pdf_tracking): ?>
<!-- PDF Viewer with Page Tracking - Scrollable -->
<div class="pdf-viewer-container" id="pdfViewerContainer">
    <div id="pdfPagesContainer" class="pdf-pages-container">
        <!-- PDF pages will be rendered here -->
    </div>
</div>

<!-- Progress bar under PDF -->
<div class="pdf-progress-container">
    <div class="pdf-progress-text">
        <span><i class="fas fa-file-pdf me-1"></i>PDF Progress</span>
        <span id="pdfProgressPercentage"><?= ($enrollment['pdf_progress'] ?? 0) ?>%</span>
    </div>
    <div class="pdf-progress-bar">
        <div id="pdfProgressBar" class="pdf-progress-fill <?= (($enrollment['pdf_completed'] ?? 0) == 1) ? 'completed' : '' ?>" 
             style="width: <?= ($enrollment['pdf_progress'] ?? 0) ?>%"></div>
    </div>
    <div class="text-muted small mt-2">
        <i class="fas fa-info-circle"></i>
        Pages viewed: <span id="viewedPagesCount"><?= ($enrollment['pdf_current_page'] ?? 0) ?></span> / <span id="totalPagesCount"><?= ($enrollment['pdf_total_pages'] ?? 0) ?></span>
        (100% required to complete)
    </div>
</div>

<?php else: ?>
<!-- Simple iframe for basic PDF viewing -->
<div class="pdf-viewer-container">
    <iframe
        src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
        width="100%"
        height="600"
        style="border:none; border-radius: 8px;">
    </iframe>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Action Buttons for Students -->
<?php if(is_student()): ?>
<div class="action-buttons">
    <!-- Take Assessment Button -->
    <?php if($hasAssessment && $assessmentId): ?>
        <?php
        // Check if materials are complete
        $materialsComplete = true;
        if ($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) {
            $materialsComplete = false;
        }
        if ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1) {
            $materialsComplete = false;
        }
        ?>
        <a href="take_assessment.php?assessment_id=<?= $assessmentId ?>" 
           id="takeAssessmentBtn" 
           class="btn btn-primary action-btn <?= $materialsComplete ? '' : 'disabled' ?>"
           <?= $materialsComplete ? '' : 'disabled' ?>>
            <i class="fas fa-pencil-alt"></i> Take Assessment
        </a>
    <?php endif; ?>
    
    <!-- Complete Course Button -->
    <button id="completeBtn" 
            class="btn btn-success action-btn <?= ((($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                                                    ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : 'btn-pulse') ?>"
            <?= ((($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                  ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : '') ?>>
        <i class="fas fa-check-circle"></i> Complete Course
    </button>
</div>
<?php endif; ?>

</div>

<!-- Fullscreen hint element (will be shown when entering fullscreen) -->
<div id="fullscreenHint" class="fullscreen-hint" style="display: none;">
    <i class="fas fa-keyboard"></i> Press ESC to exit full screen
</div>

<script>
<?php if(is_student()): ?>
let totalSeconds = parseInt($('#timeSpent').text() || 0);
let videoProgress = <?= ($enrollment['video_progress'] ?? 0) ?>;
let videoCompleted = <?= ($enrollment['video_completed'] ?? 0) ?>;
let videoDuration = 0;
const hasVideo = <?= $hasVideo ? 'true' : 'false' ?>;
const hasPdf = <?= $hasPdf ? 'true' : 'false' ?>;
const hasVideoTracking = <?= $has_video_tracking ? 'true' : 'false' ?>;
const hasPdfTracking = <?= $has_pdf_tracking ? 'true' : 'false' ?>;

// Track viewed pages
let viewedPages = new Set();
let currentPdfProgress = <?= ($enrollment['pdf_progress'] ?? 0) ?>;
let pdfCompleted = <?= ($enrollment['pdf_completed'] ?? 0) ?>;

// Function to check if all materials are completed
function areMaterialsCompleted() {
    let videoComplete = !hasVideo || !hasVideoTracking || videoCompleted;
    let pdfComplete = !hasPdf || !hasPdfTracking || pdfCompleted;
    return videoComplete && pdfComplete;
}

// Function to update button states
function updateButtons() {
    // Update Complete button
    const completeBtn = document.getElementById('completeBtn');
    if (completeBtn) {
        if (areMaterialsCompleted()) {
            completeBtn.classList.remove('disabled');
            completeBtn.classList.add('btn-pulse');
            completeBtn.removeAttribute('disabled');
            console.log('Materials completed - complete button enabled');
        } else {
            completeBtn.classList.add('disabled');
            completeBtn.classList.remove('btn-pulse');
            completeBtn.setAttribute('disabled', 'disabled');
            console.log('Materials not completed - complete button disabled');
        }
    }
    
    // Update Take Assessment button
    const takeBtn = document.getElementById('takeAssessmentBtn');
    if (takeBtn) {
        if (areMaterialsCompleted()) {
            takeBtn.classList.remove('disabled');
            takeBtn.removeAttribute('disabled');
            console.log('Materials completed - assessment button enabled');
        } else {
            takeBtn.classList.add('disabled');
            takeBtn.setAttribute('disabled', 'disabled');
            console.log('Materials not completed - assessment button disabled');
        }
    }
}

// Load viewed pages from localStorage on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, checking materials completion...');
    
    // Try to load viewed pages from localStorage
    const savedViewedPages = localStorage.getItem('pdf_viewed_pages_<?= $courseId ?>');
    if (savedViewedPages) {
        try {
            const parsed = JSON.parse(savedViewedPages);
            viewedPages = new Set(parsed);
            // Update counts based on saved data
            if (viewedPages.size > 0) {
                const totalPages = parseInt(document.getElementById('totalPagesCount')?.textContent || '0');
                if (totalPages > 0) {
                    const progress = Math.round((viewedPages.size / totalPages) * 100);
                    document.getElementById('pdfProgressBar').style.width = progress + '%';
                    document.getElementById('pdfProgressPercentage').textContent = progress + '%';
                    document.getElementById('viewedPagesCount').textContent = viewedPages.size;
                    
                    // Check if PDF is completed based on loaded pages
                    if (progress >= 100 && !pdfCompleted) {
                        pdfCompleted = true;
                        console.log('PDF marked as completed from localStorage');
                    }
                }
            }
        } catch (e) {
            console.error('Error loading saved pages:', e);
        }
    }
    
    // Initial button state update
    updateButtons();
    
    // Fullscreen PDF functionality
    const fullscreenBtn = document.getElementById('fullscreenPdfBtn');
    const pdfMaterialCard = document.getElementById('pdfMaterialCard');
    const fullscreenHint = document.getElementById('fullscreenHint');
    
    if (fullscreenBtn && pdfMaterialCard) {
        
        function enterFullscreen() {
            pdfMaterialCard.classList.add('pdf-fullscreen-mode');
            fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
            fullscreenBtn.title = 'Exit full screen (ESC)';
            
            // Show hint
            fullscreenHint.style.display = 'block';
            
            // Hide hint after 3 seconds
            setTimeout(() => {
                fullscreenHint.style.display = 'none';
            }, 3000);
            
            // Trigger resize to adjust PDF rendering
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 100);
        }
        
        function exitFullscreen() {
            pdfMaterialCard.classList.remove('pdf-fullscreen-mode');
            fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
            fullscreenBtn.title = 'Enter full screen (ESC to exit)';
            fullscreenHint.style.display = 'none';
            
            // Trigger resize to adjust PDF rendering
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 100);
        }
        
        fullscreenBtn.addEventListener('click', function() {
            if (pdfMaterialCard.classList.contains('pdf-fullscreen-mode')) {
                exitFullscreen();
            } else {
                enterFullscreen();
            }
        });
        
        // ESC key to exit fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && pdfMaterialCard.classList.contains('pdf-fullscreen-mode')) {
                exitFullscreen();
            }
        });
    }
});

// Function to show toast notification
function showToast(message, type = 'success') {
    const toast = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);

    $('#toastContainer').append(toast);

    // Auto dismiss after 5 seconds
    setTimeout(() => {
        toast.fadeOut(300, function() { $(this).remove(); });
    }, 5000);
}

// Auto update time spent
setInterval(function(){
    if (<?= ($enrollment['status'] ?? '') !== 'completed' ? 'true' : 'false' ?>) {
        totalSeconds++;
        $.post(window.location.href, {seconds: 1});
    }
}, 1000);

// Video Element with Video.js
if (hasVideo && hasVideoTracking && !videoCompleted) {
    const player = videojs('courseVideo');
    console.log('Video player initialized');
    
    // Load saved video progress from localStorage
    const savedVideoProgress = localStorage.getItem('video_progress_<?= $courseId ?>');
    if (savedVideoProgress && !videoCompleted) {
        const savedTime = parseInt(savedVideoProgress);
        if (savedTime > 0) {
            player.ready(function() {
                this.currentTime(savedTime);
                console.log('Restored video position:', savedTime);
            });
        }
    }
    
    player.on('timeupdate', function() {
        const duration = player.duration();
        const currentTime = player.currentTime();
        
        // Save current time to localStorage every 5 seconds
        if (Math.floor(currentTime) % 5 === 0) {
            localStorage.setItem('video_progress_<?= $courseId ?>', currentTime.toString());
        }
        
        if (duration > 0 && !videoCompleted) {
            let progress = (currentTime / duration) * 100;
            progress = Math.min(100, Math.round(progress));
            
            // Update if progress increased
            if (progress > videoProgress) {
                videoProgress = progress;
                $.post(window.location.href, {video_progress: videoProgress});
                console.log('Video progress updated:', progress + '%');
            }
            
            // Check if video is complete (98% or more to account for rounding)
            if (videoProgress >= 98 && !videoCompleted) {
                videoCompleted = true;
                console.log('Video completed!');
                
                $.post(window.location.href, {video_completed: 1}, function(response) {
                    console.log('Video completion saved:', response);
                }).fail(function(xhr) {
                    console.error('Failed to save video completion:', xhr);
                });
                
                localStorage.removeItem('video_progress_<?= $courseId ?>');
                
                // Update UI for completion
                $('.material-card:has(i.fa-video) .material-status')
                    .removeClass('status-pending').addClass('status-completed')
                    .html('<i class="fas fa-check-circle me-1"></i>Completed');
                
                showToast('✓ Video completed!', 'success');
                updateButtons();
            }
        }
    });
    
    player.on('ended', function() {
        if (!videoCompleted) {
            videoCompleted = true;
            videoProgress = 100;
            console.log('Video ended - marking as completed');
            
            $.post(window.location.href, {video_progress: 100, video_completed: 1}, function(response) {
                console.log('Video completion saved:', response);
            }).fail(function(xhr) {
                console.error('Failed to save video completion:', xhr);
            });
            
            localStorage.removeItem('video_progress_<?= $courseId ?>');
            
            // Update UI
            $('.material-card:has(i.fa-video) .material-status')
                .removeClass('status-pending').addClass('status-completed')
                .html('<i class="fas fa-check-circle me-1"></i>Completed');
            
            showToast('✓ Video completed!', 'success');
            updateButtons();
        }
    });
}

// PDF Page Tracking
if (hasPdf && hasPdfTracking) {
    console.log('Initializing PDF viewer...');
    const pdfUrl = '<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>';
    const pagesContainer = document.getElementById('pdfPagesContainer');
    const pdfProgressBar = document.getElementById('pdfProgressBar');
    const pdfProgressPercentage = document.getElementById('pdfProgressPercentage');
    const viewedPagesCount = document.getElementById('viewedPagesCount');
    const totalPagesSpan = document.getElementById('totalPagesCount');
    
    // Initialize viewed pages from database
    <?php
    // Get all viewed pages from database if you have a table for this
    // For now, we'll just use the current page as viewed
    $viewedPages = [$enrollment['pdf_current_page'] ?? 1];
    ?>
    
    viewedPages = new Set([<?= implode(',', $viewedPages) ?>]);
    console.log('Initial viewed pages:', Array.from(viewedPages));
    
    // Load PDF
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc) {
        const numPages = pdfDoc.numPages;
        totalPagesSpan.textContent = numPages;
        console.log('PDF loaded with', numPages, 'pages');
        
        // Update total pages in database if different
        if (numPages != <?= ($enrollment['pdf_total_pages'] ?? 0) ?>) {
            $.post(window.location.href, {
                pdf_page: <?= ($enrollment['pdf_current_page'] ?? 1) ?>,
                total_pages: numPages
            });
        }
        
        // Render all pages
        for (let pageNum = 1; pageNum <= numPages; pageNum++) {
            renderPage(pdfDoc, pageNum, numPages);
        }
    }).catch(function(error) {
        console.error('Error loading PDF:', error);
        showToast('Error loading PDF. Please refresh the page.', 'danger');
    });
    
    // Render PDF page
    function renderPage(pdfDoc, pageNum, totalPages) {
        pdfDoc.getPage(pageNum).then(function(page) {
            // Create wrapper for this page
            const pageWrapper = document.createElement('div');
            pageWrapper.className = 'pdf-page-wrapper';
            pageWrapper.id = `pdf-page-${pageNum}`;
            
            // Create canvas for this page
            const canvas = document.createElement('canvas');
            canvas.className = 'pdf-page-canvas';
            pageWrapper.appendChild(canvas);
            
            // Add viewed badge (will be shown when page is viewed)
            const badge = document.createElement('div');
            badge.className = 'page-viewed-badge';
            badge.id = `page-badge-${pageNum}`;
            badge.innerHTML = '<i class="fas fa-check"></i>';
            badge.style.display = 'none';
            pageWrapper.appendChild(badge);
            
            pagesContainer.appendChild(pageWrapper);
            
            // Render the page
            const viewport = page.getViewport({ scale: 1.5 });
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            const renderContext = {
                canvasContext: canvas.getContext('2d'),
                viewport: viewport
            };
            
            page.render(renderContext).promise.then(function() {
                console.log('Page', pageNum, 'rendered');
            }).catch(function(error) {
                console.error('Error rendering page', pageNum, ':', error);
            });
            
            // Add intersection observer to detect when page is viewed
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !viewedPages.has(pageNum)) {
                        console.log('Page', pageNum, 'viewed');
                        
                        // Mark page as viewed
                        viewedPages.add(pageNum);
                        document.getElementById(`page-badge-${pageNum}`).style.display = 'flex';
                        
                        // Save to localStorage
                        const viewedPagesArray = Array.from(viewedPages);
                        localStorage.setItem('pdf_viewed_pages_<?= $courseId ?>', JSON.stringify(viewedPagesArray));
                        
                        // Calculate progress (100% required)
                        const progress = Math.round((viewedPages.size / totalPages) * 100);
                        currentPdfProgress = progress;
                        
                        // Update progress bar
                        pdfProgressBar.style.width = progress + '%';
                        pdfProgressPercentage.textContent = progress + '%';
                        viewedPagesCount.textContent = viewedPages.size;
                        
                        console.log('PDF progress:', progress + '%', 'Pages viewed:', viewedPages.size, '/', totalPages);
                        
                        // Send update to server
                        $.post(window.location.href, {
                            pdf_page: pageNum,
                            total_pages: totalPages
                        }, function(response) {
                            console.log('Page view saved:', response);
                        }).fail(function(xhr) {
                            console.error('Failed to save page view:', xhr);
                        });
                        
                        // Check if completed (100%)
                        if (progress >= 100 && !pdfCompleted) {
                            pdfCompleted = true;
                            console.log('PDF completed!');
                            
                            pdfProgressBar.classList.add('completed');
                            
                            // Update UI for completion
                            $('.material-card:has(i.fa-file-pdf) .material-status')
                                .removeClass('status-pending').addClass('status-completed')
                                .html('<i class="fas fa-check-circle me-1"></i>Completed');
                            
                            showToast('✓ PDF completed! You have viewed all ' + totalPages + ' pages.', 'success');
                            
                            $.post(window.location.href, {pdf_completed: 1}, function(response) {
                                console.log('PDF completion saved:', response);
                            }).fail(function(xhr) {
                                console.error('Failed to save PDF completion:', xhr);
                            });
                            
                            localStorage.removeItem('pdf_viewed_pages_<?= $courseId ?>');
                        }
                        
                        // Check overall completion
                        updateButtons();
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(pageWrapper);
            
            // If this page was already viewed, show the badge
            if (viewedPages.has(pageNum)) {
                document.getElementById(`page-badge-${pageNum}`).style.display = 'flex';
            }
        }).catch(function(error) {
            console.error('Error getting page', pageNum, ':', error);
        });
    }
}

// Complete button click with better error handling
$('#completeBtn').on('click', function(){
    let btn = $(this);
    
    // Double-check if materials are completed before sending
    if (!areMaterialsCompleted()) {
        showToast('Please complete all materials first!', 'warning');
        return;
    }
    
    console.log('Completing course...');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Completing...');

    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { mark_completed: 1 },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            console.log('Complete response:', response);
            if (response.success) {
                showToast('✓ ' + response.message, 'success');
                
                // Clear localStorage on completion
                localStorage.removeItem('video_progress_<?= $courseId ?>');
                localStorage.removeItem('pdf_viewed_pages_<?= $courseId ?>');
                
                // Update UI
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                showToast('Error: ' + response.message, 'danger');
                btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Complete Course');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {status: status, error: error, response: xhr.responseText});
            
            let errorMessage = 'Network error. ';
            if (status === 'timeout') {
                errorMessage += 'Request timed out. Please try again.';
            } else if (xhr.status === 0) {
                errorMessage += 'Could not connect to server.';
            } else if (xhr.status === 500) {
                errorMessage += 'Server error. Please try again later.';
            } else {
                errorMessage += 'Please try again.';
            }
            
            showToast(errorMessage, 'danger');
            btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Complete Course');
        }
    });
});

// Initial button state update
setTimeout(updateButtons, 1000);
<?php endif; ?>

// Animation on load
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.material-card, .course-info-card, .action-buttons');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
</body>
</html>