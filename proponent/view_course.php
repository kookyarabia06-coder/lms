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

// Check if assessment exists for this course
$assessment = null;
$assessment_questions = [];

try {
    // First, check if assessments table exists and get the assessment
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE course_id = ? LIMIT 1");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();
    
    if ($assessment) {
        // Get questions for this assessment
        $stmt = $pdo->prepare("
            SELECT * FROM assessment_questions 
            WHERE assessment_id = ? 
            ORDER BY order_number ASC
        ");
        $stmt->execute([$assessment['id']]);
        $questions = $stmt->fetchAll();
        
        // For each question, get its options
        foreach ($questions as $question) {
            $stmt = $pdo->prepare("
                SELECT * FROM assessment_options 
                WHERE question_id = ? 
                ORDER BY order_number ASC
            ");
            $stmt->execute([$question['id']]);
            $options = $stmt->fetchAll();
            
            // Add options to question
            $question['options'] = $options;
            $assessment_questions[] = $question;
        }
    }
} catch (Exception $e) {
    // Log error or handle gracefully
    error_log("Error fetching assessment: " . $e->getMessage());
    $assessment = null;
    $assessment_questions = [];
}

// ============================================
// FETCH ENROLLED STUDENTS - FOR COURSE CREATORS
// ============================================

// 1. ALL enrolled students (ongoing + completed)
$stmt = $pdo->prepare('
    SELECT 
        u.id, 
        u.fname, 
        u.lname, 
        u.email,
        u.username,
        e.status,
        e.progress,
        e.total_time_seconds,
        e.enrolled_at,
        e.completed_at,
        DATE_FORMAT(e.enrolled_at, "%M %d, %Y") as enrolled_date,
        DATE_FORMAT(e.completed_at, "%M %d, %Y") as completed_date,
        CASE 
            WHEN e.status = "completed" THEN "bg-success"
            WHEN e.status = "ongoing" THEN "bg-warning"
            ELSE "bg-secondary"
        END as status_color,
        CASE 
            WHEN e.status = "completed" THEN "Completed"
            WHEN e.status = "ongoing" THEN "Ongoing"
            ELSE "Not Started"
        END as status_text
    FROM enrollments e
    JOIN users u ON e.user_id = u.id 
    WHERE e.course_id = ?
    ORDER BY 
        CASE e.status 
            WHEN "ongoing" THEN 1 
            WHEN "completed" THEN 2 
            ELSE 3 
        END,
        e.enrolled_at DESC
');
$stmt->execute([$courseId]);
$enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Count statistics
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

// 3. Completion rate
$completionRate = 0;
if ($stats['total_enrolled'] > 0) {
    $completionRate = round(($stats['completed_count'] / $stats['total_enrolled']) * 100);
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv' && (is_admin() || is_proponent())) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="enrolled_students_course_' . $courseId . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student Name', 'Email', 'Username', 'Status', 'Enrolled Date', 'Completed Date', 'Progress (%)', 'Time Spent (mins)']);
    
    foreach ($enrolledStudents as $student) {
        $timeMinutes = round($student['total_time_seconds'] / 60, 1);
        fputcsv($output, [
            $student['fname'] . ' ' . $student['lname'],
            $student['email'],
            $student['username'],
            $student['status_text'],
            $student['enrolled_date'],
            $student['completed_date'] ?? 'N/A',
            $student['progress'] . '%',
            $timeMinutes
        ]);
    }
    fclose($output);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=htmlspecialchars($course['title'])?> - Course View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
   
   <style>
        .students-section {
            margin-top: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .students-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
        }
        
        .students-stats {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
        }
        
        .stat-item i {
            margin-right: 8px;
        }
        
        .students-table-container {
            padding: 20px;
        }
        
        .student-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .progress-mini {
            width: 100px;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
        }
        
        .progress-mini-bar {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .export-btn {
            background: white;
            color: #667eea;
            border: 1px solid #667eea;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            background: #667eea;
            color: white;
        }
        
        /* Content cards matching the page design */
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            padding: 24px;
            margin-bottom: 30px;
        }
        
        .content-card h5 {
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        /* Fixed video container */
        .video-container {
            width: 100%;
            height: 500px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
        }
        
        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        
        /* PDF container - fixed height */
        .pdf-container {
            width: 100%;
            height: 700px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .pdf-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Assessment container inside content card - fixed height scrollable */
        .assessment-container {
            height: 600px;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f1f5f9;
        }
        
        .assessment-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .assessment-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .assessment-container::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 10px;
        }
        
        .assessment-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Assessment header inside the scrollable container */
        .assessment-header {
            background: linear-gradient(135deg, #334386 0%, #291583 100%);
            color: white;
            padding: 20px 24px;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .assessment-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .assessment-header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .assessment-meta {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .assessment-meta-item {
            background: rgba(255,255,255,0.15);
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
        }
        
        .assessment-meta-item i {
            margin-right: 5px;
            font-size: 10px;
        }
        
        /* Assessment content padding */
        .assessment-content {
            padding: 20px;
        }
        
        .question-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .question-number {
            font-weight: 700;
            color: #667eea;
            font-size: 15px;
        }
        
        .question-points {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .question-text {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .options-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .option-item {
            padding: 10px 15px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .correct-option {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .correct-option i {
            color: #28a745;
        }
        
        .option-marker {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .correct-option .option-marker {
            background: #28a745;
            color: white;
        }
        
        .true-false-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-true {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-false {
            background: #f8d7da;
            color: #721c24;
        }
        
        .essay-placeholder {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px dashed #ced4da;
            color: #6c757d;
            text-align: center;
        }
        
        .no-assessment {
            padding: 60px;
            text-align: center;
            color: #6c757d;
            background: white;
            border-radius: 16px;
            margin-top: 30px;
        }
        
        .no-assessment i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        .alert-info {
            background: #e7f5ff;
            border: 1px solid #b8e2ff;
            color: #0c4a6e;
            border-radius: 12px;
            padding: 12px 16px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="course-content-wrapper">
        <!-- Course Header -->
        <div class="course-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3>
                        <?=htmlspecialchars($course['title'])?>
                    </h3>
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
            <div>
                <!-- Button to toggle List of enrollees -->
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#enrolleesModal">
                    <i class="fas fa-users me-2"></i>View Enrollees (<?= $stats['total_enrolled'] ?? 0 ?>)
                </button>
            </div>
        </div>

        <!-- Enrollees Modal -->
        <div class="modal fade" id="enrolleesModal" tabindex="-1" aria-labelledby="enrolleesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="enrolleesModalLabel">
                            <i class="fas fa-users me-2"></i>
                            Enrolled Students - <?= htmlspecialchars($course['title']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Students Stats -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="students-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    Total: <?= $stats['total_enrolled'] ?? 0 ?>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-spinner"></i>
                                    Ongoing: <?= $stats['ongoing_count'] ?? 0 ?>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-check-circle"></i>
                                    Completed: <?= $stats['completed_count'] ?? 0 ?>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-chart-line"></i>
                                    Completion Rate: <?= $completionRate ?>%
                                </div>
                            </div>
                            
                            <?php if((is_admin() || is_proponent()) && count($enrolledStudents) > 0): ?>
                                <a href="?id=<?= $courseId ?>&export=csv" class="export-btn">
                                    <i class="fas fa-download me-2"></i>Export CSV
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Students Table -->
                        <?php if(count($enrolledStudents) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle" id="studentsTableModal">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Enrolled Date</th>
                                            <th>Completed Date</th>
                                            <th>Progress</th>
                                            <th>Time Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($enrolledStudents as $student): ?>
                                        <tr style="cursor: pointer;" onclick="window.location.href='user_profile.php?id=<?= $student['id'] ?>'">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?= strtoupper(substr($student['fname'] ?? '', 0, 1) . substr($student['lname'] ?? '', 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($student['fname'] ?? '') ?> <?= htmlspecialchars($student['lname'] ?? '') ?></strong>
                                                        <small class="d-block text-muted">@<?= htmlspecialchars($student['username'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                                            <td>
                                                <span class="badge <?= $student['status_color'] ?? 'bg-secondary' ?> status-badge">
                                                    <i class="fas fa-<?= $student['status'] === 'completed' ? 'check-circle' : 'play-circle' ?> me-1"></i>
                                                    <?= $student['status_text'] ?? ucfirst($student['status'] ?? 'Unknown') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar-alt text-muted me-1"></i>
                                                <?= $student['enrolled_date'] ?? date('M d, Y', strtotime($student['enrolled_at'])) ?>
                                            </td>
                                            <td>
                                                <?php if($student['completed_at']): ?>
                                                    <i class="fas fa-check-circle text-success me-1"></i>
                                                    <?= $student['completed_date'] ?? date('M d, Y', strtotime($student['completed_at'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="fw-bold me-2"><?= intval($student['progress'] ?? 0) ?>%</span>
                                                    <div class="progress-mini">
                                                        <div class="progress-mini-bar" style="width: <?= intval($student['progress'] ?? 0) ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $minutes = floor(($student['total_time_seconds'] ?? 0) / 60);
                                                $seconds = ($student['total_time_seconds'] ?? 0) % 60;
                                                ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= $minutes > 0 ? $minutes . 'm ' : '' ?><?= $seconds ?>s
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    Showing <?= count($enrolledStudents) ?> of <?= $stats['total_enrolled'] ?? 0 ?> students
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h5>No Enrolled Students Yet</h5>
                                <p class="text-muted">This course hasn't been taken by any students yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Preview Section -->
        <div class="content-card">
            <h5><i class="fas fa-info-circle text-primary me-2"></i> Course Preview</h5>
            <div class="modern-course-info-content">
                <?= $course['summary'] ?? '<p>No preview available.</p>' ?>
            </div>
        </div>

        <!-- PDF Content -->
        <?php if($course['file_pdf']): ?>
        <div class="content-card">
            <h5><i class="fas fa-file-pdf text-danger me-2"></i> Course PDF Material</h5>
            
            <div class="pdf-container">
                <iframe
                    src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>">
                </iframe>
            </div>

            <p class="mt-3">
                <a class="btn btn-outline-primary"
                   href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                   target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>Open PDF in new tab
                </a>
            </p>
        </div>
        <?php endif; ?>

        <!-- Video Content -->
        <?php if($course['file_video']): ?>
        <div class="content-card">
            <h5><i class="fas fa-video text-primary me-2"></i> Course Video</h5>
            
            <div class="video-container">
                <video id="courseVideo" controls>
                    <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars($course['file_video']) ?>" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assessment Content - Inside a content card like PDF and video -->
        <?php if($assessment): ?>
        <div class="content-card">
            <h5><i class="fas fa-clipboard-list text-primary me-2"></i> Course Assessment</h5>
            
            <div class="assessment-container">
                <div class="assessment-header">
                    <h4><?= htmlspecialchars($assessment['title']) ?></h4>
                    <p><?= htmlspecialchars($assessment['description'] ?: 'No description provided.') ?></p>
                    
                    <div class="assessment-meta">
                        <span class="assessment-meta-item">
                            <i class="fas fa-check-circle"></i>
                            Passing: <?= intval($assessment['passing_score']) ?>%
                        </span>
                        <?php if($assessment['time_limit']): ?>
                        <span class="assessment-meta-item">
                            <i class="fas fa-clock"></i>
                            Time: <?= intval($assessment['time_limit']) ?> min
                        </span>
                        <?php endif; ?>
                        <span class="assessment-meta-item">
                            <i class="fas fa-redo-alt"></i>
                            Attempts: <?= intval($assessment['attempts_allowed']) ?>
                        </span>
                        <span class="assessment-meta-item">
                            <i class="fas fa-question-circle"></i>
                            Questions: <?= count($assessment_questions) ?>
                        </span>
                    </div>
                </div>
                
                <div class="assessment-content">
                    <?php if(count($assessment_questions) > 0): ?>
                        <?php foreach($assessment_questions as $index => $question): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <span class="question-number">Question <?= $index + 1 ?></span>
                                    <span class="question-points"><?= intval($question['points']) ?> point<?= intval($question['points']) != 1 ? 's' : '' ?></span>
                                </div>
                                
                                <div class="question-text">
                                    <?= htmlspecialchars($question['question_text']) ?>
                                </div>
                                
                                <?php if($question['question_type'] == 'multiple_choice'): ?>
                                    <ul class="options-list">
                                        <?php foreach($question['options'] as $optIndex => $option): ?>
                                            <li class="option-item <?= $option['is_correct'] ? 'correct-option' : '' ?>">
                                                <span class="option-marker"><?= chr(65 + $optIndex) ?></span>
                                                <span><?= htmlspecialchars($option['option_text']) ?></span>
                                                <?php if($option['is_correct']): ?>
                                                    <i class="fas fa-check-circle ms-auto"></i>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                
                                <?php elseif($question['question_type'] == 'true_false'): ?>
                                    <?php 
                                    $correctOption = array_filter($question['options'], function($opt) { 
                                        return $opt['is_correct'] == 1; 
                                    });
                                    $correctValue = !empty($correctOption) ? reset($correctOption)['option_text'] : 'True';
                                    ?>
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        <span class="true-false-badge badge-true">
                                            <i class="fas fa-check-circle me-1"></i>True
                                        </span>
                                        <span class="true-false-badge <?= $correctValue == 'False' ? 'badge-false' : 'badge-true' ?>">
                                            <i class="fas fa-times-circle me-1"></i>False
                                        </span>
                                        <span class="text-success ms-2">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Correct: <strong><?= $correctValue ?></strong>
                                        </span>
                                    </div>
                                
                                <?php elseif($question['question_type'] == 'essay'): ?>
                                    <div class="essay-placeholder">
                                        <i class="fas fa-pencil-alt me-2"></i>
                                        Essay Question - Students will provide a written response
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-question-circle"></i>
                            <h5>No Questions Added</h5>
                            <p class="text-muted">This assessment doesn't have any questions yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="content-card">
            <h5><i class="fas fa-clipboard-list text-primary me-2"></i> Course Assessment</h5>
            <div class="no-assessment">
                <i class="fas fa-clipboard-list"></i>
                <p class="text-muted">This course doesn't have an assessment yet.</p>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.content-card, .course-info-card');
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