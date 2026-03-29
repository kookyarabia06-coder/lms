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

// Check if user can retake
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ?
");
$stmt->execute([$u['id'], $attempt['assessment_id']]);
$attemptsUsed = $stmt->fetchColumn();
$canRetake = ($attemptsUsed < $attempt['attempts_allowed'] || $attempt['attempts_allowed'] == 0);
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
                </div>
            </div>

            <!-- Detailed Answers -->
            <div class="ar-answers-section">
                <div class="ar-section-title">
                    <h3>Detailed Answers</h3>
                    <div class="ar-filter-buttons">
                        <button class="ar-filter-btn active" onclick="filterAnswers('all')">All</button>
                        <button class="ar-filter-btn" onclick="filterAnswers('correct')">Correct</button>
                        <button class="ar-filter-btn" onclick="filterAnswers('incorrect')">Incorrect</button>
                    </div>
                </div>

                <div class="ar-answers-list" id="answersList">
                    <?php foreach ($answers as $index => $answer): 
                        $status = $answer['is_correct'] ? 'correct' : 'incorrect';
                        if ($answer['question_type'] == 'essay' && $answer['points_earned'] < $answer['points'] && $answer['points_earned'] > 0) {
                            $status = 'partial';
                        }
                    ?>
                    <div class="ar-answer-item" data-status="<?= $status ?>">
                        <div class="ar-answer-header <?= $status ?>" onclick="toggleAnswer(this)">
                            <div class="ar-question-info">
                                <span class="ar-question-number"><?= $index + 1 ?></span>
                                <div>
                                    <div class="fw-600">Question <?= $index + 1 ?></div>
                                    <div class="ar-question-type">
                                        <i class="fas <?= $answer['question_type'] == 'essay' ? 'fa-pencil-alt' : 'fa-check-circle' ?> me-1"></i>
                                                                                <?= ucfirst(str_replace('_', ' ', $answer['question_type'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ar-score-indicator">
                                <span class="ar-score-badge <?= $status ?>">
                                    <i class="fas <?= $status == 'correct' ? 'fa-check-circle' : ($status == 'partial' ? 'fa-adjust' : 'fa-times-circle') ?> me-1"></i>
                                    <?= $answer['points_earned'] ?>/<?= $answer['points'] ?> pts
                                </span>
                                <i class="fas fa-chevron-down ar-expand-icon"></i>
                            </div>
                        </div>

                        <div class="ar-answer-content">
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
                    <button class="ar-btn-action ar-btn-retake disabled" disabled>
                        <i class="fas fa-ban"></i>
                        No Attempts Left
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
    </script>
</body>
</html>