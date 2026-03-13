<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$courseId = intval($_GET['id'] ?? 0);
if(!$courseId) die('Invalid course ID');

// Fetch course
$stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if(!$course) die('Course not found');

// Fetch enrollment if student
$enrollment = null;
$pdfProgress = [];

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

    // Check enrollment
    $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id=? AND course_id=?');
    $stmt->execute([$u['id'], $courseId]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        // Auto-create enrollment ONLY if course is valid
        $stmt = $pdo->prepare('
            INSERT INTO enrollments 
            (user_id, course_id, enrolled_at, status, progress) 
            VALUES (?, ?, NOW(), "ongoing", 0)
        ');
        $stmt->execute([$u['id'], $courseId]);

        $enrollmentId = $pdo->lastInsertId();
        $enrollment = [
            'id' => $enrollmentId,
            'progress' => 0,
            'status' => 'ongoing'
        ];
    } else {
        $enrollmentId = $enrollment['id'];
    }

    // Fetch PDF progress if any
    $stmt = $pdo->prepare('SELECT * FROM pdf_progress WHERE enrollment_id = ? ORDER BY page_number');
    $stmt->execute([$enrollmentId]);
    $pdfProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX PDF page tracking
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pdf_page']) && is_student()) {
    $page = intval($_POST['pdf_page']);
    $totalPages = intval($_POST['total_pages'] ?? 1);
    
    // Check if this page has been recorded
    $stmt = $pdo->prepare('SELECT id FROM pdf_progress WHERE enrollment_id = ? AND page_number = ?');
    $stmt->execute([$enrollment['id'], $page]);
    
    if (!$stmt->fetch()) {
        // Record new page view
        $stmt = $pdo->prepare('INSERT INTO pdf_progress (enrollment_id, page_number, viewed_at) VALUES (?, ?, NOW())');
        $stmt->execute([$enrollment['id'], $page]);
        
        // Calculate new progress percentage
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT page_number) as pages_viewed FROM pdf_progress WHERE enrollment_id = ?');
        $stmt->execute([$enrollment['id']]);
        $pagesViewed = $stmt->fetchColumn();
        
        $progressPercent = min(100, round(($pagesViewed / $totalPages) * 100));
        
        // Update enrollment progress and pages_viewed
        $stmt = $pdo->prepare('UPDATE enrollments SET progress = ?, pages_viewed = ? WHERE id = ?');
        $stmt->execute([$progressPercent, $pagesViewed, $enrollment['id']]);
        
        echo json_encode([
            'success' => true,
            'pages_viewed' => $pagesViewed,
            'total_pages' => $totalPages,
            'progress' => $progressPercent
        ]);
    } else {
        echo json_encode(['success' => true, 'already_viewed' => true]);
    }
    exit;
}

// Handle completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed']) && is_student()) {

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get total pages
        $totalPages = intval($_POST['total_pages'] ?? 1);
        
        // Get pages viewed
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT page_number) as pages_viewed FROM pdf_progress WHERE enrollment_id = ?');
        $stmt->execute([$enrollment['id']]);
        $pagesViewed = $stmt->fetchColumn();
        
        // Calculate progress
        $progressPercent = round(($pagesViewed / $totalPages) * 100);
        
        // Update enrollment status and progress
        $stmt = $pdo->prepare("
            UPDATE enrollments 
            SET status = 'completed', completed_at = NOW(), progress = ?, pages_viewed = ? 
            WHERE id = ?
        ");
        $stmt->execute([$progressPercent, $pagesViewed, $enrollment['id']]);

        // Get student info for email
        $studentName = trim($u['fname'] . ' ' . $u['lname']);
        if (empty($studentName)) $studentName = $u['username'];

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'pages_viewed' => $pagesViewed,
            'total_pages' => $totalPages,
            'progress' => $progressPercent
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

// Get total pages for PDF from the course table
$totalPdfPages = $course['total_pages'] ?? 0;

// Check if assessment exists for this course
$assessment = null;
$completedAssessment = null;
if (is_student()) {
    $stmt = $pdo->prepare("SELECT id, title FROM assessments WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();
    
    if ($assessment) {
        $stmt = $pdo->prepare("
            SELECT id, status, score, completed_at FROM assessment_attempts 
            WHERE assessment_id = ? AND user_id = ? AND status = 'completed'
            ORDER BY completed_at DESC LIMIT 1
        ");
        $stmt->execute([$assessment['id'], $u['id']]);
        $completedAssessment = $stmt->fetch();
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
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/profile.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/manager.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
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

    <!-- Fullscreen hint -->
    <div id="fullscreenHint" class="fullscreen-hint" style="display: none;">
        <i class="fas fa-keyboard"></i> Press ESC to exit full screen
    </div>

    <!-- Main Content -->
    <div class="main-content-wrapper" id="mainContent">
        <!-- Course Header -->
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-book-open text-primary"></i>
                    <h5 class="mb-0"><?=htmlspecialchars($course['title'])?></h5>
                </div>
            </div>
            <p class="text-muted mb-0"><?=nl2br(htmlspecialchars($course['description']))?></p>
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-2">
                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                    </div>
                    <span class="text-muted">
                        <strong>Instructor:</strong> <?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Progress Section for Students -->
        <?php if(is_student()): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-chart-line text-primary"></i>
                    <h5 class="mb-0">Your Progress</h5>
                </div>
                <span class="material-status <?= (($enrollment['status'] ?? '') === 'completed') ? 'status-completed' : 'status-pending' ?>">
                    <?php if(($enrollment['status'] ?? '') === 'completed'): ?>
                        <i class="fas fa-check-circle me-1"></i>Completed
                    <?php else: ?>
                        <i class="fas fa-spinner me-1"></i>Ongoing
                    <?php endif; ?>
                </span>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-muted me-2"></i>
                        <span>Pages viewed: <strong><span id="pagesViewed"><?= count($pdfProgress) ?></span>/<span id="totalPages"><?= $totalPdfPages ?></span></strong></span>
                    </div>
                </div>
            </div>

            <div class="pdf-progress-container">
                <div class="pdf-progress-text">
                    <span>Course Progress</span>
                    <span id="progressPercent"><?= intval($enrollment['progress'] ?? 0) ?>%</span>
                </div>
                <div class="pdf-progress-bar">
                    <div class="pdf-progress-fill <?= (($enrollment['status'] ?? '') === 'completed') ? 'completed' : '' ?>" 
                         id="progressBar" 
                         style="width: <?= intval($enrollment['progress'] ?? 0) ?>%;"></div>
                </div>
            </div>

            <!-- Complete Button -->
            <?php if(($enrollment['status'] ?? '') !== 'completed'): ?>
                <button id="completeBtn" class="btn btn-success action-btn w-100 mt-3" disabled>
                    <i class="fas fa-check-circle me-2"></i>Mark as Complete
                </button>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    View all pages of the PDF to enable completion
                </small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Assessment Section -->
        <?php if(is_student() && $assessment): ?>
        <div id="assessmentContainer" style="<?= (($enrollment['status'] ?? '') === 'completed') ? '' : 'display: none;' ?>">
            <div class="material-card" style="border-left: 4px solid #ffc107;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="material-title">
                        <i class="fas fa-file-alt text-warning"></i>
                        <div>
                            <h5 class="mb-1">Course Assessment</h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($assessment['title']) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($completedAssessment): ?>
                        <div class="d-flex align-items-center gap-3">
                            <span class="material-status status-completed">
                                <i class="fas fa-check-circle me-1"></i>Completed
                            </span>
                            <span class="fw-bold">Score: <?= $completedAssessment['score'] ?>%</span>
                            <a href="assessment_result.php?attempt_id=<?= $completedAssessment['id'] ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Result
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="take_assessment.php?id=<?= $assessment['id'] ?>" class="btn btn-warning" id="takeAssessmentBtn">
                            <i class="fas fa-play-circle me-2"></i>Take Assessment
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PDF Content -->
        <?php if($course['file_pdf']): ?>
        <div class="material-card" id="pdfMaterialCard">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-file-pdf text-danger"></i>
                    <h5 class="mb-0">Course PDF Material</h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if(is_student()): ?>
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
                    <button class="btn btn-outline-primary" id="fullscreenPdfBtn" title="Fullscreen (ESC to exit)">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
            </div>

            <?php if(is_student()): ?>
                <!-- PDF Viewer with Page Tracking -->
                <div class="pdf-viewer-container" id="pdfViewerContainer">
                    <div id="pdfPagesContainer" class="pdf-pages-container">
                        <!-- PDF pages will be rendered here -->
                    </div>
                </div>

                <!-- Progress bar under PDF -->
                <div class="pdf-progress-container mt-3">
                    <div class="pdf-progress-text">
                        <span><i class="fas fa-file-pdf me-1"></i>PDF Progress</span>
                        <span id="pdfProgressPercentage"><?= ($enrollment['pdf_progress'] ?? 0) ?>%</span>
                    </div>
                    <div class="pdf-progress-bar">
                        <div id="pdfProgressBar" class="pdf-progress-fill <?= (($enrollment['pdf_completed'] ?? 0) == 1) ? 'completed' : '' ?>" 
                             style="width: <?= ($enrollment['pdf_progress'] ?? 0) ?>%;"></div>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="fas fa-info-circle"></i>
                        Pages viewed: <span id="viewedPagesCount"><?= ($enrollment['pdf_current_page'] ?? 0) ?></span> / <span id="totalPagesCount"><?= ($enrollment['pdf_total_pages'] ?? 0) ?></span>
                        (100% required to complete)
                    </div>
                </div>
            <?php else: ?>
                <!-- Simple iframe for non-students -->
                <div class="pdf-viewer-container">
                    <iframe
                        src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                        width="100%"
                        height="100%"
                        style="border: none; border-radius: 8px;">
                    </iframe>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Video Content -->
        <?php if($course['file_video']): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-title">
                    <i class="fas fa-video text-primary"></i>
                    <h5 class="mb-0">Course Video</h5>
                </div>
                <?php if(is_student()): ?>
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
                </video-js>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    <?php if(is_student()): ?>
    // ===== PDF VIEWER WITH INSTANT PAGE TRACKING =====
    let pdfDoc = null;
    let totalPages = <?= $totalPdfPages ?>;
    let pagesViewed = <?= count($pdfProgress) ?>;
    let viewedPages = <?= json_encode(array_column($pdfProgress, 'page_number')) ?>;
    let completeBtn = document.getElementById('completeBtn');
    let pagesContainer = document.getElementById('pdfPagesContainer');
    let pageViewedConfirmed = {};
    let isCompleted = <?= ($enrollment['status'] ?? '') === 'completed' ? 'true' : 'false' ?>;
    let isFullscreen = false;
    let pdfCompleted = <?= ($enrollment['pdf_completed'] ?? 0) ?>;
    const mainContent = document.getElementById('mainContent');
    const fullscreenBtn = document.getElementById('fullscreenPdfBtn');
    const assessmentContainer = document.getElementById('assessmentContainer');
    const fullscreenHint = document.getElementById('fullscreenHint');
    
    // Initialize viewed pages
    let viewedArray = viewedPages || [];
    let viewedSet = new Set(viewedArray);
    let serverConfirmed = new Set(viewedArray);
    
    // Update UI function
    function updateUI() {
        let progress = Math.min(100, Math.round((pagesViewed / totalPages) * 100));
        document.getElementById('pagesViewed').textContent = pagesViewed;
        document.getElementById('progressPercent').textContent = progress + '%';
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('pdfProgressPercentage').textContent = progress + '%';
        document.getElementById('pdfProgressBar').style.width = progress + '%';
        document.getElementById('viewedPagesCount').textContent = pagesViewed;
        
        // Check completion
        if (!isCompleted && completeBtn) {
            completeBtn.disabled = pagesViewed < totalPages;
        }
    }
    
    // Initial UI update
    updateUI();
    
    // Load PDF
    if (pagesContainer) {
        pdfjsLib.getDocument('<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>').promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('totalPages').textContent = pdf.numPages;
            document.getElementById('totalPagesCount').textContent = pdf.numPages;
            totalPages = pdf.numPages;
            updateUI();
            
            pagesContainer.innerHTML = '';
            
            for (let num = 1; num <= pdf.numPages; num++) {
                renderPage(num);
            }
            
            setTimeout(checkVisiblePages, 500);
        });
    }
    
    // Render PDF page
    function renderPage(num) {
        pdfDoc.getPage(num).then(function(page) {
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
            
            if (viewedSet.has(num)) {
                const badge = document.createElement('div');
                badge.className = 'page-viewed-badge';
                badge.id = `page-badge-${num}`;
                badge.innerHTML = '<i class="fas fa-check"></i>';
                pageWrapper.appendChild(badge);
            }
            
            pagesContainer.appendChild(pageWrapper);
            
            page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: scaledViewport
            });
            
            // Add intersection observer
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !viewedSet.has(num) && !isCompleted) {
                        trackPageView(num);
                        
                        if (!document.getElementById(`page-badge-${num}`)) {
                            const badge = document.createElement('div');
                            badge.className = 'page-viewed-badge';
                            badge.id = `page-badge-${num}`;
                            badge.innerHTML = '<i class="fas fa-check"></i>';
                            pageWrapper.appendChild(badge);
                        }
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(pageWrapper);
        });
    }
    
    // Check visible pages on scroll
    function checkVisiblePages() {
        if (isCompleted || !pagesContainer) return;
        
        const container = document.getElementById('pdfViewerContainer');
        if (!container) return;
        
        const containerRect = container.getBoundingClientRect();
        
        for (let num = 1; num <= totalPages; num++) {
            const pageElement = document.getElementById(`pdf-page-${num}`);
            if (!pageElement) continue;
            
            const pageRect = pageElement.getBoundingClientRect();
            const isVisible = (
                pageRect.top < containerRect.bottom &&
                pageRect.bottom > containerRect.top
            );
            
            if (isVisible && !viewedSet.has(num) && !serverConfirmed.has(num) && !isCompleted) {
                trackPageView(num);
                
                if (!document.getElementById(`page-badge-${num}`)) {
                    const badge = document.createElement('div');
                    badge.className = 'page-viewed-badge';
                    badge.id = `page-badge-${num}`;
                    badge.innerHTML = '<i class="fas fa-check"></i>';
                    pageElement.appendChild(badge);
                }
            }
        }
    }
    
    // Add scroll listener
    document.getElementById('pdfViewerContainer')?.addEventListener('scroll', checkVisiblePages);
    
    // Track page view
    function trackPageView(pageNum) {
        if (serverConfirmed.has(pageNum) || isCompleted) return;
        
        if (!viewedSet.has(pageNum)) {
            viewedSet.add(pageNum);
            pagesViewed = viewedSet.size;
            
            let newProgress = Math.min(100, Math.round((pagesViewed / totalPages) * 100));
            
            // Update UI
            document.getElementById('pagesViewed').textContent = pagesViewed;
            document.getElementById('viewedPagesCount').textContent = pagesViewed;
            document.getElementById('progressPercent').textContent = newProgress + '%';
            document.getElementById('pdfProgressPercentage').textContent = newProgress + '%';
            document.getElementById('progressBar').style.width = newProgress + '%';
            document.getElementById('pdfProgressBar').style.width = newProgress + '%';
            
            if (!isCompleted && completeBtn) {
                completeBtn.disabled = pagesViewed < totalPages;
            }
            
            // Check if PDF completed
            if (newProgress >= 100 && !pdfCompleted) {
                pdfCompleted = true;
                document.getElementById('pdfProgressBar').classList.add('completed');
                
                // Update status badge
                const pdfStatusBadge = document.querySelector('.material-card:has(i.fa-file-pdf) .material-status');
                if (pdfStatusBadge) {
                    pdfStatusBadge.className = 'material-status status-completed';
                    pdfStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed';
                }
            }
        }
        
        // Send to server
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                pdf_page: pageNum,
                total_pages: totalPages
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    serverConfirmed.add(pageNum);
                }
            }
        });
    }

    // Complete button click
    if (completeBtn) {
        $('#completeBtn').on('click', function(){
            let btn = $(this);
            
            isCompleted = true;
            
            // Update UI
            document.getElementById('pagesViewed').textContent = totalPages;
            document.getElementById('viewedPagesCount').textContent = totalPages;
            document.getElementById('progressPercent').textContent = '100%';
            document.getElementById('pdfProgressPercentage').textContent = '100%';
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('pdfProgressBar').style.width = '100%';
            document.getElementById('pdfProgressBar').classList.add('completed');
            
            // Update status badges
            const courseStatusBadge = document.querySelector('.material-card:has(i.fa-chart-line) .material-status');
            if (courseStatusBadge) {
                courseStatusBadge.className = 'material-status status-completed';
                courseStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed';
            }
            
            const pdfStatusBadge = document.querySelector('.material-card:has(i.fa-file-pdf) .material-status');
            if (pdfStatusBadge) {
                pdfStatusBadge.className = 'material-status status-completed';
                pdfStatusBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Completed';
            }
            
            // Add viewed badges to all PDF pages
            for (let num = 1; num <= totalPages; num++) {
                if (!document.getElementById(`page-badge-${num}`)) {
                    const pageElement = document.getElementById(`pdf-page-${num}`);
                    if (pageElement) {
                        const badge = document.createElement('div');
                        badge.className = 'page-viewed-badge';
                        badge.id = `page-badge-${num}`;
                        badge.innerHTML = '<i class="fas fa-check"></i>';
                        pageElement.appendChild(badge);
                    }
                }
            }
            
            // Remove button and show success
            btn.replaceWith(`
                <div class="alert alert-success mt-3">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Congratulations! You have successfully completed this course 🎓
                </div>
            `);
            
            // Show assessment
            if (assessmentContainer) {
                assessmentContainer.style.display = 'block';
            }
            
            showToast('Course completed successfully!', 'success');
            
            // Send to server
            $.post(window.location.href, { 
                mark_completed: 1,
                total_pages: totalPages 
            });
        });
    }

    // Fullscreen toggle
    if (fullscreenBtn) {
        const pdfCard = document.getElementById('pdfMaterialCard');
        
        fullscreenBtn.addEventListener('click', function() {
            isFullscreen = !isFullscreen;
            
            if (isFullscreen) {
                pdfCard.classList.add('pdf-fullscreen-mode');
                fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                fullscreenHint.style.display = 'block';
                
                setTimeout(() => {
                    fullscreenHint.style.display = 'none';
                }, 3000);
            } else {
                pdfCard.classList.remove('pdf-fullscreen-mode');
                fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                fullscreenHint.style.display = 'none';
            }
            
            setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
        });
    }

    // ESC key to exit fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isFullscreen) {
            isFullscreen = false;
            document.getElementById('pdfMaterialCard').classList.remove('pdf-fullscreen-mode');
            fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
            fullscreenHint.style.display = 'none';
        }
    });

    // Take Assessment button
    $('#takeAssessmentBtn').on('click', function(e){
        if (!isCompleted) {
            e.preventDefault();
            showToast('Please complete the course first!', 'warning');
            return false;
        }
        return true;
    });

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="alert alert-${type} alert-dismissible fade show shadow" role="alert">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        $('#toastContainer').append(toast);
        setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 5000);
    }

    <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>