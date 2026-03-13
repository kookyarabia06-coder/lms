<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$assessmentId = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$start = isset($_GET['start']) ? true : false;
$questionId = isset($_GET['question']) ? (int)$_GET['question'] : 1;

if (!$assessmentId) {
    $_SESSION['error_message'] = 'Invalid assessment ID';
    header('Location: courses_crud.php');
    exit;
}

// Fetch assessment details
$stmt = $pdo->prepare("
    SELECT a.*, c.title as course_title, c.id as course_id 
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$assessmentId]);
$assessment = $stmt->fetch();

if (!$assessment) {
    $_SESSION['error_message'] = 'Assessment not found';
    header('Location: courses_crud.php');
    exit;
}

// Check if user is enrolled
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$u['id'], $assessment['course_id']]);
if (!$stmt->fetch()) {
    $_SESSION['error_message'] = 'You are not enrolled in this course';
    header('Location: courses_crud.php');
    exit;
}

// Check if already passed
$stmt = $pdo->prepare("
    SELECT id FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ? AND passed = 1
    LIMIT 1
");
$stmt->execute([$u['id'], $assessmentId]);
if ($stmt->fetch() && !$start) {
    $_SESSION['info_message'] = 'You have already passed this assessment';
    header('Location: course_view.php?id=' . $assessment['course_id']);
    exit;
}

// Get attempt count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as attempt_count FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ?
");
$stmt->execute([$u['id'], $assessmentId]);
$attemptCount = $stmt->fetch()['attempt_count'];
$attemptsLeft = $assessment['attempts_allowed'] - $attemptCount;

// If not started and not out of attempts, show start page
if (!$start && $attemptsLeft > 0) {
    // Show start page with instructions
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($assessment['title']) ?> - Assessment</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
        <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            .assessment-container {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            .assessment-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f0f0f0;
            }
            .assessment-header h2 {
                color: #333;
                margin-bottom: 10px;
            }
            .assessment-meta {
                display: flex;
                justify-content: center;
                gap: 20px;
                margin-top: 15px;
                color: #666;
            }
            .meta-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: #f8f9fa;
                border-radius: 50px;
                font-size: 14px;
            }
            .meta-item i {
                color: #667eea;
            }
            .warning-box {
                background: #fff3cd;
                border: 1px solid #ffeeba;
                color: #856404;
                padding: 20px;
                border-radius: 12px;
                margin: 30px 0;
            }
            .warning-box i {
                font-size: 24px;
                margin-right: 10px;
                color: #f0ad4e;
            }
            .warning-title {
                font-weight: 700;
                font-size: 18px;
                margin-bottom: 10px;
            }
            .warning-list {
                list-style: none;
                padding: 0;
                margin: 15px 0 0 0;
            }
            .warning-list li {
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .warning-list i {
                font-size: 16px;
                width: 20px;
            }
            .btn-start {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 40px;
                font-size: 18px;
                font-weight: 600;
                border: none;
                border-radius: 50px;
                width: 100%;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 20px;
            }
            .btn-start:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }
            .attempts-left {
                text-align: center;
                margin-top: 15px;
                color: #666;
                font-size: 14px;
            }
            .attempts-warning {
                color: #dc3545;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="lms-sidebar-container">
            <?php include __DIR__ . '/../inc/sidebar.php'; ?>
        </div>
        <div class="main-content-wrapper">
            <div class="assessment-container">
                <div class="assessment-header">
                    <h2><?= htmlspecialchars($assessment['title']) ?></h2>
                    <p class="text-muted">Course: <?= htmlspecialchars($assessment['course_title']) ?></p>
                    <div class="assessment-meta">
                        <div class="meta-item"><i class="fas fa-clock"></i><?= $assessment['time_limit'] ? $assessment['time_limit'] . ' minutes' : 'No time limit' ?></div>
                        <div class="meta-item"><i class="fas fa-check-circle"></i>Passing: <?= $assessment['passing_score'] ?>%</div>
                        <div class="meta-item"><i class="fas fa-redo-alt"></i>Attempts: <?= $attemptCount ?>/<?= $assessment['attempts_allowed'] ?></div>
                    </div>
                </div>
                <?php if ($attemptsLeft <= 0): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>You have used all your allowed attempts.</div>
                    <a href="course_view.php?id=<?= $assessment['course_id'] ?>" class="btn btn-secondary">Back to Course</a>
                <?php else: ?>
                    <div class="warning-box">
                        <div class="d-flex align-items-center"><i class="fas fa-exclamation-triangle"></i><span class="warning-title">Important Instructions</span></div>
                        <ul class="warning-list">
                            <li><i class="fas fa-sync-alt"></i><span><strong>Do not refresh</strong> the page during the assessment</span></li>
                            <li><i class="fas fa-sign-out-alt"></i><span><strong>Do not navigate away</strong> from this page once started</span></li>
                            <li><i class="fas fa-hourglass-half"></i><span><?= $assessment['time_limit'] ? 'You have <strong>' . $assessment['time_limit'] . ' minutes</strong> to complete' : 'There is <strong>no time limit</strong>' ?></span></li>
                            <li><i class="fas fa-tachometer-alt"></i><span>Your progress will be <strong>lost</strong> if you leave or refresh</span></li>
                        </ul>
                    </div>
                    <div class="attempts-left"><i class="fas fa-info-circle me-1"></i>You have <span class="<?= $attemptsLeft == 1 ? 'attempts-warning' : '' ?>"><?= $attemptsLeft ?> attempt<?= $attemptsLeft != 1 ? 's' : '' ?></span> remaining</div>
                    <a href="?start=1&assessment_id=<?= $assessmentId ?>" class="btn-start"><i class="fas fa-play-circle me-2"></i>Start Assessment</a>
                <?php endif; ?>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// If out of attempts, redirect
if ($attemptsLeft <= 0) {
    $_SESSION['error_message'] = 'No attempts left for this assessment';
    header('Location: course_view.php?id=' . $assessment['course_id']);
    exit;
}

// Create or get active attempt
$stmt = $pdo->prepare("
    SELECT * FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ? AND completed_at IS NULL
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$u['id'], $assessmentId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO assessment_attempts (assessment_id, user_id, attempt_number, started_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$assessmentId, $u['id'], $attemptCount + 1]);
    $attemptId = $pdo->lastInsertId();
    
    // Fetch the new attempt
    $stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
} else {
    $attemptId = $attempt['id'];
}

// Fetch all questions for this assessment
$stmt = $pdo->prepare("
    SELECT * FROM assessment_questions 
    WHERE assessment_id = ? 
    ORDER BY order_number ASC
");
$stmt->execute([$assessmentId]);
$questions = $stmt->fetchAll();

// For each question, fetch its options
foreach ($questions as &$question) {
    $stmt = $pdo->prepare("
        SELECT * FROM assessment_options 
        WHERE question_id = ? 
        ORDER BY order_number ASC
    ");
    $stmt->execute([$question['id']]);
    $question['options'] = $stmt->fetchAll();
}

$totalQuestions = count($questions);
$currentQuestion = $questionId > $totalQuestions ? 1 : $questionId;

// Fetch user's answers for this attempt
$answers = [];
if ($attemptId) {
    $stmt = $pdo->prepare("
        SELECT * FROM assessment_answers 
        WHERE attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $answers[$row['question_id']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($assessment['title']) ?> - Taking Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .assessment-fullscreen {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        /* Question Navigation Sidebar */
        .question-nav {
            width: 280px;
            background: white;
            border-right: 1px solid #e0e4e8;
            display: flex;
            flex-direction: column;
            height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .nav-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e0e4e8;
        }
        
        .nav-header h4 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .nav-header p {
            font-size: 13px;
            color: #666;
            margin: 0;
        }
        
        .nav-header .timer {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        
        .questions-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .question-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        
        .question-btn {
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .question-btn:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            color: #495057;
        }
        
        .question-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .question-btn.answered {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .question-btn.answered.active {
            background: #667eea;
            color: white;
        }
        
        .nav-footer {
            padding: 20px;
            border-top: 1px solid #e0e4e8;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        /* Main Question Area */
        .question-area {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f0f2f5;
        }
        
        .question-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .question-number-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #e9ecef;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
        }
        
        .question-text {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .options-container {
            margin-top: 20px;
        }
        
        .option-item {
            margin-bottom: 15px;
        }
        
        .option-label {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-label:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }
        
        .option-label.selected {
            background: #e7f5ff;
            border-color: #667eea;
        }
        
        .option-marker {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
        }
        
        .option-label.selected .option-marker {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .option-text {
            flex: 1;
            font-size: 16px;
        }
        
        .essay-input {
            width: 100%;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            font-size: 16px;
            min-height: 150px;
            resize: vertical;
        }
        
        .essay-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            max-width: 900px;
            margin: 30px auto 0;
        }
        
        .nav-btn {
            padding: 10px 25px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            color: #495057;
        }
        
        .nav-btn:disabled,
        .nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .nav-btn.primary {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .nav-btn.primary:hover {
            background: #5a6fd6;
            color: white;
        }
    </style>
</head>
<body>
    <div class="assessment-fullscreen">
        <!-- Question Navigation Sidebar -->
        <div class="question-nav">
            <div class="nav-header">
                <h4><?= htmlspecialchars($assessment['title']) ?></h4>
                <p>Question <?= $currentQuestion ?> of <?= $totalQuestions ?></p>
                <?php if ($assessment['time_limit']): ?>
                <div class="timer" id="timer">
                    <?= $assessment['time_limit'] ?>:00
                </div>
                <?php endif; ?>
            </div>
            
            <div class="questions-list">
                <div class="question-grid">
                    <?php for ($i = 1; $i <= $totalQuestions; $i++): 
                        $question = $questions[$i-1];
                        $isAnswered = isset($answers[$question['id']]);
                        $isActive = ($i == $currentQuestion);
                    ?>
                    <a href="?assessment_id=<?= $assessmentId ?>&question=<?= $i ?>" 
                       class="question-btn <?= $isAnswered ? 'answered' : '' ?> <?= $isActive ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="nav-footer">
                <button class="btn-submit" onclick="submitAssessment()">
                    <i class="fas fa-check-circle me-2"></i>Submit Assessment
                </button>
            </div>
        </div>
        
        <!-- Main Question Area -->
        <div class="question-area">
            <div class="question-card">
                <?php if ($totalQuestions > 0 && isset($questions[$currentQuestion-1])): 
                    $question = $questions[$currentQuestion-1];
                    $questionId = $question['id'];
                    $savedAnswer = isset($answers[$questionId]) ? $answers[$questionId] : null;
                ?>
                <span class="question-number-badge">Question <?= $currentQuestion ?></span>
                <div class="question-text"><?= htmlspecialchars($question['question_text']) ?></div>
                
                <form method="post" id="answerForm">
                    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
                    <input type="hidden" name="question_id" value="<?= $questionId ?>">
                    <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
                    <input type="hidden" name="current_question" value="<?= $currentQuestion ?>">
                    
                    <div class="options-container">
                        <?php if ($question['question_type'] == 'multiple_choice'): ?>
                            <?php foreach ($question['options'] as $index => $option): ?>
                            <div class="option-item">
                                <label class="option-label <?= ($savedAnswer && $savedAnswer['selected_option_id'] == $option['id']) ? 'selected' : '' ?>">
                                    <input type="radio" name="selected_option" value="<?= $option['id'] ?>" 
                                           style="display: none;"
                                           <?= ($savedAnswer && $savedAnswer['selected_option_id'] == $option['id']) ? 'checked' : '' ?>>
                                    <span class="option-marker"><?= chr(65 + $index) ?></span>
                                    <span class="option-text"><?= htmlspecialchars($option['option_text']) ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        
                        <?php elseif ($question['question_type'] == 'true_false'): ?>
                            <?php foreach ($question['options'] as $index => $option): ?>
                            <div class="option-item">
                                <label class="option-label <?= ($savedAnswer && $savedAnswer['selected_option_id'] == $option['id']) ? 'selected' : '' ?>">
                                    <input type="radio" name="selected_option" value="<?= $option['id'] ?>" 
                                           style="display: none;"
                                           <?= ($savedAnswer && $savedAnswer['selected_option_id'] == $option['id']) ? 'checked' : '' ?>>
                                    <span class="option-marker"><?= chr(65 + $index) ?></span>
                                    <span class="option-text"><?= htmlspecialchars($option['option_text']) ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            
                        <?php elseif ($question['question_type'] == 'essay'): ?>
                            <textarea name="essay_answer" class="essay-input" placeholder="Type your answer here..."><?= $savedAnswer ? htmlspecialchars($savedAnswer['answer_text']) : '' ?></textarea>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php else: ?>
                <div class="alert alert-warning">No questions found for this assessment.</div>
                <?php endif; ?>
            </div>
            
            <div class="navigation-buttons">
                <a href="?assessment_id=<?= $assessmentId ?>&question=<?= max(1, $currentQuestion-1) ?>" 
                   class="nav-btn <?= $currentQuestion <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left me-2"></i>Previous
                </a>
                <button class="nav-btn primary" onclick="saveAndContinue()">
                    Save & Continue<i class="fas fa-chevron-right ms-2"></i>
                </button>
                <a href="?assessment_id=<?= $assessmentId ?>&question=<?= min($totalQuestions, $currentQuestion+1) ?>" 
                   class="nav-btn <?= $currentQuestion >= $totalQuestions ? 'disabled' : '' ?>">
                    Next<i class="fas fa-chevron-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle option selection without page reload
        document.querySelectorAll('input[name="selected_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Highlight selected option
                document.querySelectorAll('.option-label').forEach(label => {
                    label.classList.remove('selected');
                });
                this.closest('.option-label').classList.add('selected');
                
                // Save answer via AJAX (no page reload)
                saveAnswer();
            });
        });
        
        document.querySelectorAll('textarea[name="essay_answer"]').forEach(textarea => {
            let timeout;
            textarea.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    saveAnswer();
                }, 1000);
            });
        });
        
        function saveAnswer() {
            const form = document.getElementById('answerForm');
            const formData = new FormData(form);
            
            fetch('save_answer_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark question as answered in navigation
                    const currentQuestion = <?= $currentQuestion ?>;
                    const questionBtns = document.querySelectorAll('.question-btn');
                    if (questionBtns[currentQuestion - 1]) {
                        questionBtns[currentQuestion - 1].classList.add('answered');
                    }
                    console.log('Answer saved');
                }
            })
            .catch(error => {
                console.error('Error saving answer:', error);
            });
        }
        
        function saveAndContinue() {
            saveAnswer();
            // Navigate to next question after a short delay
            setTimeout(() => {
                const nextQuestion = <?= min($totalQuestions, $currentQuestion+1) ?>;
                if (nextQuestion <= <?= $totalQuestions ?>) {
                    window.location.href = '?assessment_id=<?= $assessmentId ?>&question=' + nextQuestion;
                }
            }, 300);
        }
        
        function submitAssessment() {
            if (confirm('Are you sure you want to submit your assessment? This action cannot be undone.')) {
                // Save current answer first
                saveAnswer();
                
                // Then submit
                setTimeout(() => {
                    window.location.href = 'submit_assessment.php?attempt_id=<?= $attemptId ?>&assessment_id=<?= $assessmentId ?>';
                }, 300);
            }
        }
        
        // Timer functionality
        <?php if ($assessment['time_limit']): ?>
        let timeLeft = <?= $assessment['time_limit'] * 60 ?>; // in seconds
        const timerDisplay = document.getElementById('timer');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                // Time's up - auto submit
                alert('Time is up! Your assessment will be submitted automatically.');
                window.location.href = 'submit_assessment.php?attempt_id=<?= $attemptId ?>&assessment_id=<?= $assessmentId ?>';
            }
            
            timeLeft--;
        }
        
        setInterval(updateTimer, 1000);
        <?php endif; ?>
        
        // Mark initially selected option on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="selected_option"]:checked');
            if (checkedRadio) {
                checkedRadio.closest('.option-label').classList.add('selected');
            }
        });
    </script>
</body>
</html>