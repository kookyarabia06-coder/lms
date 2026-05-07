<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$courseId = intval($_GET['id'] ?? 0);
if (!$courseId) die('Invalid course ID');

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) die('Course not found');

/**
 * Calculate and update overall progress for an enrollment
 * Progress = (Completed Materials / Total Available Materials) × 100
 */
function updateOverallProgress($pdo, $enrollmentId, $courseId) {
    // Get course materials
    $stmt = $pdo->prepare("SELECT file_pdf, file_video FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    // Get current enrollment status
    $stmt = $pdo->prepare("SELECT pdf_completed, video_completed FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();
    
    $totalMaterials = 0;
    $completedMaterials = 0;
    
    // Check PDF
    if ($course['file_pdf']) {
        $totalMaterials++;
        if ($enrollment['pdf_completed'] == 1) {
            $completedMaterials++;
        }
    }
    
    // Check Video
    if ($course['file_video']) {
        $totalMaterials++;
        if ($enrollment['video_completed'] == 1) {
            $completedMaterials++;
        }
    }
    
    // Calculate progress percentage
    $progress = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100, 2) : 0;
    
    // Update the overall progress in enrollments table
    $stmt = $pdo->prepare("UPDATE enrollments SET progress = ? WHERE id = ?");
    $stmt->execute([$progress, $enrollmentId]);
    
    return $progress;
}

/**
 * Calculate detailed incremental progress with dynamic weighting
 */
function calculateDetailedProgress($pdo, $enrollmentId, $courseId) {
    $stmt = $pdo->prepare("SELECT file_pdf, file_video FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT e.pdf_completed, e.video_completed, e.pdf_total_pages,
               (SELECT COUNT(*) FROM pdf_progress WHERE enrollment_id = e.id) as pages_viewed,
               (SELECT video_position FROM video_progress WHERE enrollment_id = e.id LIMIT 1) as video_position
        FROM enrollments e 
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollmentId]);
    $enrollment = $stmt->fetch();
    
    // Determine available materials
    $hasPdf = !empty($course['file_pdf']);
    $hasVideo = !empty($course['file_video']);
    
    // If no materials, return 0
    if (!$hasPdf && !$hasVideo) {
        return [
            'progress' => 0,
            'pdf_pages_viewed' => 0,
            'pdf_total_pages' => 0,
            'video_position' => 0,
            'pdf_completed' => 0,
            'video_completed' => 0
        ];
    }
    
    // Dynamic weighting based on available materials
    $totalWeight = 0;
    $earnedWeight = 0;
    
    // If only one material type exists, it gets 100% weight
    // If both exist, each gets 50% weight
    if ($hasPdf && $hasVideo) {
        $pdfWeight = 50;
        $videoWeight = 50;
    } elseif ($hasPdf) {
        $pdfWeight = 100;
        $videoWeight = 0;
    } else {
        $pdfWeight = 0;
        $videoWeight = 100;
    }
    
    $totalWeight = $pdfWeight + $videoWeight;
    
    // Calculate PDF progress
    if ($hasPdf) {
        if ($enrollment['pdf_completed']) {
            $earnedWeight += $pdfWeight;
        } elseif ($enrollment['pdf_total_pages'] > 0) {
            $pdfProgress = ($enrollment['pages_viewed'] / $enrollment['pdf_total_pages']) * $pdfWeight;
            $earnedWeight += $pdfProgress;
        }
    }
    
    // Calculate Video progress
    if ($hasVideo) {
        if ($enrollment['video_completed']) {
            $earnedWeight += $videoWeight;
        } elseif ($enrollment['video_position'] > 0) {
            // Get video duration from file or estimate
            $videoPath = __DIR__ . '/../uploads/video/' . $course['file_video'];
            $duration = 300; // Default 5 minutes if can't determine
            
            // Try to get actual duration using ffprobe if available
            if (file_exists($videoPath)) {
                $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath) . " 2>&1";
                $output = @shell_exec($cmd);
                if ($output && is_numeric(trim($output))) {
                    $duration = intval(trim($output));
                }
            }
            
            $videoProgress = min(($enrollment['video_position'] / $duration) * $videoWeight, $videoWeight);
            $earnedWeight += $videoProgress;
        }
    }
    
    $progress = $totalWeight > 0 ? round($earnedWeight) : 0;
    
    // Update the progress in database
    $stmt = $pdo->prepare("UPDATE enrollments SET progress = ? WHERE id = ?");
    $stmt->execute([$progress, $enrollmentId]);
    
    return [
        'progress' => $progress,
        'pdf_pages_viewed' => $enrollment['pages_viewed'] ?? 0,
        'pdf_total_pages' => $enrollment['pdf_total_pages'] ?? 0,
        'video_position' => $enrollment['video_position'] ?? 0,
        'pdf_completed' => $enrollment['pdf_completed'] ?? 0,
        'video_completed' => $enrollment['video_completed'] ?? 0
    ];
}

// Only students need enrollment tracking
$enrollment = null;
$pdfProgress = [];
$videoProgress = null;

if (is_student()) {
    // Check if course is available
    $today = date('Y-m-d');
    if ($course['is_active'] == 0 || ($course['expires_at'] && $today > $course['expires_at'])) {
        die('<div class="alert alert-danger m-4"><h5>Course Unavailable</h5><p>This course has expired or is no longer active.</p></div>');
    }

    // Check enrollment
    $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$u['id'], $courseId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        // Auto-create enrollment
        $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_id, enrolled_at, status) VALUES (?, ?, NOW(), "ongoing")');
        $stmt->execute([$u['id'], $courseId]);
        $enrollmentId = $pdo->lastInsertId();
        
        $enrollment = [
            'id' => $enrollmentId,
            'pdf_completed' => 0,
            'video_completed' => 0,
            'pdf_total_pages' => 0,
            'pdf_current_page' => 0,
            'status' => 'ongoing'
        ];
    } else {
        $enrollmentId = $enrollment['id'];
    }

    // Fetch PDF progress
    if ($course['file_pdf']) {
        $stmt = $pdo->prepare('SELECT page_number FROM pdf_progress WHERE enrollment_id = ? ORDER BY page_number');
        $stmt->execute([$enrollmentId]);
        $pdfProgress = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fetch or create video progress
    if ($course['file_video']) {
        $stmt = $pdo->prepare('SELECT * FROM video_progress WHERE enrollment_id = ?');
        $stmt->execute([$enrollmentId]);
        $videoProgress = $stmt->fetch();

        if (!$videoProgress) {
            $stmt = $pdo->prepare('INSERT INTO video_progress (enrollment_id) VALUES (?)');
            $stmt->execute([$enrollmentId]);
            
            $videoProgress = [
                'id' => $pdo->lastInsertId(),
                'video_position' => 0,
                'completed' => 0
            ];
        }
    }
}

// Handle AJAX PDF page tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_page']) && is_student()) {
    $page = intval($_POST['pdf_page']);
    
    try {
        $pdo->beginTransaction();

        // Get current enrollment with lock
        $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE id = ? FOR UPDATE');
        $stmt->execute([$enrollment['id']]);
        $currentEnrollment = $stmt->fetch();

        // If this is first page view, get total pages
        if ($currentEnrollment['pdf_total_pages'] == 0 && isset($_POST['total_pages'])) {
            $totalPages = intval($_POST['total_pages']);
            $stmt = $pdo->prepare('UPDATE enrollments SET pdf_total_pages = ? WHERE id = ?');
            $stmt->execute([$totalPages, $enrollment['id']]);
        }

        // Record page view
        $stmt = $pdo->prepare('INSERT IGNORE INTO pdf_progress (enrollment_id, page_number, viewed_at) VALUES (?, ?, NOW())');
        $stmt->execute([$enrollment['id'], $page]);

        // Update current page
        $stmt = $pdo->prepare('UPDATE enrollments SET pdf_current_page = ? WHERE id = ?');
        $stmt->execute([$page, $enrollment['id']]);

        // Count viewed pages
        $stmt = $pdo->prepare('SELECT COUNT(*) as viewed FROM pdf_progress WHERE enrollment_id = ?');
        $stmt->execute([$enrollment['id']]);
        $viewedCount = $stmt->fetchColumn();

        // Get total pages
        $totalPages = $currentEnrollment['pdf_total_pages'] > 0 ? $currentEnrollment['pdf_total_pages'] : ($_POST['total_pages'] ?? 0);
        
        // Check if PDF is completed
        $pdfCompleted = ($totalPages > 0 && $viewedCount >= $totalPages) ? 1 : 0;
        
        if ($pdfCompleted != $currentEnrollment['pdf_completed']) {
            $stmt = $pdo->prepare('UPDATE enrollments SET pdf_completed = ? WHERE id = ?');
            $stmt->execute([$pdfCompleted, $enrollment['id']]);
        }

        // Calculate and update detailed progress
        $progressData = calculateDetailedProgress($pdo, $enrollment['id'], $courseId);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'viewed_count' => $viewedCount,
            'total_pages' => $totalPages,
            'pdf_completed' => $pdfCompleted,
            'progress' => $progressData['progress']
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("PDF Tracking Error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle AJAX video progress tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['video_position']) && is_student()) {
    $position = intval($_POST['video_position']);
    $duration = intval($_POST['duration'] ?? 0);
    $completed = intval($_POST['completed'] ?? 0);
    
    try {
        $pdo->beginTransaction();

        // Update video progress
        $stmt = $pdo->prepare('UPDATE video_progress SET video_position = ?, completed = ?, last_watched = NOW() WHERE enrollment_id = ?');
        $stmt->execute([$position, $completed, $enrollment['id']]);

        // Update enrollment video_completed flag
        if ($completed == 1) {
            $stmt = $pdo->prepare('UPDATE enrollments SET video_completed = 1 WHERE id = ?');
            $stmt->execute([$enrollment['id']]);
        }

        // Calculate and update detailed progress
        $progressData = calculateDetailedProgress($pdo, $enrollment['id'], $courseId);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'video_completed' => $completed,
            'progress' => $progressData['progress']
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Video Tracking Error: " . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle AJAX progress fetch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_progress']) && is_student()) {
    $progressData = calculateDetailedProgress($pdo, $enrollment['id'], $courseId);
    echo json_encode($progressData);
    exit;
}

// Handle course completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_course']) && is_student()) {
    try {
        $pdo->beginTransaction();
        
        // Check if user has passed the assessment
        $stmt = $pdo->prepare("
            SELECT a.id FROM assessments a
            JOIN assessment_attempts att ON att.assessment_id = a.id
            WHERE a.course_id = ? AND att.user_id = ? AND att.passed = 1
            LIMIT 1
        ");
        $stmt->execute([$courseId, $u['id']]);
        $passed = $stmt->fetch();
        
        if (!$passed) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'You must pass the assessment first']);
            exit;
        }
        
        // Update enrollment
        $stmt = $pdo->prepare("
            UPDATE enrollments 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$enrollment['id'], $u['id']]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Course Completion Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error completing course']);
    }
    exit;
}

// Check if assessment exists and if user has passed it
$assessment = null;
$hasPassedAssessment = false;
if (is_student()) {
    $stmt = $pdo->prepare("SELECT id, title, passing_score FROM assessments WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();
    
    // Check if user has passed the assessment
    if ($assessment) {
        $stmt = $pdo->prepare("
            SELECT passed FROM assessment_attempts 
            WHERE assessment_id = ? AND user_id = ? AND passed = 1 
            LIMIT 1
        ");
        $stmt->execute([$assessment['id'], $u['id']]);
        $hasPassedAssessment = $stmt->fetch() ? true : false;
    }
}

// Validate file existence
$pdfPath = __DIR__ . '/../uploads/pdf/' . $course['file_pdf'];
$pdfExists = $course['file_pdf'] && file_exists($pdfPath);

$videoPath = __DIR__ . '/../uploads/video/' . $course['file_video'];
$videoExists = $course['file_video'] && file_exists($videoPath);

// Calculate if assessment can be taken (both materials completed)
$canTakeAssessment = false;
if (is_student() && $enrollment) {
    $pdfDone = !$course['file_pdf'] || ($enrollment['pdf_completed'] ?? 0) == 1;
    $videoDone = !$course['file_video'] || ($enrollment['video_completed'] ?? 0) == 1;
    $canTakeAssessment = $pdfDone && $videoDone;
}

// Get detailed progress for display
if (is_student()) {
    $progressData = calculateDetailedProgress($pdo, $enrollment['id'], $courseId);
    $overallProgress = $progressData['progress'];
    $pdfPagesViewed = $progressData['pdf_pages_viewed'];
    $pdfTotalPages = $progressData['pdf_total_pages'];
    $videoPosition = $progressData['video_position'];
} else {
    $overallProgress = 0;
    $pdfPagesViewed = 0;
    $pdfTotalPages = 0;
    $videoPosition = 0;
}

// Determine material availability for JavaScript
$hasPdf = !empty($course['file_pdf']);
$hasVideo = !empty($course['file_video']);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($course['title']) ?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.1);
            --border-color: #dee2e6;
            --bg-light: #f8f9fa;
            --text-muted: #6c757d;
        }

        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        .main-content-wrapper {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }

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
            transition: all 0.3s ease;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

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

        .pdf-progress-container {
            margin-top: 15px;
            padding: 10px;
            background: var(--bg-light);
            border-radius: 8px;
        }

        .pdf-progress-text {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .pdf-progress-text span:last-child {
            font-weight: 600;
            color: #667eea;
        }

        .pdf-progress-bar {
            height: 10px;
            background: linear-gradient(90deg, #e9ecef 0%, #dee2e6 100%);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }

        .pdf-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }

        .pdf-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .assessment-section {
            background: linear-gradient(135deg, #fff3cd 0%, #fff8e7 100%);
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }

        .assessment-btn {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
        }
        
        .assessment-btn:not(.disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .assessment-btn.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
            background: #6c757d;
            color: white;
        }

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
            background: #1a1a1a !important;
            overflow: hidden !important;
        }

        .pdf-fullscreen-mode .lms-sidebar-container,
        .pdf-fullscreen-mode .course-header,
        .pdf-fullscreen-mode .course-info-card,
        .pdf-fullscreen-mode .video-player-container,
        .pdf-fullscreen-mode .pdf-progress-container,
        .pdf-fullscreen-mode .assessment-section {
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
            background: rgba(30,30,30,0.95) !important;
            backdrop-filter: blur(10px) !important;
            padding: 12px 20px !important;
            margin: 0 !important;
            border-bottom: 1px solid rgba(255,255,255,0.1) !important;
            color: white !important;
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

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(10px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
        }

        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: #000;
            color: white;
            padding: 8px;
            z-index: 10000;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Material progress mini bar */
        .material-progress-mini {
            width: 100px;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            display: inline-block;
            margin-left: 10px;
        }

        .material-progress-mini-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s ease;
        }

        /* ADDED: Custom Confirm Modal Styles */
        .custom-confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .custom-confirm-modal.active {
            display: flex;
        }

        .custom-confirm-content {
            background: white;
            border-radius: 12px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .custom-confirm-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-confirm-header i {
            font-size: 22px;
            color: #28a745;
        }

        .custom-confirm-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #111827;
        }

        .custom-confirm-body {
            padding: 20px 24px;
        }

        .custom-confirm-body p {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .custom-confirm-body .course-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            border-left: 3px solid #28a745;
        }

        .custom-confirm-body .course-info strong {
            display: block;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .custom-confirm-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .custom-confirm-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .custom-confirm-footer .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .custom-confirm-footer .btn-cancel:hover {
            background: #e5e7eb;
        }

        .custom-confirm-footer .btn-confirm {
            background: #28a745;
            color: white;
        }

        .custom-confirm-footer .btn-confirm:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <a href="#mainContent" class="skip-link">Skip to main content</a>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div id="toastContainer" class="toast-notification"></div>
    <div id="fullscreenHint" class="fullscreen-hint" style="display: none;">
        <i class="fas fa-keyboard"></i> Press ESC to exit full screen
    </div>

    <!-- ADDED: Custom Confirm Modal -->
    <div class="custom-confirm-modal" id="completeCourseModal">
        <div class="custom-confirm-content">
            <div class="custom-confirm-header">
                <i class="fas fa-graduation-cap"></i>
                <h3>Complete Course</h3>
            </div>
            <div class="custom-confirm-body">
                <p>Are you sure you want to mark this course as completed?</p>
                <div class="course-info">
                    <strong id="confirmCourseTitle"><?= htmlspecialchars($course['title']) ?></strong>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Once marked as completed, you will receive a certificate of completion.
                </small>
            </div>
            <div class="custom-confirm-footer">
                <button class="btn-cancel" id="cancelCompleteBtn">Cancel</button>
                <button class="btn-confirm" id="confirmCompleteBtn">Yes, Complete Course</button>
            </div>
        </div>
    </div>

    <div class="main-content-wrapper" id="mainContent" tabindex="-1">
        <!-- Course Header -->
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-book-open text-primary" aria-hidden="true"></i>
                    <h5 class="mb-0"><?= htmlspecialchars($course['title']) ?></h5>
                </div>
            </div>
            <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($course['description'])) ?></p>
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="fas fa-chalkboard-teacher text-primary" aria-hidden="true"></i>
                    </div>
                    <span class="text-muted">
                        <strong>Instructor:</strong> <?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Assessment Section -->
        <?php if (is_student() && $assessment): ?>
        <div class="material-card assessment-section" id="assessmentCard">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-15 p-3">
                        <i class="fas fa-file-alt text-warning fa-2x" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">Course Assessment</h4>
                        <p class="text-muted mb-0"><?= htmlspecialchars($assessment['title']) ?></p>
                        <?php if ($assessment['passing_score']): ?>
                            <small class="text-muted"><i class="fas fa-star me-1"></i>Passing Score: <?= $assessment['passing_score'] ?>%</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-end">
                    <?php if ($canTakeAssessment): ?>
                        <a href="../public/take_assessment.php?assessment_id=<?= $assessment['id'] ?>" class="btn btn-warning assessment-btn" id="takeAssessmentBtn">
                            <i class="fas fa-play-circle me-2"></i>Take Assessment Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary assessment-btn disabled" id="takeAssessmentBtn" disabled>
                            <i class="fas fa-lock me-2"></i>Complete Course Materials First
                        </button>
                        <div class="text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Complete all PDF and video materials to enable assessment
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress Section -->
        <?php if (is_student()): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-chart-line text-primary" aria-hidden="true"></i>
                    <h5 class="mb-0">Your Progress</h5>
                </div>
            </div>

            <div class="row mb-3">
                <?php if ($course['file_pdf']): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-danger me-2"></i>
                        <span>PDF Material: 
                            <strong>
                                <span id="pdfStatusText">
                                    <?php if (($enrollment['pdf_completed'] ?? 0) == 1): ?>
                                        Completed (100%)
                                    <?php else: ?>
                                        In Progress (<?= $pdfTotalPages > 0 ? round(($pdfPagesViewed / $pdfTotalPages) * 100) : 0 ?>%)
                                    <?php endif; ?>
                                </span>
                            </strong>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($course['file_video']): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-video text-primary me-2"></i>
                        <span>Video Material: 
                            <strong>
                                <span id="videoStatusText">
                                    <?php if (($enrollment['video_completed'] ?? 0) == 1): ?>
                                        Completed (100%)
                                    <?php else: ?>
                                        In Progress
                                    <?php endif; ?>
                                </span>
                            </strong>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="pdf-progress-container">
                <div class="pdf-progress-text">
                    <span>Overall Course Progress</span>
                    <span id="progressPercent"><?= $overallProgress ?>%</span>
                </div>
                <div class="pdf-progress-bar">
                    <div class="pdf-progress-fill" id="progressBar" style="width: <?= $overallProgress ?>%;"></div>
                </div>
            </div>

            <!-- Complete Course Button - MODIFIED: Removed confirm() -->
            <?php if ($enrollment['status'] !== 'completed'): ?>
            <div class="mt-4">
                <?php if ($hasPassedAssessment): ?>
                    <button id="completeCourseBtn" class="btn btn-success w-100 py-3 fw-bold">
                        <i class="fas fa-graduation-cap me-2"></i>Complete Course
                    </button>
                    <small class="text-muted d-block mt-2 text-center">
                        <i class="fas fa-info-circle me-1"></i>
                        Mark this course as completed. You've already passed the assessment.
                    </small>
                <?php else: ?>
                    <?php if ($assessment): ?>
                        <button class="btn btn-secondary w-100 py-3 fw-bold" disabled>
                            <i class="fas fa-lock me-2"></i>Pass Assessment to Complete
                        </button>
                        <small class="text-muted d-block mt-2 text-center">
                            <i class="fas fa-info-circle me-1"></i>
                            You need to pass the assessment to complete this course.
                        </small>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 py-3 fw-bold" disabled>
                            <i class="fas fa-lock me-2"></i>No Assessment Available
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- PDF Content -->
        <?php if ($course['file_pdf']): ?>
        <div class="material-card" id="pdfMaterialCard">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-file-pdf text-danger"></i>
                    <h5 class="mb-0">Course PDF Material</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (is_student()): ?>
                    <span class="material-status <?= ($enrollment['pdf_completed'] ?? 0) == 1 ? 'status-completed' : 'status-pending' ?>" id="pdfStatusBadge">
                        <?php if (($enrollment['pdf_completed'] ?? 0) == 1): ?>
                            <i class="fas fa-check-circle me-1"></i>Completed (100%)
                        <?php else: ?>
                            <i class="fas fa-clock me-1"></i>In Progress (<?= $pdfTotalPages > 0 ? round(($pdfPagesViewed / $pdfTotalPages) * 100) : 0 ?>%)
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" id="fullscreenPdfBtn" title="Toggle fullscreen mode (ESC to exit)">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
            </div>

            <?php if ($pdfExists): ?>
                <div class="pdf-viewer-container" id="pdfViewerContainer">
                    <div id="pdfPagesContainer" class="pdf-pages-container"></div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    PDF file is temporarily unavailable. Please contact support.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Video Content -->
        <?php if ($course['file_video']): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-video text-primary"></i>
                    <h5 class="mb-0">Course Video</h5>
                </div>
                <?php if (is_student()): ?>
                <span class="material-status <?= ($enrollment['video_completed'] ?? 0) == 1 ? 'status-completed' : 'status-pending' ?>" id="videoStatusBadge">
                    <?php if (($enrollment['video_completed'] ?? 0) == 1): ?>
                        <i class="fas fa-check-circle me-1"></i>Completed (100%)
                    <?php else: ?>
                        <i class="fas fa-clock me-1"></i>In Progress
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="video-player-container">
                <?php if ($videoExists): ?>
                    <video-js id="courseVideo"
                             class="video-js vjs-default-skin"
                             controls
                             preload="auto"
                             width="100%"
                             height="100%"
                             data-setup='{"fluid": false}'>
                        <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars(basename($course['file_video'])) ?>" type="video/mp4">
                    </video-js>
                <?php else: ?>
                    <div class="alert alert-danger m-3">
                        Video file is temporarily unavailable. Please contact support.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

        <?php if (is_student()): ?>
        // ADDED: Custom Confirm Modal for Course Completion
        const completeModal = document.getElementById('completeCourseModal');
        const confirmCompleteBtn = document.getElementById('confirmCompleteBtn');
        const cancelCompleteBtn = document.getElementById('cancelCompleteBtn');
        let pendingCompleteCallback = null;

        function closeCompleteModal() {
            completeModal.classList.remove('active');
            pendingCompleteCallback = null;
        }

        function showCompleteModal(callback) {
            completeModal.classList.add('active');
            pendingCompleteCallback = callback;
        }

        if (confirmCompleteBtn) {
            confirmCompleteBtn.onclick = function() {
                if (pendingCompleteCallback) pendingCompleteCallback();
                closeCompleteModal();
            };
        }

        if (cancelCompleteBtn) {
            cancelCompleteBtn.onclick = closeCompleteModal;
        }

        completeModal.addEventListener('click', function(e) {
            if (e.target === completeModal) closeCompleteModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && completeModal.classList.contains('active')) closeCompleteModal();
        });

        // Course data with dynamic material availability
        const courseData = {
            id: <?= $courseId ?>,
            hasPdf: <?= $hasPdf ? 'true' : 'false' ?>,
            hasVideo: <?= $hasVideo ? 'true' : 'false' ?>,
            pdfCompleted: <?= ($enrollment['pdf_completed'] ?? 0) ?>,
            videoCompleted: <?= ($enrollment['video_completed'] ?? 0) ?>,
            pdfTotalPages: <?= ($enrollment['pdf_total_pages'] ?? 0) ?>,
            pdfPagesViewed: <?= $pdfPagesViewed ?>,
            videoPosition: <?= $videoPosition ?>,
            hasAssessment: <?= $assessment ? 'true' : 'false' ?>,
            overallProgress: <?= $overallProgress ?>
        };

        // Calculate weights dynamically based on available materials
        const weights = (function() {
            if (courseData.hasPdf && courseData.hasVideo) {
                return { pdf: 50, video: 50 };
            } else if (courseData.hasPdf) {
                return { pdf: 100, video: 0 };
            } else if (courseData.hasVideo) {
                return { pdf: 0, video: 100 };
            }
            return { pdf: 0, video: 0 };
        })();

        // State management
        let state = {
            pdfDoc: null,
            totalPages: courseData.pdfTotalPages,
            pagesViewed: courseData.pdfPagesViewed,
            viewedSet: new Set(<?= json_encode($pdfProgress) ?>),
            serverConfirmed: new Set(<?= json_encode($pdfProgress) ?>),
            pdfCompleted: courseData.pdfCompleted,
            videoCompleted: courseData.videoCompleted,
            videoCurrentTime: courseData.videoPosition,
            videoDuration: 0,
            overallProgress: courseData.overallProgress,
            isFullscreen: false,
            currentPage: 1,
            pageCache: {},
            pageViewTimers: {}
        };

        // DOM Elements
        const elements = {
            pagesContainer: document.getElementById('pdfPagesContainer'),
            fullscreenBtn: document.getElementById('fullscreenPdfBtn'),
            pdfCard: document.getElementById('pdfMaterialCard'),
            fullscreenHint: document.getElementById('fullscreenHint'),
            progressPercent: document.getElementById('progressPercent'),
            progressBar: document.getElementById('progressBar'),
            pdfStatusBadge: document.getElementById('pdfStatusBadge'),
            pdfStatusText: document.getElementById('pdfStatusText'),
            videoStatusBadge: document.getElementById('videoStatusBadge'),
            videoStatusText: document.getElementById('videoStatusText'),
            takeAssessmentBtn: document.getElementById('takeAssessmentBtn'),
            completeCourseBtn: document.getElementById('completeCourseBtn')
        };

        // Calculate and update progress incrementally with dynamic weights
        function calculateIncrementalProgress() {
            let totalWeight = weights.pdf + weights.video;
            let earnedWeight = 0;
            
            // Calculate PDF progress
            if (courseData.hasPdf) {
                if (state.pdfCompleted) {
                    earnedWeight += weights.pdf;
                } else if (state.totalPages > 0) {
                    const pdfProgress = (state.pagesViewed / state.totalPages) * weights.pdf;
                    earnedWeight += pdfProgress;
                }
            }
            
            // Calculate Video progress
            if (courseData.hasVideo) {
                if (state.videoCompleted) {
                    earnedWeight += weights.video;
                } else if (state.videoDuration > 0 && state.videoCurrentTime > 0) {
                    const videoProgress = Math.min((state.videoCurrentTime / state.videoDuration) * weights.video, weights.video);
                    earnedWeight += videoProgress;
                }
            }
            
            // Calculate final percentage
            const newProgress = totalWeight > 0 ? Math.round(earnedWeight) : 0;
            
            // Only update if progress changed
            if (newProgress !== state.overallProgress) {
                state.overallProgress = newProgress;
                updateProgressDisplay();
                updatePageTitle();
                return true;
            }
            
            return false;
        }

        // Update progress display
        function updateProgressDisplay() {
            if (elements.progressPercent) {
                elements.progressPercent.textContent = state.overallProgress + '%';
            }
            if (elements.progressBar) {
                elements.progressBar.style.width = state.overallProgress + '%';
            }
            
            // Update PDF badge with detailed progress
            if (courseData.hasPdf && elements.pdfStatusBadge) {
                if (state.pdfCompleted) {
                    elements.pdfStatusBadge.className = 'material-status status-completed';
                    elements.pdfStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed (100%)';
                    if (elements.pdfStatusText) elements.pdfStatusText.textContent = 'Completed (100%)';
                } else {
                    const pdfPercent = state.totalPages > 0 ? Math.round((state.pagesViewed / state.totalPages) * 100) : 0;
                    elements.pdfStatusBadge.className = 'material-status status-pending';
                    elements.pdfStatusBadge.innerHTML = `<i class="fas fa-clock me-1"></i>In Progress (${pdfPercent}%)`;
                    if (elements.pdfStatusText) {
                        if (state.totalPages > 0) {
                            elements.pdfStatusText.textContent = `In Progress (${state.pagesViewed}/${state.totalPages} pages)`;
                        } else {
                            elements.pdfStatusText.textContent = `In Progress (${pdfPercent}%)`;
                        }
                    }
                }
            }
            
            // Update Video badge with detailed progress
            if (courseData.hasVideo && elements.videoStatusBadge) {
                if (state.videoCompleted) {
                    elements.videoStatusBadge.className = 'material-status status-completed';
                    elements.videoStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed (100%)';
                    if (elements.videoStatusText) elements.videoStatusText.textContent = 'Completed (100%)';
                } else {
                    const videoPercent = state.videoDuration > 0 ? Math.round((state.videoCurrentTime / state.videoDuration) * 100) : 0;
                    elements.videoStatusBadge.className = 'material-status status-pending';
                    elements.videoStatusBadge.innerHTML = `<i class="fas fa-clock me-1"></i>In Progress (${videoPercent}%)`;
                    if (elements.videoStatusText) {
                        const minutes = Math.floor(state.videoCurrentTime / 60);
                        const seconds = Math.floor(state.videoCurrentTime % 60);
                        elements.videoStatusText.textContent = `In Progress (${minutes}:${seconds.toString().padStart(2, '0')} watched)`;
                    }
                }
            }
            
            // Update assessment button
            updateAssessmentButton();
        }

        // Update page title with progress
        function updatePageTitle() {
            if (state.overallProgress > 0) {
                document.title = `[${state.overallProgress}%] <?= htmlspecialchars(addslashes($course['title'])) ?> - LMS`;
            }
        }

        // Track PDF page view with incremental progress
        function trackPageView(pageNum) {
            if (state.serverConfirmed.has(pageNum) || state.pdfCompleted) return;

            if (!state.viewedSet.has(pageNum)) {
                state.viewedSet.add(pageNum);
                state.pagesViewed = state.viewedSet.size;
                
                // Update progress immediately
                calculateIncrementalProgress();

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        pdf_page: pageNum,
                        total_pages: state.totalPages
                    },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        if (response.success) {
                            state.serverConfirmed.add(pageNum);
                            
                            if (response.pdf_completed) {
                                state.pdfCompleted = true;
                            }
                            
                            if (response.progress !== undefined) {
                                state.overallProgress = response.progress;
                                updateProgressDisplay();
                                updatePageTitle();
                            }
                        }
                    },
                    error: function() {
                        state.viewedSet.delete(pageNum);
                        state.pagesViewed = state.viewedSet.size;
                        calculateIncrementalProgress();
                    }
                });
            }
        }

        // Fetch progress from server
        function fetchProgressFromServer() {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { get_progress: 1 },
                dataType: 'json',
                success: function(data) {
                    state.overallProgress = data.progress;
                    state.pagesViewed = data.pdf_pages_viewed;
                    state.pdfCompleted = data.pdf_completed;
                    state.videoCompleted = data.video_completed;
                    updateProgressDisplay();
                    updatePageTitle();
                }
            });
        }

        // Update assessment button state
        function updateAssessmentButton() {
            if (!elements.takeAssessmentBtn || !courseData.hasAssessment) return;
            
            const canTake = state.overallProgress >= 100;
            
            if (canTake) {
                const assessmentId = <?= $assessment ? $assessment['id'] : 0 ?>;
                const container = elements.takeAssessmentBtn.parentNode;
                container.innerHTML = `
                    <a href="take_assessment.php?assessment_id=${assessmentId}" 
                       class="btn btn-warning assessment-btn" 
                       id="takeAssessmentBtn">
                        <i class="fas fa-play-circle me-2"></i>Take Assessment Now
                    </a>
                `;
                elements.takeAssessmentBtn = document.getElementById('takeAssessmentBtn');
            }
        }

        // Complete Course Button - MODIFIED: Uses custom modal instead of confirm()
        if (elements.completeCourseBtn) {
            elements.completeCourseBtn.addEventListener('click', function() {
                if (state.overallProgress < 100) {
                    showToast(`Complete all materials first! Progress: ${state.overallProgress}%`, 'warning');
                    return;
                }
                
                // Show custom modal instead of confirm()
                showCompleteModal(function() {
                    const btn = $(elements.completeCourseBtn);
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Completing...');
                    
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { complete_course: 1 },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast('Course completed successfully! 🎓', 'success');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                btn.prop('disabled', false).html('<i class="fas fa-graduation-cap me-2"></i>Complete Course');
                                showToast(response.message || 'Error completing course', 'danger');
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false).html('<i class="fas fa-graduation-cap me-2"></i>Complete Course');
                            showToast('Error completing course. Please try again.', 'danger');
                        }
                    });
                });
            });
        }

        // PDF Viewer
        if (elements.pagesContainer && <?= $pdfExists ? 'true' : 'false' ?>) {
            pdfjsLib.getDocument('<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars(basename($course['file_pdf'])) ?>').promise
                .then(function(pdf) {
                    state.pdfDoc = pdf;
                    state.totalPages = pdf.numPages;
                    
                    if (courseData.pdfTotalPages === 0) {
                        // Will be saved when first page is viewed
                    }
                    
                    elements.pagesContainer.innerHTML = '';

                    for (let num = 1; num <= Math.min(3, pdf.numPages); num++) {
                        renderPage(num);
                    }

                    setTimeout(() => {
                        for (let num = 4; num <= pdf.numPages; num++) {
                            renderPage(num);
                        }
                    }, 500);

                    setTimeout(checkVisiblePages, 1000);
                })
                .catch(function(error) {
                    console.error('Error loading PDF:', error);
                    elements.pagesContainer.innerHTML = '<div class="alert alert-danger m-3">Error loading PDF. Please try again.</div>';
                });

            function renderPage(num) {
                if (state.pageCache[num]) {
                    const pageWrapper = state.pageCache[num].cloneNode(true);
                    elements.pagesContainer.appendChild(pageWrapper);
                    return;
                }

                state.pdfDoc.getPage(num).then(function(page) {
                    const container = document.getElementById('pdfViewerContainer');
                    const containerWidth = container ? container.clientWidth - 60 : 800;
                    const viewport = page.getViewport({ scale: 1 });
                    const scale = containerWidth / viewport.width;
                    const scaledViewport = page.getViewport({ scale: scale });

                    const pageWrapper = document.createElement('div');
                    pageWrapper.className = 'pdf-page-wrapper';
                    pageWrapper.id = `pdf-page-${num}`;

                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-page-canvas';
                    canvas.height = scaledViewport.height;
                    canvas.width = scaledViewport.width;
                    pageWrapper.appendChild(canvas);

                    if (state.viewedSet.has(num)) {
                        const badge = document.createElement('div');
                        badge.className = 'page-viewed-badge';
                        badge.id = `page-badge-${num}`;
                        badge.innerHTML = '<i class="fas fa-check"></i>';
                        pageWrapper.appendChild(badge);
                    }

                    elements.pagesContainer.appendChild(pageWrapper);

                    page.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport: scaledViewport
                    });

                    state.pageCache[num] = pageWrapper.cloneNode(true);

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting && !state.viewedSet.has(num) && !state.pdfCompleted) {
                                if (!state.pageViewTimers[num]) {
                                    state.pageViewTimers[num] = setTimeout(() => {
                                        trackPageView(num);

                                        if (!document.getElementById(`page-badge-${num}`)) {
                                            const badge = document.createElement('div');
                                            badge.className = 'page-viewed-badge';
                                            badge.id = `page-badge-${num}`;
                                            badge.innerHTML = '<i class="fas fa-check"></i>';
                                            pageWrapper.appendChild(badge);
                                        }

                                        delete state.pageViewTimers[num];
                                    }, 3000);
                                }
                            } else if (!entry.isIntersecting && state.pageViewTimers[num]) {
                                clearTimeout(state.pageViewTimers[num]);
                                delete state.pageViewTimers[num];
                            }
                        });
                    }, { threshold: 0.5 });

                    observer.observe(pageWrapper);
                });
            }

            function checkVisiblePages() {
                if (state.pdfCompleted || !elements.pagesContainer) return;

                const container = document.getElementById('pdfViewerContainer');
                if (!container) return;

                const containerRect = container.getBoundingClientRect();

                for (let num = 1; num <= state.totalPages; num++) {
                    const pageElement = document.getElementById(`pdf-page-${num}`);
                    if (!pageElement) continue;

                    const pageRect = pageElement.getBoundingClientRect();
                    const isVisible = (
                        pageRect.top < containerRect.bottom &&
                        pageRect.bottom > containerRect.top
                    );

                    if (isVisible && !state.viewedSet.has(num) && !state.serverConfirmed.has(num) && !state.pdfCompleted) {
                        if (!state.pageViewTimers[num]) {
                            state.pageViewTimers[num] = setTimeout(() => {
                                trackPageView(num);

                                if (!document.getElementById(`page-badge-${num}`)) {
                                    const badge = document.createElement('div');
                                    badge.className = 'page-viewed-badge';
                                    badge.id = `page-badge-${num}`;
                                    badge.innerHTML = '<i class="fas fa-check"></i>';
                                    pageElement.appendChild(badge);
                                }

                                delete state.pageViewTimers[num];
                            }, 3000);
                        }
                    } else if (!isVisible && state.pageViewTimers[num]) {
                        clearTimeout(state.pageViewTimers[num]);
                        delete state.pageViewTimers[num];
                    } else if (isVisible) {
                        state.currentPage = num;
                    }
                }
            }

            let scrollTimeout;
            document.getElementById('pdfViewerContainer')?.addEventListener('scroll', function() {
                if (!scrollTimeout) {
                    scrollTimeout = setTimeout(function() {
                        checkVisiblePages();
                        scrollTimeout = null;
                    }, 200);
                }
            });
        }

        // Video Player with incremental progress
        <?php if ($course['file_video'] && $videoExists): ?>
        videojs('courseVideo').ready(function() {
            const player = this;
            let completionReported = state.videoCompleted;
            
            player.one('loadedmetadata', function() {
                state.videoDuration = Math.floor(player.duration());
                
                if (state.videoCurrentTime > 0 && !state.videoCompleted) {
                    player.currentTime(state.videoCurrentTime);
                }
                
                calculateIncrementalProgress();
            });

            player.on('timeupdate', function() {
                const position = Math.floor(player.currentTime());
                state.videoCurrentTime = position;
                
                calculateIncrementalProgress();
                
                if (completionReported || state.videoCompleted) return;
                
                const duration = Math.floor(player.duration());
                if (duration === 0) return;
                
                const percent = (position / duration) * 100;
                
                if (percent >= 95 && !completionReported) {
                    completionReported = true;
                    
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            video_position: position,
                            duration: duration,
                            completed: 1
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                state.videoCompleted = true;
                                if (response.progress !== undefined) {
                                    state.overallProgress = response.progress;
                                    updateProgressDisplay();
                                    updatePageTitle();
                                }
                            }
                        }
                    });
                } else {
                    if (position % 10 === 0) {
                        $.ajax({
                            url: window.location.href,
                            method: 'POST',
                            data: {
                                video_position: position,
                                duration: duration,
                                completed: 0
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.progress !== undefined) {
                                    state.overallProgress = response.progress;
                                    updateProgressDisplay();
                                    updatePageTitle();
                                }
                            }
                        });
                    }
                }
            });

            player.on('ended', function() {
                if (!completionReported && !state.videoCompleted) {
                    completionReported = true;
                    
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            video_position: player.duration(),
                            duration: player.duration(),
                            completed: 1
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                state.videoCompleted = true;
                                if (response.progress !== undefined) {
                                    state.overallProgress = response.progress;
                                    updateProgressDisplay();
                                    updatePageTitle();
                                }
                            }
                        }
                    });
                }
            });
            
            window.addEventListener('beforeunload', function() {
                if (player && !state.videoCompleted) {
                    const position = Math.floor(player.currentTime());
                    const duration = Math.floor(player.duration());
                    
                    const data = new FormData();
                    data.append('video_position', position);
                    data.append('duration', duration);
                    data.append('completed', 0);
                    
                    navigator.sendBeacon(window.location.href, data);
                }
            });
        });
        <?php endif; ?>

        // Fullscreen Toggle
        if (elements.fullscreenBtn && elements.pdfCard) {
            elements.fullscreenBtn.addEventListener('click', function() {
                state.isFullscreen = !state.isFullscreen;

                if (state.isFullscreen) {
                    elements.pdfCard.classList.add('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'block';

                    setTimeout(() => {
                        if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                    }, 3000);
                } else {
                    elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                    elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                    if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
                }

                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && state.isFullscreen) {
                state.isFullscreen = false;
                if (elements.pdfCard) elements.pdfCard.classList.remove('pdf-fullscreen-mode');
                if (elements.fullscreenBtn) elements.fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                if (elements.fullscreenHint) elements.fullscreenHint.style.display = 'none';
            }

            if (state.isFullscreen && courseData.hasPdf) {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (state.currentPage < state.totalPages) {
                        document.getElementById(`pdf-page-${state.currentPage + 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (state.currentPage > 1) {
                        document.getElementById(`pdf-page-${state.currentPage - 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.getElementById('toastContainer');
            container.innerHTML = '';
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Initialize progress display
        updateProgressDisplay();
        updatePageTitle();

        // Periodic progress sync
        setInterval(() => {
            if (!state.pdfCompleted || !state.videoCompleted) {
                fetchProgressFromServer();
            }
        }, 30000);
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>