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
        
        /* PDF container - keep responsive but with better height */
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
        
        /* Course info badges for view only */
        .view-only-badge {
            background: #6c757d;
            color: white;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 12px;
            margin-left: 10px;
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
        <div class="course-info-card">
            <div class="content-header">
                <h4>Course Preview</h4>
            </div>
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