<?php
error_log("===== TAKE ASSESSMENT START =====");
error_log("GET data: " . print_r($_GET, true));
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$assessmentId = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$start = isset($_GET['start']) ? true : false;

error_log("assessmentId after parsing: " . $assessmentId);

if (!$assessmentId) {
    error_log("NO ASSESSMENT ID - redirecting to courses.php");
    $_SESSION['error_message'] = 'Invalid assessment ID';
    header('Location: courses.php');
    exit;
}

error_log("Assessment ID is valid: " . $assessmentId);

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
    header('Location: assessments.php');
    exit;
}

// Check if user is enrolled
$stmt = $pdo->prepare("SELECT id, status FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$u['id'], $assessment['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    $_SESSION['error_message'] = 'You are not enrolled in this course';
    header('Location: assessments.php');
    exit;
}

// Get all attempts for this assessment
$stmt = $pdo->prepare("
    SELECT * FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ?
    ORDER BY started_at DESC
");
$stmt->execute([$u['id'], $assessmentId]);
$attempts = $stmt->fetchAll();

// Calculate attempts used and check if passed with 100%
$attemptsUsed = count($attempts);
$hasPassed = false;
foreach ($attempts as $attempt) {
    if ($attempt['passed'] == 1) {
        $hasPassed = true;
        break;
    }
}

$attemptsLeft = $assessment['attempts_allowed'] - $attemptsUsed;
$canStart = true;
$disabledReason = '';

if ($hasPassed) {
    $canStart = false;
    $disabledReason = 'You have already passed this assessment';
} elseif ($assessment['attempts_allowed'] > 0 && $attemptsLeft <= 0) {
    $canStart = false;
    $disabledReason = 'No attempts remaining';
}

// Check for existing in-progress attempt
$inProgressAttempt = null;
foreach ($attempts as $attempt) {
    if ($attempt['status'] == 'in_progress') {
        $inProgressAttempt = $attempt;
        break;
    }
}

// Handle starting a new attempt
if ($start && $canStart && !$inProgressAttempt) {
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO assessment_attempts (assessment_id, user_id, status, started_at)
        VALUES (?, ?, 'in_progress', NOW())
    ");
    $stmt->execute([$assessmentId, $u['id']]);
    $attemptId = $pdo->lastInsertId();
    
    // Redirect to the assessment proper
    header('Location: take_assessment.php?assessment_id=' . $assessmentId . '&take=1');
    exit;
}

// If there's an in-progress attempt and we're not starting a new one, go to assessment proper
if ($inProgressAttempt && !$start && !isset($_GET['take'])) {
    header('Location: take_assessment.php?assessment_id=' . $assessmentId . '&take=1');
    exit;
}

// If we're taking the assessment (either new or in-progress)
if (isset($_GET['take']) && ($inProgressAttempt || $canStart)) {
    // Get the attempt ID
    if ($inProgressAttempt) {
        $attemptId = $inProgressAttempt['id'];
    } else {
        // This should not happen, but just in case
        header('Location: take_assessment.php?assessment_id=' . $assessmentId);
        exit;
    }
    
// Fetch all questions
$stmt = $pdo->prepare("
    SELECT 
        q.id,
        q.question_text,
        q.question_type,
        q.points,
        q.order_number,
        (
            SELECT CONCAT(
                '[',
                GROUP_CONCAT(
                    JSON_OBJECT(
                        'id', o.id,
                        'option_text', o.option_text,
                        'is_correct', o.is_correct,
                        'order_number', o.order_number
                    )
                ),
                ']'
            )
            FROM assessment_options o 
            WHERE o.question_id = q.id
        ) as options_json
    FROM assessment_questions q
    WHERE q.assessment_id = ?
    ORDER BY q.order_number ASC
");
$stmt->execute([$assessmentId]);
$questionsData = $stmt->fetchAll();

// Format questions for JavaScript
$questions = [];
foreach ($questionsData as $q) {
    $options = json_decode($q['options_json'], true) ?: [];
    // Remove is_correct from options sent to client (for security)
    foreach ($options as &$opt) {
        unset($opt['is_correct']);
    }
    $questions[] = [
        'id' => $q['id'],
        'question_text' => $q['question_text'],
        'question_type' => $q['question_type'],
        'points' => $q['points'],
        'options' => $options
    ];
}
    
    $totalQuestions = count($questions);
    $timeLimit = $assessment['time_limit'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($assessment['title']) ?> - Taking Assessment</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
        <link href="<?= BASE_URL ?>/assets/css/take_assessment.css" rel="stylesheet">
    </head>
    <body>
        <div class="ta-leave-warning" id="leaveWarning">
            <i class="fas fa-exclamation-triangle"></i>
            Don't leave! Your progress will be lost if you exit.
        </div>

        <div class="ta-loading-overlay" id="loadingOverlay">
            <div class="ta-loading-spinner">
                <div class="ta-spinner"></div>
                <p id="loadingMessage">Processing...</p>
            </div>
        </div>

        <div class="ta-grace-timer" id="graceTimer" style="display: none;">
            <i class="fas fa-hourglass-half me-2"></i>
            <span id="graceTimerText">Auto-submitting in 60s</span>
        </div>

        <div class="ta-shortcut-hint" id="shortcutHint">
            <i class="fas fa-keyboard"></i>
            <span>← Previous | → Next | Ctrl+S Save</span>
        </div>

        <div class="ta-fullscreen">
            <!-- Question Navigation Sidebar -->
            <div class="ta-question-nav">
                <div class="ta-nav-header">
                    <h4><?= htmlspecialchars($assessment['title']) ?></h4>
                    <p><?= htmlspecialchars($assessment['course_title']) ?></p>

                    <!-- Progress Bar -->
                    <div class="ta-progress-container">
                        <div class="ta-progress-info">
                            <span>Progress</span>
                            <span id="progressText">0/<?= $totalQuestions ?> (0%)</span>
                        </div>
                        <div class="ta-progress-bar">
                            <div class="ta-progress-fill" id="progressFill" style="width: 0%;"></div>
                        </div>
                    </div>

                    <!-- Timer -->
                    <?php if ($timeLimit && $timeLimit > 0): ?>
                    <div class="ta-timer" id="timer">
                        <div class="ta-timer-label">Time Remaining</div>
                        <div class="ta-timer-value" id="timerValue"><?= str_pad($timeLimit, 2, '0', STR_PAD_LEFT) ?>:00</div>
                    </div>
                    <div id="graceMessage" class="ta-grace-message" style="display: none;"></div>
                    <?php endif; ?>
                </div>

                <div class="ta-questions-list">
                    <h5><i class="fas fa-list me-2"></i>Question Navigator</h5>
                    <div class="ta-question-grid" id="questionGrid">
                        <?php for ($i = 1; $i <= $totalQuestions; $i++): ?>
                        <button class="question-btn" data-question="<?= $i ?>">
                            <?= $i ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="nav-footer">
                    <button class="btn-submit" id="submitBtn">
                        <i class="fas fa-check-circle"></i>Submit Assessment
                    </button>
                </div>
            </div>
            
            <!-- Main Question Area -->
            <div class="question-area">
                <div class="question-container">
                    <div class="question-card" id="questionCard">
                        <!-- Question will be loaded here by JavaScript -->
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="navigation-buttons">
                        <button class="nav-btn" id="prevBtn" disabled>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <button class="nav-btn primary" id="nextBtn">
                            Save & Continue <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Assessment data from PHP
            const assessmentData = {
                id: <?= $assessmentId ?>,
                attemptId: <?= $attemptId ?>,
                timeLimit: <?= $timeLimit ? $timeLimit * 60 : 0 ?>,
                passingScore: <?= $assessment['passing_score'] ?>,
                questions: <?= json_encode($questions) ?>
            };
            
            // State management
            let state = {
                currentQuestion: 1,
                totalQuestions: assessmentData.questions.length,
                answers: {}, // question_id => selected_option_id
                timeLeft: assessmentData.timeLimit,
                timerInterval: null,
                graceTimer: null,
                graceTimeLeft: 60,
                inGrace: false,
                submitting: false
            };
            
            // DOM Elements
            const elements = {
                questionCard: document.getElementById('questionCard'),
                prevBtn: document.getElementById('prevBtn'),
                nextBtn: document.getElementById('nextBtn'),
                submitBtn: document.getElementById('submitBtn'),
                questionGrid: document.getElementById('questionGrid'),
                progressFill: document.getElementById('progressFill'),
                progressText: document.getElementById('progressText'),
                timer: document.getElementById('timer'),
                timerValue: document.getElementById('timerValue'),
                graceMessage: document.getElementById('graceMessage'),
                graceTimer: document.getElementById('graceTimer'),
                graceTimerText: document.getElementById('graceTimerText'),
                loadingOverlay: document.getElementById('loadingOverlay'),
                loadingMessage: document.getElementById('loadingMessage'),
                leaveWarning: document.getElementById('leaveWarning'),
                shortcutHint: document.getElementById('shortcutHint')
            };
            
            // Initialize
            function init() {
                renderQuestion(1);
                updateQuestionButtons();
                updateProgress();
                startTimer();
                
                // Event listeners
                document.querySelectorAll('.question-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const q = parseInt(btn.dataset.question);
                        navigateToQuestion(q);
                    });
                });
                
                elements.prevBtn.addEventListener('click', () => {
                    if (state.currentQuestion > 1) {
                        navigateToQuestion(state.currentQuestion - 1);
                    }
                });
                
                elements.nextBtn.addEventListener('click', () => {
                    saveCurrentAnswer();
                    if (state.currentQuestion < state.totalQuestions) {
                        navigateToQuestion(state.currentQuestion + 1);
                    } else {
                        // On last question, Next button acts as Save
                        updateQuestionButtons();
                    }
                });
                
                elements.submitBtn.addEventListener('click', submitAssessment);
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 's') {
                        e.preventDefault();
                        saveCurrentAnswer();
                    } else if (e.key === 'ArrowLeft' && !elements.prevBtn.disabled) {
                        e.preventDefault();
                        elements.prevBtn.click();
                    } else if (e.key === 'ArrowRight' && !elements.nextBtn.disabled) {
                        e.preventDefault();
                        elements.nextBtn.click();
                    }
                });
                
                // Before unload warning
                window.addEventListener('beforeunload', (e) => {
                    if (!state.submitting) {
                        elements.leaveWarning.style.display = 'flex';
                        setTimeout(() => {
                            elements.leaveWarning.style.display = 'none';
                        }, 3000);
                        
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
                
                // Hide shortcut hint after 5 seconds
                setTimeout(() => {
                    elements.shortcutHint.style.opacity = '0';
                    setTimeout(() => elements.shortcutHint.remove(), 500);
                }, 5000);
            }
            
            // Render question
            function renderQuestion(questionNum) {
                const q = assessmentData.questions[questionNum - 1];
                if (!q) return;
                
                let optionsHtml = '';
                
                if (q.question_type === 'multiple_choice' || q.question_type === 'true_false') {
                    q.options.forEach((opt, idx) => {
                        const letter = String.fromCharCode(65 + idx);
                        const isSelected = state.answers[q.id] === opt.id;
                        
                        optionsHtml += `
                            <div class="option-item">
                                <label class="option-label ${isSelected ? 'selected' : ''}">
                                    <input type="radio" name="question_option" value="${opt.id}" 
                                           style="display: none;" ${isSelected ? 'checked' : ''}>
                                    <span class="option-marker">${letter}</span>
                                    <span class="option-text">${escapeHtml(opt.option_text)}</span>
                                </label>
                            </div>
                        `;
                    });
                }
                
                elements.questionCard.innerHTML = `
                    <div class="question-header">
                        <span class="question-number-badge">
                            <i class="fas fa-question-circle me-2"></i>Question ${questionNum} of ${state.totalQuestions}
                        </span>
                        <span class="question-type-badge">
                            <i class="fas ${q.question_type === 'multiple_choice' ? 'fa-check-circle' : 'fa-check-double'}"></i>
                            ${q.question_type === 'multiple_choice' ? 'Multiple Choice' : 'True/False'}
                        </span>
                    </div>
                    
                    <div class="question-text">${escapeHtml(q.question_text)}</div>
                    
                    <div class="options-container">
                        ${optionsHtml}
                    </div>
                `;
                
                // Add event listeners to radio buttons
                document.querySelectorAll('input[name="question_option"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        // Remove selected class from all labels
                        document.querySelectorAll('.option-label').forEach(label => {
                            label.classList.remove('selected');
                        });
                        
                        // Add selected class to this label
                        this.closest('.option-label').classList.add('selected');
                        
                        // Save answer
                        state.answers[q.id] = parseInt(this.value);
                        
                        // Mark question as answered in navigator
                        const btn = document.querySelector(`.question-btn[data-question="${questionNum}"]`);
                        if (btn && !btn.classList.contains('answered')) {
                            btn.classList.add('answered');
                            updateProgress();
                        }
                    });
                });
                
                // Update navigation buttons
                elements.prevBtn.disabled = (questionNum === 1);
                elements.nextBtn.innerHTML = (questionNum === state.totalQuestions) 
                    ? '<i class="fas fa-save me-2"></i>Save Answer' 
                    : 'Save & Continue <i class="fas fa-chevron-right"></i>';
            }
            
            // Navigate to question
            function navigateToQuestion(questionNum) {
                saveCurrentAnswer();
                state.currentQuestion = questionNum;
                renderQuestion(questionNum);
                
                // Update active state in navigator
                document.querySelectorAll('.question-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (parseInt(btn.dataset.question) === questionNum) {
                        btn.classList.add('active');
                    }
                });
            }
            
            // Save current answer
            function saveCurrentAnswer() {
                const selectedRadio = document.querySelector('input[name="question_option"]:checked');
                if (selectedRadio) {
                    const q = assessmentData.questions[state.currentQuestion - 1];
                    state.answers[q.id] = parseInt(selectedRadio.value);
                    
                    // Mark as answered
                    const btn = document.querySelector(`.question-btn[data-question="${state.currentQuestion}"]`);
                    if (btn && !btn.classList.contains('answered')) {
                        btn.classList.add('answered');
                        updateProgress();
                    }
                }
            }
            
            // Update question buttons appearance
            function updateQuestionButtons() {
                document.querySelectorAll('.question-btn').forEach(btn => {
                    btn.classList.remove('active', 'answered');
                });
                
                // Mark answered questions
                Object.keys(state.answers).forEach(qId => {
                    const questionIndex = assessmentData.questions.findIndex(q => q.id == qId);
                    if (questionIndex !== -1) {
                        const btn = document.querySelector(`.question-btn[data-question="${questionIndex + 1}"]`);
                        if (btn) btn.classList.add('answered');
                    }
                });
                
                // Mark current question
                const currentBtn = document.querySelector(`.question-btn[data-question="${state.currentQuestion}"]`);
                if (currentBtn) currentBtn.classList.add('active');
            }
            
            // Update progress bar
            function updateProgress() {
                const answeredCount = Object.keys(state.answers).length;
                const percent = Math.round((answeredCount / state.totalQuestions) * 100);
                
                elements.progressFill.style.width = percent + '%';
                elements.progressText.textContent = `${answeredCount}/${state.totalQuestions} (${percent}%)`;
            }
            
            // Timer functions
            function startTimer() {
                if (!state.timeLeft) return;
                
                state.timerInterval = setInterval(() => {
                    if (state.submitting) return;
                    
                    state.timeLeft--;
                    
                    if (state.timeLeft <= 0 && !state.inGrace) {
                        // Time's up - start grace period
                        clearInterval(state.timerInterval);
                        startGracePeriod();
                    } else if (state.timeLeft > 0) {
                        updateTimerDisplay();
                    }
                }, 1000);
            }
            
            function updateTimerDisplay() {
                if (!elements.timerValue) return;
                
                const minutes = Math.floor(state.timeLeft / 60);
                const seconds = state.timeLeft % 60;
                elements.timerValue.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Warning at 5 minutes
                if (state.timeLeft <= 300 && !state.inGrace) {
                    elements.timer.classList.add('warning');
                }
            }
            
            function startGracePeriod() {
                state.inGrace = true;
                elements.timer.classList.remove('warning');
                elements.timer.classList.add('grace');
                elements.timerValue.textContent = '00:00';
                elements.graceMessage.style.display = 'block';
                elements.graceMessage.innerHTML = '⏰ Assessment timer ended. Please finalize your work and submit. Auto-submit in <span id="graceCountdown">60</span>s';
                elements.graceTimer.style.display = 'flex';
                
                state.graceTimer = setInterval(() => {
                    state.graceTimeLeft--;
                    
                    if (state.graceTimeLeft <= 0) {
                        clearInterval(state.graceTimer);
                        submitAssessment(true); // Auto-submit
                    } else {
                        document.getElementById('graceCountdown').textContent = state.graceTimeLeft;
                        elements.graceTimerText.textContent = `Auto-submitting in ${state.graceTimeLeft}s`;
                    }
                }, 1000);
            }
            
            // Submit assessment
            function submitAssessment(isAutoSubmit = false) {
                if (state.submitting) return;
                
                state.submitting = true;
                
                // Clear timers
                if (state.timerInterval) clearInterval(state.timerInterval);
                if (state.graceTimer) clearInterval(state.graceTimer);
                
                // Show loading
                elements.loadingOverlay.style.display = 'flex';
                elements.loadingMessage.textContent = isAutoSubmit ? 'Auto-submitting...' : 'Submitting assessment...';
                
                // Save current answer
                saveCurrentAnswer();
                
                // Prepare answers for submission
                const answers = [];
                assessmentData.questions.forEach(q => {
                    if (state.answers[q.id]) {
                        answers.push({
                            question_id: q.id,
                            selected_option_id: state.answers[q.id]
                        });
                    }
                });
                
                // Submit via AJAX
                fetch('submit_assessment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attempt_id: assessmentData.attemptId,
                        answers: answers
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'assessment_result.php?attempt_id=' + assessmentData.attemptId;
                    } else {
                        alert('Error submitting assessment. Please try again.');
                        state.submitting = false;
                        elements.loadingOverlay.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error. Please try again.');
                    state.submitting = false;
                    elements.loadingOverlay.style.display = 'none';
                });
            }
            
            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Initialize on page load
            document.addEventListener('DOMContentLoaded', init);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If we get here, show the start page
// Get total questions count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?");
$stmt->execute([$assessmentId]);
$totalQuestions = $stmt->fetchColumn();

// Get any previous attempts for display
$stmt = $pdo->prepare("
    SELECT score, passed, completed_at 
    FROM assessment_attempts 
    WHERE user_id = ? AND assessment_id = ? AND status = 'completed'
    ORDER BY completed_at DESC
");
$stmt->execute([$u['id'], $assessmentId]);
$previousAttempts = $stmt->fetchAll();
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
    <link href="<?= BASE_URL ?>/assets/css/take_assessment.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="main-content-wrapper">
        <div class="assessment-container">
            <div class="welcome-card">
                <div class="card-header-gradient">
                    <h1><i class="fas fa-clipboard-list me-3"></i><?= htmlspecialchars($assessment['title']) ?></h1>
                    <p><?= htmlspecialchars($assessment['course_title']) ?></p>
                </div>
                
                <div class="card-body">
                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon time">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-value"><?= $assessment['time_limit'] ? $assessment['time_limit'] . ' min' : 'No limit' ?></div>
                            <div class="info-label">Time Limit</div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon passing">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="info-value"><?= $assessment['passing_score'] ?>%</div>
                            <div class="info-label">Passing Score</div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon attempts">
                                <i class="fas fa-redo-alt"></i>
                            </div>
                            <div class="info-value">
                                <?php if ($assessment['attempts_allowed'] == 0): ?>
                                    Unlimited
                                <?php else: ?>
                                    <?= $attemptsUsed ?>/<?= $assessment['attempts_allowed'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="info-label">Attempts Used</div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon questions">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="info-value"><?= $totalQuestions ?></div>
                            <div class="info-label">Questions</div>
                        </div>
                    </div>
                    
                    <!-- Assessment Description -->
                    <?php if ($assessment['description']): ?>
                    <div class="mb-4">
                        <h5 class="mb-3">Description</h5>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($assessment['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Warning Box - Important Instructions -->
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="warning-box-content">
                            <div class="warning-box-title">⚠️ Important: Read Before Starting</div>
                            <p class="warning-box-text">
                                Once you click "Start Assessment", you cannot leave this page. 
                                Do not refresh or close your browser. Your answers are saved locally 
                                until you submit. Make sure you have a stable internet connection.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Attempts Warning if limited attempts left -->
                    <?php if ($assessment['attempts_allowed'] > 0 && $attemptsLeft <= 2 && $attemptsLeft > 0 && $canStart): ?>
                    <div class="attempts-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="attempts-warning-content">
                            <div class="attempts-warning-title">Limited Attempts Remaining!</div>
                            <p class="attempts-warning-text">
                                You have <?= $attemptsLeft ?> attempt<?= $attemptsLeft != 1 ? 's' : '' ?> left. 
                                Make sure you're prepared before starting.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Start Button -->
                    <?php if ($canStart): ?>
                        <a href="?assessment_id=<?= $assessmentId ?>&start=1" class="btn-start">
                            <i class="fas fa-play-circle"></i>
                            Start Assessment
                        </a>
                        <?php if ($assessment['attempts_allowed'] > 0): ?>
                            <p class="text-center text-muted small mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                You have <?= $attemptsLeft ?> attempt<?= $attemptsLeft != 1 ? 's' : '' ?> remaining
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn-start disabled" disabled>
                            <i class="fas fa-lock"></i>
                            <?= $disabledReason ?>
                        </button>
                        <p class="text-center text-muted small mt-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <?= $disabledReason ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Previous Attempts -->
                    <?php if (!empty($previousAttempts)): ?>
                    <div class="previous-attempts">
                        <h6 class="mb-3"><i class="fas fa-history me-2"></i>Your Previous Attempts</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Score</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($previousAttempts as $attempt): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($attempt['completed_at'])) ?></td>
                                        <td><?= $attempt['score'] ?>%</td>
                                        <td>
                                            <?php if ($attempt['passed']): ?>
                                                <span class="attempt-badge badge-passed">Passed</span>
                                            <?php else: ?>
                                                <span class="attempt-badge badge-failed">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>