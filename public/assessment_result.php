<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$attemptId = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

if (!$attemptId) {
    $_SESSION['error_message'] = 'Invalid attempt ID';
    header('Location: assessments.php');
    exit;
}

// Fetch attempt details
$stmt = $pdo->prepare("
    SELECT aa.*, a.title as assessment_title, a.passing_score, a.course_id, 
           c.title as course_title, c.id as course_id,
           a.attempts_allowed
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.id = ? AND aa.user_id = ?
");
$stmt->execute([$attemptId, $u['id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error_message'] = 'Attempt not found';
    header('Location: assessments.php');
    exit;
}

// Fetch detailed answers
$stmt = $pdo->prepare("
    SELECT 
        q.id as question_id,
        q.question_text,
        q.question_type,
        q.points,
        a.selected_option_id,
        a.essay_answer,
        a.is_correct,
        a.points_earned,
        o.option_text as selected_option_text,
        (SELECT option_text FROM assessment_options WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_answer
    FROM assessment_questions q
    LEFT JOIN assessment_answers a ON q.id = a.question_id AND a.attempt_id = ?
    LEFT JOIN assessment_options o ON a.selected_option_id = o.id
    WHERE q.assessment_id = ?
    ORDER BY q.order_number
");
$stmt->execute([$attemptId, $attempt['assessment_id']]);
$answers = $stmt->fetchAll();

// Calculate statistics
$totalQuestions = count($answers);
$correctAnswers = count(array_filter($answers, fn($a) => $a['is_correct'] == 1));
$totalPoints = array_sum(array_column($answers, 'points'));
$earnedPoints = array_sum(array_column($answers, 'points_earned'));

// Get current attempt number
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ? AND id <= ?
");
$stmt->execute([$u['id'], $attempt['assessment_id'], $attemptId]);
$currentAttemptNumber = $stmt->fetchColumn();

// Check if user can retake
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ?
");
$stmt->execute([$u['id'], $attempt['assessment_id']]);
$attemptsUsed = $stmt->fetchColumn();
$canRetake = ($attemptsUsed < $attempt['attempts_allowed'] || $attempt['attempts_allowed'] == 0);

// Handle course reset (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_course'])) {
    try {
        $pdo->beginTransaction();
        
        $courseId = $attempt['course_id'];
        $userId = $u['id'];
        
        // Get enrollment record
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $enrollment = $stmt->fetch();
        
        if ($enrollment) {
            $enrollmentId = $enrollment['id'];
            
            // Clear PDF progress
            $stmt = $pdo->prepare("DELETE FROM pdf_progress WHERE enrollment_id = ?");
            $stmt->execute([$enrollmentId]);
            
            // Clear video progress
            $stmt = $pdo->prepare("DELETE FROM video_progress WHERE enrollment_id = ?");
            $stmt->execute([$enrollmentId]);
            
            // Reset enrollment progress
            $stmt = $pdo->prepare("
                UPDATE enrollments 
                SET pdf_completed = 0, 
                    video_completed = 0, 
                    pdf_total_pages = 0, 
                    pdf_current_page = 0,
                    progress = 0,
                    status = 'ongoing',
                    completed_at = NULL
                WHERE id = ?
            ");
            $stmt->execute([$enrollmentId]);
        }
        
        // Get all assessments for this course and delete attempts
        $stmt = $pdo->prepare("
            SELECT id FROM assessments WHERE course_id = ?
        ");
        $stmt->execute([$courseId]);
        $assessments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($assessments)) {
            foreach ($assessments as $assessmentId) {
                // Delete assessment answers
                $stmt = $pdo->prepare("
                    DELETE FROM assessment_answers 
                    WHERE attempt_id IN (
                        SELECT id FROM assessment_attempts 
                        WHERE user_id = ? AND assessment_id = ?
                    )
                ");
                $stmt->execute([$userId, $assessmentId]);
                
                // Delete assessment attempts
                $stmt = $pdo->prepare("
                    DELETE FROM assessment_attempts 
                    WHERE user_id = ? AND assessment_id = ?
                ");
                $stmt->execute([$userId, $assessmentId]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Course progress has been reset. You can now retake the course.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Course Reset Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error resetting course progress']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Result - <?= htmlspecialchars($attempt['assessment_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/assessment_result.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Print button -->
    <button class="ar-btn-print" onclick="window.print()" title="Print Result">
        <i class="fas fa-print"></i>
    </button>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="ar-main-container">
        <div class="ar-result-container">
            <!-- Result Header -->
            <div class="ar-result-header <?= $attempt['passed'] ? 'passed' : 'failed' ?>">
                <div class="ar-header-content">
                    <div class="ar-title-section">
                        <h2><?= htmlspecialchars($attempt['assessment_title']) ?></h2>
                        <div class="ar-course-name">
                            <i class="fas fa-book"></i>
                            <?= htmlspecialchars($attempt['course_title']) ?>
                        </div>
                    </div>

                    <div class="ar-score-section">
                        <div class="ar-score-circle <?= $attempt['passed'] ? 'passed' : 'failed' ?>">
                            <?= round($attempt['score']) ?>%
                        </div>
                        <div class="ar-result-badge <?= $attempt['passed'] ? 'badge-passed' : 'badge-failed' ?>">
                            <i class="fas <?= $attempt['passed'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                            <?= $attempt['passed'] ? 'PASSED' : 'FAILED' ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="ar-stats-grid">
                    <div class="ar-stat-card">
                        <div class="ar-stat-icon points">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="ar-stat-details">
                            <h4><?= $earnedPoints ?>/<?= $totalPoints ?></h4>
                            <p>Points Earned</p>
                        </div>
                    </div>

                    <div class="ar-stat-card">
                        <div class="ar-stat-icon time">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="ar-stat-details">
                            <h4><?= date('M d, Y', strtotime($attempt['completed_at'])) ?></h4>
                            <p>Date Completed</p>
                        </div>
                    </div>

                    <div class="ar-stat-card">
                        <div class="ar-stat-icon attempt">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="ar-stat-details">
                            <h4><?= $currentAttemptNumber ?><?php if($attempt['attempts_allowed'] > 0): ?>/<?= $attempt['attempts_allowed'] ?><?php endif; ?></h4>
                            <p>Attempt Number</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Answers -->
            <div class="ar-answers-section">
                <div class="ar-section-title">
                    <h3>Answer Summary</h3>
                </div>

                <div class="ar-answers-list" id="answersList">
                    <?php foreach ($answers as $index => $answer): 
                        $status = $answer['is_correct'] ? 'correct' : 'incorrect';
                        if ($answer['question_type'] == 'essay' && $answer['points_earned'] < $answer['points'] && $answer['points_earned'] > 0) {
                            $status = 'partial';
                        }
                    ?>
                    <div class="ar-answer-item" data-status="<?= $status ?>">
                        <div class="ar-answer-header <?= $status ?>">
                            <div class="ar-question-info">
                                <span class="ar-question-number"><?= $index + 1 ?></span>
                                <div>
                                    <div class="fw-600">Question <?= $index + 1 ?></div>
                                </div>
                            </div>
                            <div class="ar-score-indicator">
                                <span class="ar-score-badge <?= $status ?>">
                                    <i class="fas <?= $status == 'correct' ? 'fa-check-circle' : ($status == 'partial' ? 'fa-adjust' : 'fa-times-circle') ?> me-1"></i>
                                    <?= ucfirst($status) ?>
                                </span>
                            </div>
                        </div>

                        <div class="ar-answer-content" style="display: none;">
                            <div class="ar-answer-detail">
                                <div class="ar-detail-label">Question:</div>
                                <div class="ar-detail-value"><?= nl2br(htmlspecialchars($answer['question_text'])) ?></div>
                            </div>

                            <div class="ar-answer-detail">
                                <div class="ar-detail-label">Your Answer:</div>
                                <div class="ar-detail-value <?= $status ?>">
                                    <?php if ($answer['question_type'] == 'essay'): ?>
                                        <?= nl2br(htmlspecialchars($answer['essay_answer'] ?: 'No answer provided')) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($answer['selected_option_text'] ?: 'No answer selected') ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($answer['question_type'] != 'essay' && $answer['correct_answer']): ?>
                            <div class="ar-answer-detail">
                                <div class="ar-detail-label">Correct Answer:</div>
                                <div class="ar-detail-value correct">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?= htmlspecialchars($answer['correct_answer']) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($answer['question_type'] == 'essay'): ?>
                            <div class="ar-explanation-box">
                                <i class="fas fa-info-circle me-2" style="color: #667eea;"></i>
                                <strong>Note:</strong> Essay questions are graded manually. The score shown (<?= $answer['points_earned'] ?>/<?= $answer['points'] ?>) may be adjusted by the instructor.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="ar-action-buttons">
                <a href="course_view.php?id=<?= $attempt['course_id'] ?>" class="ar-btn-action ar-btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Course
                </a>

                <?php if (!$attempt['passed'] && $canRetake): ?>
                    <a href="take_assessment.php?assessment_id=<?= $attempt['assessment_id'] ?>" class="ar-btn-action ar-btn-retake">
                        <i class="fas fa-redo-alt"></i>
                        Retake Assessment
                    </a>
                <?php elseif (!$attempt['passed'] && !$canRetake): ?>
                    <button class="ar-btn-action ar-btn-retake" onclick="retakeCourse()">
                        <i class="fas fa-refresh"></i>
                        Retake Course
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle answer details
        function toggleAnswer(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.ar-expand-icon');

            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                content.classList.add('expanded');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        // Filter answers
        function filterAnswers(status) {
            const answers = document.querySelectorAll('.ar-answer-item');
            const buttons = document.querySelectorAll('.ar-filter-btn');

            // Update active button
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(status)) {
                    btn.classList.add('active');
                }
            });

            // Filter answers
            answers.forEach(answer => {
                if (status === 'all') {
                    answer.style.display = 'block';
                } else {
                    const answerStatus = answer.getAttribute('data-status');
                    if (answerStatus === status) {
                        answer.style.display = 'block';
                    } else {
                        answer.style.display = 'none';
                    }
                }
            });
        }

        // Expand all answers
        function expandAll() {
            const contents = document.querySelectorAll('.ar-answer-content');
            const icons = document.querySelectorAll('.ar-expand-icon');

            contents.forEach(content => {
                content.classList.add('expanded');
            });

            icons.forEach(icon => {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            });
        }

        // Collapse all answers
        function collapseAll() {
            const contents = document.querySelectorAll('.ar-answer-content');
            const icons = document.querySelectorAll('.ar-expand-icon');

            contents.forEach(content => {
                content.classList.remove('expanded');
            });

            icons.forEach(icon => {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P to print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            // Ctrl + E to expand all
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                expandAll();
            }

            // Ctrl + C to collapse all
            if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                collapseAll();
            }
        });

        // Animate numbers on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add keyboard shortcut hint
            const hint = document.createElement('div');
            hint.className = 'ar-shortcut-hint';
            hint.innerHTML = '<i class="fas fa-keyboard me-2"></i>Ctrl+E: Expand All | Ctrl+C: Collapse All | Ctrl+P: Print';
            document.body.appendChild(hint);

            setTimeout(() => {
                hint.style.opacity = '0';
                hint.style.transition = 'opacity 0.5s';
                setTimeout(() => hint.remove(), 500);
            }, 5000);
        });

        // Confetti effect for passing score
        <?php if ($attempt['passed']): ?>
        (function() {
            // Confetti settings
            const count = 200;
            const defaults = {
                origin: { y: 0.7 }
            };

            function fire(particleRatio, opts) {
                confetti(Object.assign({}, defaults, opts, {
                    particleCount: Math.floor(count * particleRatio)
                }));
            }

            // Load confetti library
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1';
            script.onload = function() {
                fire(0.25, { spread: 26, startVelocity: 55 });
                fire(0.2, { spread: 60 });
                fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
                fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
                fire(0.1, { spread: 120, startVelocity: 45 });
            };
            document.head.appendChild(script);
        })();
        <?php endif; ?>

        // Auto-expand incorrect answers
        document.addEventListener('DOMContentLoaded', function() {
            const incorrectAnswers = document.querySelectorAll('.ar-answer-item[data-status="incorrect"]');
            incorrectAnswers.forEach(answer => {
                const header = answer.querySelector('.ar-answer-header');
                const content = answer.querySelector('.ar-answer-content');
                const icon = answer.querySelector('.ar-expand-icon');

                content.classList.add('expanded');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            });
        });

        // Save as PDF function
        function saveAsPDF() {
            window.print();
        }

        // Copy result link
        function copyResultLink() {
            const link = window.location.href;
            navigator.clipboard.writeText(link).then(() => {
                alert('Result link copied to clipboard!');
            });
        }

        // Retake course - reset progress
        function retakeCourse() {
            if (!confirm('This will reset all your progress in this course (PDF, video, and assessment attempts). Are you sure you want to continue?')) {
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting...';

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { reset_course: 1 },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', 'Course progress and assessment attempts have been reset. Redirecting to course...');
                        setTimeout(() => {
                            const courseId = <?= $attempt['course_id'] ?>;
                            window.location.href = `course_view.php?id=${courseId}`;
                        }, 2000);
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        showAlert('danger', response.message || 'Failed to reset course');
                    }
                },
                error: function() {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showAlert('danger', 'Error resetting course. Please try again.');
                }
            });
        }

        // Alert helper function
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of result container
            const resultContainer = document.querySelector('.ar-result-container');
            if (resultContainer) {
                resultContainer.insertBefore(alertDiv, resultContainer.firstChild);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
    </script>
</body>
</html>