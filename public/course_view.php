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
$assessmentCompleted = false;
$assessmentId = null;

try {
    $stmt = $pdo->prepare("SELECT id FROM assessments WHERE course_id = ? LIMIT 1");
    $stmt->execute([$courseId]);
    $assessmentData = $stmt->fetch();
    $hasAssessment = $assessmentData ? true : false;
    $assessmentId = $assessmentData ? $assessmentData['id'] : null;
    
    // Check if student has completed the assessment using student_attempts table
    if ($hasAssessment && is_student() && $assessmentId) {
        $stmt = $pdo->prepare("
            SELECT id FROM student_attempts 
            WHERE user_id = ? AND assessment_id = ? AND passed = 1
            LIMIT 1
        ");
        $stmt->execute([$u['id'], $assessmentId]);
        $assessmentCompleted = $stmt->fetch() ? true : false;
    }
} catch (Exception $e) {
    // If tables don't exist, just continue without assessment
    $hasAssessment = false;
    $assessmentCompleted = false;
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

// Handle completion
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

// Check assessment completion if exists
if ($hasAssessment && !$assessmentCompleted) {
    $requirements_met = false;
    $missing_requirements[] = 'Complete the assessment';
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

// Get student info for email
$studentName = trim($u['fname'] . ' ' . $u['lname']);
if (empty($studentName)) $studentName = $u['username'];

// SEND COMPLETION EMAIL
$subject = "Course Completed: " . $course['title'];
$message = "
<html>
<head>
<title>Course Completion Certificate</title>
<style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
.content { background: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px; }
.certificate { text-align: center; margin: 20px 0; }
.certificate i { font-size: 48px; color: #28a745; }
.details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
.footer { text-align: center; margin-top: 20px; color: #666; }
</style>
</head>
<body>
<div class='container'>
<div class='header'>
<h2>Congratulations, $studentName! 🎓</h2>
</div>
<div class='content'>
<div class='certificate'>
<i class='fas fa-certificate'></i>
</div>
<p>You have successfully completed the course:</p>
<div class='details'>
<h3>" . $course['title'] . "</h3>
<p>Completion Date: " . date('F d, Y h:i A') . "</p>
</div>
<p>This certificate confirms your dedication to learning and professional development.</p>
<p>Course Details:</p>
<ul>
" . ($hasVideo ? "<li>✓ Video lesson completed</li>" : "") . "
" . ($hasPdf ? "<li>✓ PDF material completed (all pages viewed)</li>" : "") . "
" . ($hasAssessment ? "<li>✓ Assessment completed</li>" : "") . "
<li>✓ Course requirements fulfilled</li>
</ul>
</div>
<div class='footer'>
<p>This is an automated certificate from your Learning Management System.</p>
</div>
</div>
</body>
</html>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: noreply@lms.com" . "\r\n";

$emailSent = mail($u['email'], $subject, $message, $headers);

$pdo->commit();

echo json_encode([
'success' => true,
'email_sent' => $emailSent,
'message' => $emailSent ? 'Course completed! Certificate has been sent to your email.' : 'Course completed! (Email notification failed)'
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

// FETCH ENROLLED STUDENTS - FOR ADMIN/PROPONENT
// Build SELECT query dynamically based on existing columns
$select_fields = [
    'u.id', 'u.fname', 'u.lname', 'u.email', 'u.username',
    'e.status', 'e.progress', 'e.total_time_seconds', 'e.enrolled_at', 'e.completed_at',
    'DATE_FORMAT(e.enrolled_at, "%M %d, %Y") as enrolled_date',
    'DATE_FORMAT(e.completed_at, "%M %d, %Y") as completed_date',
    'CASE WHEN e.status = "completed" THEN "bg-success" WHEN e.status = "ongoing" THEN "bg-warning" ELSE "bg-secondary" END as status_color',
    'CASE WHEN e.status = "completed" THEN "Completed" WHEN e.status = "ongoing" THEN "Ongoing" ELSE "Not Started" END as status_text'
];

if ($has_video_tracking) {
    $select_fields[] = 'e.video_progress';
    $select_fields[] = 'e.video_completed';
}

if ($has_pdf_tracking) {
    $select_fields[] = 'e.pdf_progress';
    $select_fields[] = 'e.pdf_completed';
    $select_fields[] = 'e.pdf_current_page';
    $select_fields[] = 'e.pdf_total_pages';
}

$select_sql = implode(', ', $select_fields);

$stmt = $pdo->prepare("
    SELECT $select_sql
    FROM enrollments e
    JOIN users u ON e.user_id = u.id 
    WHERE e.course_id = ?
    ORDER BY 
        CASE e.status 
            WHEN 'ongoing' THEN 1 
            WHEN 'completed' THEN 2 
            ELSE 3 
        END,
        e.enrolled_at DESC
");
$stmt->execute([$courseId]);
$enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total_enrolled,
        SUM(CASE WHEN status = "ongoing" THEN 1 ELSE 0 END) as ongoing_count,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count
    FROM enrollments 
    WHERE course_id = ?
');
$stmt->execute([$courseId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Completion rate
$completionRate = 0;
if ($stats['total_enrolled'] > 0) {
    $completionRate = round(($stats['completed_count'] / $stats['total_enrolled']) * 100);
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv' && (is_admin() || is_proponent())) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="enrolled_students_course_' . $courseId . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    $headers = ['Student Name', 'Email', 'Username', 'Status', 'Enrolled Date', 'Completed Date', 
                'Progress (%)', 'Time Spent (mins)'];
    
    if ($has_video_tracking) {
        $headers[] = 'Video Progress (%)';
        $headers[] = 'Video Completed';
    }
    
    if ($has_pdf_tracking) {
        $headers[] = 'PDF Progress (%)';
        $headers[] = 'PDF Pages Read';
        $headers[] = 'Total PDF Pages';
        $headers[] = 'PDF Completed';
    }
    
    fputcsv($output, $headers);
    
    foreach ($enrolledStudents as $student) {
        $timeMinutes = round($student['total_time_seconds'] / 60, 1);
        
        $row = [
            $student['fname'] . ' ' . $student['lname'],
            $student['email'],
            $student['username'],
            $student['status_text'],
            $student['enrolled_date'],
            $student['completed_date'] ?? 'N/A',
            $student['progress'] . '%',
            $timeMinutes
        ];
        
        if ($has_video_tracking) {
            $row[] = ($student['video_progress'] ?? 0) . '%';
            $row[] = ($student['video_completed'] ?? 0) ? 'Yes' : 'No';
        }
        
        if ($has_pdf_tracking) {
            $row[] = ($student['pdf_progress'] ?? 0) . '%';
            $row[] = ($student['pdf_current_page'] ?? 0);
            $row[] = ($student['pdf_total_pages'] ?? 0);
            $row[] = ($student['pdf_completed'] ?? 0) ? 'Yes' : 'No';
        }
        
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Show database update warning to admin
if (is_admin() || is_superadmin()) {
    $missing_columns = [];
    foreach ($all_columns as $col) {
        if (!in_array($col, $existing_columns)) {
            $missing_columns[] = $col;
        }
    }
    
    if (!empty($missing_columns)) {
        echo '<div class="alert alert-warning m-4">
            <h5><i class="fas fa-database"></i> Database Update Recommended</h5>
            <p>For better course progress tracking, please add these columns to the enrollments table:</p>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
ALTER TABLE `enrollments` 
ADD COLUMN `video_progress` INT DEFAULT 0 AFTER `progress`,
ADD COLUMN `pdf_progress` INT DEFAULT 0 AFTER `video_progress`,
ADD COLUMN `video_completed` TINYINT DEFAULT 0 AFTER `pdf_progress`,
ADD COLUMN `pdf_completed` TINYINT DEFAULT 0 AFTER `video_completed`,
ADD COLUMN `pdf_current_page` INT DEFAULT 0 AFTER `pdf_completed`,
ADD COLUMN `pdf_total_pages` INT DEFAULT 0 AFTER `pdf_current_page`;</pre>
            <p>After running this SQL, refresh the page for full functionality.</p>
        </div>';
    }
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
/* Toast notification styles */
.toast-notification {
position: fixed;
top: 20px;
right: 20px;
z-index: 9999;
animation: slideIn 0.3s ease;
}

@keyframes slideIn {
from {
transform: translateX(100%);
opacity: 0; 
}
to {
transform: translateX(0);
opacity: 1;
}
}

/* Material card */
.material-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

/* Video player container - fixed height */
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

/* Custom PDF viewer */
.pdf-viewer-container {
    position: relative;
    width: 100%;
    height: 700px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: auto;
    background: #f8f9fa;
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
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px;
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

.page-viewed-badge i {
    font-size: 14px;
}

/* Progress bar under PDF */
.pdf-progress-container {
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fa;
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
    background: linear-gradient(90deg, #007bff, #00d4ff);
    transition: width 0.3s ease;
}

.pdf-progress-fill.completed {
    background: linear-gradient(90deg, #28a745, #20c997);
}

/* Action buttons - centered */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
    }
}

/* Student table enhancements */
.student-progress-cell {
    min-width: 150px;
}

.progress-detail {
    font-size: 11px;
    color: #6c757d;
    margin-top: 2px;
}

.stats-summary .badge {
    font-size: 12px;
    padding: 5px 10px;
}

.completion-rate {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
}
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

<!-- Export Button for Admin/Proponent -->
<?php if((is_admin() || is_proponent()) && count($enrolledStudents) > 0): ?>
<a href="?id=<?= $courseId ?>&export=csv" class="export-btn">
<i class="fas fa-download me-2"></i>Export CSV
</a>
<?php endif; ?>
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
<div class="material-card">
<div class="material-header">
<div class="material-title">
<i class="fas fa-file-pdf text-danger"></i>
<h5 class="mb-0">Course PDF Material</h5>
</div>
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

<p class="mt-3">
<a class="btn btn-outline-primary"
href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
target="_blank">
<i class="fas fa-external-link-alt me-2"></i>Open PDF in new tab
</a>
</p>
</div>
<?php endif; ?>

<!-- Action Buttons for Students -->
<?php if(is_student()): ?>
<div class="action-buttons">
    <!-- Take Assessment Button -->
    <?php if($hasAssessment && $assessmentId): ?>
        <a href="take_assessment.php?assessment_id=<?= $assessmentId ?>" 
           id="takeAssessmentBtn" 
           class="btn btn-primary action-btn <?= ((($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                                                  ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : '') ?>"
           <?= ((($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                 ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : '') ?>>
            <i class="fas fa-pencil-alt"></i> Take Assessment
        </a>
    <?php endif; ?>
    
    <!-- Complete Course Button -->
    <button id="completeBtn" 
            class="btn btn-success action-btn <?= ((($hasAssessment && !$assessmentCompleted) || 
                                                    ($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                                                    ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : 'btn-pulse') ?>"
            <?= ((($hasAssessment && !$assessmentCompleted) || 
                  ($hasVideo && $has_video_tracking && ($enrollment['video_completed'] ?? 0) != 1) || 
                  ($hasPdf && $has_pdf_tracking && ($enrollment['pdf_completed'] ?? 0) != 1)) ? 'disabled' : '') ?>>
        <i class="fas fa-check-circle"></i> Complete Course
    </button>
</div>
<?php endif; ?>

<!-- Students List for Admin/Proponent -->
<?php if((is_admin() || is_proponent()) && count($enrolledStudents) > 0): ?>
<div class="students-section mt-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5><i class="fas fa-users me-2"></i>Enrolled Students</h5>

<!-- Stats Summary -->
<div class="stats-summary">
<span class="badge bg-primary me-2">Total: <?= $stats['total_enrolled'] ?? 0 ?></span>
<span class="badge bg-warning me-2">Ongoing: <?= $stats['ongoing_count'] ?? 0 ?></span>
<span class="badge bg-success">Completed: <?= $stats['completed_count'] ?? 0 ?></span>
</div>
</div>

<!-- Completion Rate -->
<div class="completion-rate mb-3">
<div class="d-flex align-items-center">
<span class="me-2">Completion Rate:</span>
<div class="progress flex-grow-1" style="height: 10px;">
<div class="progress-bar bg-success" style="width: <?= $completionRate ?>%"></div>
</div>
<span class="ms-2 fw-bold"><?= $completionRate ?>%</span>
</div>
</div>

<!-- Students Table -->
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Student</th>
<th>Email</th>
<th>Status</th>
<th>Progress</th>
<?php if($has_video_tracking): ?>
    <th>Video</th>
<?php endif; ?>
<?php if($has_pdf_tracking): ?>
    <th>PDF</th>
    <th>PDF Pages</th>
<?php endif; ?>
<th>Enrolled</th>
<th>Completed</th>
<th>Time Spent</th>
</tr>
</thead>
<tbody>
<?php foreach($enrolledStudents as $student): ?>
<tr>
<td>
<?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>
<br><small class="text-muted">@<?= htmlspecialchars($student['username']) ?></small>
</td>
<td><?= htmlspecialchars($student['email']) ?></td>
<td>
<span class="badge <?= $student['status_color'] ?>">
<?= $student['status_text'] ?>
</span>
</td>
<td class="student-progress-cell">
<div class="d-flex align-items-center">
<div class="progress flex-grow-1 me-2" style="height: 5px;">
<div class="progress-bar bg-success" style="width: <?= $student['progress'] ?>%"></div>
</div>
<small><?= $student['progress'] ?>%</small>
</div>
</td>
<?php if($has_video_tracking): ?>
<td>
<?php if($hasVideo): ?>
    <?php if($student['video_completed'] ?? 0): ?>
        <span class="badge bg-success"><i class="fas fa-check"></i> Done</span>
    <?php else: ?>
        <span class="badge bg-warning"><?= ($student['video_progress'] ?? 0) ?>%</span>
    <?php endif; ?>
<?php else: ?>
    <span class="text-muted">—</span>
<?php endif; ?>
</td>
<?php endif; ?>
<?php if($has_pdf_tracking): ?>
<td>
<?php if($hasPdf): ?>
    <?php if($student['pdf_completed'] ?? 0): ?>
        <span class="badge bg-success"><i class="fas fa-check"></i> Done</span>
    <?php else: ?>
        <span class="badge bg-warning"><?= ($student['pdf_progress'] ?? 0) ?>%</span>
    <?php endif; ?>
<?php else: ?>
    <span class="text-muted">—</span>
<?php endif; ?>
</td>
<td>
<?php if($hasPdf && $has_pdf_tracking): ?>
    <?= ($student['pdf_current_page'] ?? 0) ?> / <?= ($student['pdf_total_pages'] ?? 0) ?>
<?php else: ?>
    <span class="text-muted">—</span>
<?php endif; ?>
</td>
<?php endif; ?>
<td><?= $student['enrolled_date'] ?></td>
<td><?= $student['completed_date'] ?? '—' ?></td>
<td><?= round($student['total_time_seconds'] / 60, 1) ?> mins</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
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
const hasAssessment = <?= $hasAssessment ? 'true' : 'false' ?>;
let assessmentCompleted = <?= $assessmentCompleted ? 'true' : 'false' ?>;
let assessmentId = <?= $assessmentId ?: 'null' ?>;

// Track viewed pages
let viewedPages = new Set();
let currentPdfProgress = <?= ($enrollment['pdf_progress'] ?? 0) ?>;
let pdfCompleted = <?= ($enrollment['pdf_completed'] ?? 0) ?>;

// Load viewed pages from localStorage on page load
document.addEventListener('DOMContentLoaded', function() {
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
                }
            }
        } catch (e) {
            console.error('Error loading saved pages:', e);
        }
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
    
    // Load saved video progress from localStorage
    const savedVideoProgress = localStorage.getItem('video_progress_<?= $courseId ?>');
    if (savedVideoProgress && !videoCompleted) {
        const savedTime = parseInt(savedVideoProgress);
        if (savedTime > 0) {
            player.ready(function() {
                this.currentTime(savedTime);
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
            }
            
            // Check if video is complete (98% or more to account for rounding)
            if (videoProgress >= 98 && !videoCompleted) {
                videoCompleted = true;
                $.post(window.location.href, {video_completed: 1});
                localStorage.removeItem('video_progress_<?= $courseId ?>');
                
                // Update UI for completion
                $('.material-card:has(i.fa-video) .material-status')
                    .removeClass('status-pending').addClass('status-completed')
                    .html('<i class="fas fa-check-circle me-1"></i>Completed');
                
                showToast('✓ Video completed!', 'success');
                checkRequirements();
            }
        }
    });
    
    player.on('ended', function() {
        if (!videoCompleted) {
            videoCompleted = true;
            videoProgress = 100;
            $.post(window.location.href, {video_progress: 100, video_completed: 1});
            localStorage.removeItem('video_progress_<?= $courseId ?>');
            
            // Update UI
            $('.material-card:has(i.fa-video) .material-status')
                .removeClass('status-pending').addClass('status-completed')
                .html('<i class="fas fa-check-circle me-1"></i>Completed');
            
            showToast('✓ Video completed!', 'success');
            checkRequirements();
        }
    });
}

// PDF Page Tracking
if (hasPdf && hasPdfTracking) {
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
    
    // Load PDF
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc) {
        const numPages = pdfDoc.numPages;
        totalPagesSpan.textContent = numPages;
        
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
            
            page.render(renderContext);
            
            // Add intersection observer to detect when page is viewed
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !viewedPages.has(pageNum)) {
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
                        
                        // Check if completed (100%)
                        if (progress >= 100 && !pdfCompleted) {
                            pdfCompleted = true;
                            pdfProgressBar.classList.add('completed');
                            
                            // Update UI for completion
                            $('.material-card:has(i.fa-file-pdf) .material-status')
                                .removeClass('status-pending').addClass('status-completed')
                                .html('<i class="fas fa-check-circle me-1"></i>Completed');
                            
                            showToast('✓ PDF completed! You have viewed all ' + totalPages + ' pages.', 'success');
                            
                            $.post(window.location.href, {pdf_completed: 1});
                            localStorage.removeItem('pdf_viewed_pages_<?= $courseId ?>');
                        }
                        
                        // Send update to server
                        $.post(window.location.href, {
                            pdf_page: pageNum,
                            total_pages: totalPages
                        });
                        
                        // Check overall requirements
                        checkRequirements();
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(pageWrapper);
            
            // If this page was already viewed, show the badge
            if (viewedPages.has(pageNum)) {
                document.getElementById(`page-badge-${pageNum}`).style.display = 'flex';
            }
        });
    }
}

// Check if all requirements are met for buttons
function checkRequirements() {
    let materialsComplete = true;
    
    if (hasVideo && hasVideoTracking && !videoCompleted) {
        materialsComplete = false;
    }
    
    if (hasPdf && hasPdfTracking && !pdfCompleted) {
        materialsComplete = false;
    }
    
    // Update Take Assessment button
    const takeAssessmentBtn = document.getElementById('takeAssessmentBtn');
    if (takeAssessmentBtn) {
        if (materialsComplete) {
            takeAssessmentBtn.classList.remove('disabled');
            takeAssessmentBtn.removeAttribute('disabled');
        } else {
            takeAssessmentBtn.classList.add('disabled');
            takeAssessmentBtn.setAttribute('disabled', 'disabled');
        }
    }
    
    // Update Complete Course button
    const completeBtn = document.getElementById('completeBtn');
    if (completeBtn) {
        const canComplete = materialsComplete && (!hasAssessment || assessmentCompleted);
        
        if (canComplete) {
            completeBtn.classList.remove('disabled');
            completeBtn.classList.add('btn-pulse');
            completeBtn.removeAttribute('disabled');
        } else {
            completeBtn.classList.add('disabled');
            completeBtn.classList.remove('btn-pulse');
            completeBtn.setAttribute('disabled', 'disabled');
        }
    }
}

// Complete button click
$('#completeBtn').on('click', function(){
let btn = $(this);
btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Completing...');

$.post(window.location.href, { mark_completed: 1 }, function(response) {
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
}, 'json').fail(function() {
showToast('Network error. Please try again.', 'danger');
btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Complete Course');
});
});

// Initial check for requirements
setTimeout(checkRequirements, 1000);
<?php endif; ?>

// Animation on load
document.addEventListener('DOMContentLoaded', function() {
const cards = document.querySelectorAll('.material-card, .course-info-card, .students-section, .action-buttons');
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