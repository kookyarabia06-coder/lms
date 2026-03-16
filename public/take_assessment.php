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
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            :root {
                --primary-color: #667eea;
                --primary-dark: #5a67d8;
                --success-color: #28a745;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
                --bg-color: #f0f2f5;
                --sidebar-width: 320px;
            }
            
            body {
                background: var(--bg-color);
                height: 100vh;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            }
            
            .assessment-fullscreen {
                display: flex;
                height: 100vh;
                width: 100vw;
            }
            
            /* Question Navigation Sidebar */
            .question-nav {
                width: var(--sidebar-width);
                background: white;
                border-right: 1px solid #e0e4e8;
                display: flex;
                flex-direction: column;
                height: 100vh;
                box-shadow: 2px 0 10px rgba(0,0,0,0.05);
                position: relative;
                z-index: 10;
            }
            
            .nav-header {
                padding: 25px 20px;
                border-bottom: 1px solid #e0e4e8;
                background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
            }
            
            .nav-header h4 {
                font-size: 18px;
                font-weight: 700;
                color: #333;
                margin-bottom: 5px;
                word-break: break-word;
            }
            
            .nav-header p {
                font-size: 13px;
                color: #666;
                margin: 0;
            }
            
            /* Progress Bar */
            .progress-container {
                margin-top: 15px;
            }
            
            .progress-info {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: #666;
                margin-bottom: 5px;
            }
            
            .progress-bar-custom {
                height: 8px;
                background: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, var(--primary-color), #764ba2);
                border-radius: 4px;
                transition: width 0.3s ease;
            }
            
            /* Timer */
            .timer {
                margin-top: 15px;
                padding: 15px;
                background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .timer-label {
                font-size: 12px;
                color: rgba(255,255,255,0.9);
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .timer-value {
                font-size: 32px;
                font-weight: 700;
                color: white;
                font-family: 'Courier New', monospace;
                line-height: 1;
            }
            
            .timer.warning {
                background: linear-gradient(135deg, var(--danger-color) 0%, #c82333 100%);
            }
            
            .timer.grace {
                background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            }
            
            .grace-message {
                margin-top: 10px;
                padding: 10px;
                background: #fff3cd;
                border-radius: 8px;
                font-size: 13px;
                color: #856404;
                text-align: center;
                font-weight: 600;
                animation: pulse 1s infinite;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.8; }
                100% { opacity: 1; }
            }
            
            .questions-list {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
            }
            
            .questions-list h5 {
                font-size: 14px;
                color: #666;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #e9ecef;
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
                border: 2px solid #dee2e6;
                border-radius: 10px;
                font-weight: 700;
                font-size: 16px;
                color: #495057;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                position: relative;
            }
            
            .question-btn:hover {
                background: #e9ecef;
                transform: translateY(-2px);
                box-shadow: 0 5px 10px rgba(0,0,0,0.1);
                color: var(--primary-color);
                border-color: var(--primary-color);
            }
            
            .question-btn.active {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
            }
            
            .question-btn.answered {
                background: #d4edda;
                border-color: var(--success-color);
                color: #155724;
            }
            
            .question-btn.answered.active {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
            }
            
            .question-btn.answered::after {
                content: '✓';
                position: absolute;
                top: 2px;
                right: 2px;
                font-size: 10px;
                color: var(--success-color);
            }
            
            .nav-footer {
                padding: 20px;
                border-top: 2px solid #e0e4e8;
                background: #f8f9fa;
            }
            
            .btn-submit {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, var(--success-color) 0%, #20c997 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-weight: 700;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
            }
            
            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
            }
            
            .btn-submit i {
                margin-right: 8px;
            }
            
            /* Main Question Area */
            .question-area {
                flex: 1;
                padding: 30px;
                overflow-y: auto;
                background: var(--bg-color);
            }
            
            .question-container {
                max-width: 1000px;
                margin: 0 auto;
            }
            
            .question-card {
                background: white;
                border-radius: 20px;
                padding: 35px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                border: 1px solid rgba(0,0,0,0.05);
                animation: fadeIn 0.5s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }
            
            .question-number-badge {
                display: inline-flex;
                align-items: center;
                padding: 8px 20px;
                background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
                border-radius: 50px;
                font-size: 14px;
                font-weight: 600;
                color: white;
                box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2);
            }
            
            .question-type-badge {
                padding: 6px 15px;
                background: #e9ecef;
                border-radius: 50px;
                font-size: 13px;
                font-weight: 600;
                color: #495057;
            }
            
            .question-type-badge i {
                margin-right: 5px;
                color: var(--primary-color);
            }
            
            .question-text {
                font-size: 22px;
                font-weight: 600;
                color: #333;
                margin-bottom: 35px;
                line-height: 1.6;
            }
            
            .options-container {
                margin-top: 25px;
            }
            
            .option-item {
                margin-bottom: 15px;
                animation: slideIn 0.3s ease;
                animation-fill-mode: both;
            }
            
            .option-item:nth-child(1) { animation-delay: 0.1s; }
            .option-item:nth-child(2) { animation-delay: 0.2s; }
            .option-item:nth-child(3) { animation-delay: 0.3s; }
            .option-item:nth-child(4) { animation-delay: 0.4s; }
            
            @keyframes slideIn {
                from { opacity: 0; transform: translateX(-10px); }
                to { opacity: 1; transform: translateX(0); }
            }
            
            .option-label {
                display: flex;
                align-items: center;
                padding: 18px 25px;
                background: #f8f9fa;
                border: 2px solid #dee2e6;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .option-label:hover {
                background: #e9ecef;
                border-color: var(--primary-color);
                transform: translateX(5px);
            }
            
            .option-label.selected {
                background: #e7f5ff;
                border-color: var(--primary-color);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
            }
            
            .option-marker {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background: white;
                border: 2px solid #dee2e6;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 16px;
                margin-right: 20px;
                transition: all 0.2s;
            }
            
            .option-label.selected .option-marker {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
            }
            
            .option-text {
                flex: 1;
                font-size: 17px;
                font-weight: 500;
            }
            
            .navigation-buttons {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 30px;
                gap: 15px;
            }
            
            .nav-btn {
                padding: 12px 30px;
                border: 2px solid #dee2e6;
                background: white;
                border-radius: 50px;
                font-weight: 600;
                font-size: 15px;
                color: #495057;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 1;
            }
            
            .nav-btn:hover:not(.disabled) {
                background: #f8f9fa;
                border-color: var(--primary-color);
                color: var(--primary-color);
                transform: translateY(-2px);
            }
            
            .nav-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                pointer-events: none;
                background: #e9ecef;
            }
            
            .nav-btn.primary {
                background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
                color: white;
                border: none;
                flex: 1.5;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            }
            
            .nav-btn.primary:hover:not(.disabled) {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
                color: white;
            }
            
            .nav-btn i {
                margin: 0 8px;
            }
            
            /* Loading overlay */
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255,255,255,0.9);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(5px);
            }
            
            .loading-spinner {
                text-align: center;
            }
            
            .spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid var(--primary-color);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 15px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Warning message */
            .leave-warning {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--danger-color);
                color: white;
                padding: 12px 24px;
                border-radius: 50px;
                font-weight: 600;
                z-index: 10000;
                display: none;
                align-items: center;
                gap: 10px;
                box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
                animation: slideInDown 0.3s ease;
            }
            
            @keyframes slideInDown {
                from { top: -100px; opacity: 0; }
                to { top: 20px; opacity: 1; }
            }
            
            .shortcut-hint {
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 8px 16px;
                border-radius: 50px;
                font-size: 12px;
                z-index: 9997;
                backdrop-filter: blur(5px);
                border: 1px solid rgba(255,255,255,0.2);
            }
            
            .grace-timer {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #ffc107;
                color: #212529;
                padding: 15px 25px;
                border-radius: 50px;
                font-weight: 700;
                font-size: 16px;
                z-index: 9998;
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
                animation: pulse 1s infinite;
            }
            
            @media (max-width: 768px) {
                .question-nav {
                    width: 280px;
                }
                
                .question-area {
                    padding: 20px;
                }
                
                .question-card {
                    padding: 25px;
                }
                
                .question-text {
                    font-size: 18px;
                }
                
                .option-label {
                    padding: 15px 20px;
                }
                
                .navigation-buttons {
                    flex-wrap: wrap;
                }
            }
        </style>
    </head>
    <body>
        <div class="leave-warning" id="leaveWarning">
            <i class="fas fa-exclamation-triangle"></i>
            Don't leave! Your progress will be lost if you exit.
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p id="loadingMessage">Processing...</p>
            </div>
        </div>
        
        <div class="grace-timer" id="graceTimer" style="display: none;">
            <i class="fas fa-hourglass-half me-2"></i>
            <span id="graceTimerText">Auto-submitting in 60s</span>
        </div>
        
        <div class="shortcut-hint" id="shortcutHint">
            <i class="fas fa-keyboard"></i>
            <span>← Previous | → Next | Ctrl+S Save</span>
        </div>

        <div class="assessment-fullscreen">
            <!-- Question Navigation Sidebar -->
            <div class="question-nav">
                <div class="nav-header">
                    <h4><?= htmlspecialchars($assessment['title']) ?></h4>
                    <p><?= htmlspecialchars($assessment['course_title']) ?></p>
                    
                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress-info">
                            <span>Progress</span>
                            <span id="progressText">0/<?= $totalQuestions ?> (0%)</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                        </div>
                    </div>
                    
                    <!-- Timer -->
                    <?php if ($timeLimit && $timeLimit > 0): ?>
                    <div class="timer" id="timer">
                        <div class="timer-label">Time Remaining</div>
                        <div class="timer-value" id="timerValue"><?= str_pad($timeLimit, 2, '0', STR_PAD_LEFT) ?>:00</div>
                    </div>
                    <div id="graceMessage" class="grace-message" style="display: none;"></div>
                    <?php endif; ?>
                </div>
                
                <div class="questions-list">
                    <h5><i class="fas fa-list me-2"></i>Question Navigator</h5>
                    <div class="question-grid" id="questionGrid">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        body {
            background: #f4f6f9;
        }

        .main-content-wrapper {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .assessment-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .welcome-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header-gradient {
            background: var(--primary-gradient);
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .card-header-gradient h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
        }

        .card-header-gradient p {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
            position: relative;
        }

        .card-body {
            padding: 40px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .info-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }

        .info-icon.time { background: var(--primary-gradient); }
        .info-icon.passing { background: var(--success-gradient); }
        .info-icon.attempts { background: var(--warning-gradient); }
        .info-icon.questions { background: var(--danger-gradient); }

        .info-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .info-label {
            color: #666;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .warning-box i {
            font-size: 28px;
            color: #ffc107;
        }

        .warning-box-content {
            flex: 1;
        }

        .warning-box-title {
            font-weight: 700;
            color: #856404;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .warning-box-text {
            color: #856404;
            margin: 0;
            font-size: 14px;
        }

        .attempts-warning {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px 20px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .attempts-warning i {
            font-size: 24px;
            color: #dc3545;
        }

        .attempts-warning-content {
            flex: 1;
        }

        .attempts-warning-title {
            font-weight: 700;
            color: #721c24;
            margin-bottom: 3px;
        }

        .attempts-warning-text {
            color: #721c24;
            font-size: 14px;
            margin: 0;
        }

        .btn-start {
            background: var(--primary-gradient);
            color: white;
            padding: 16px 40px;
            font-size: 18px;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn-start::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-start:hover::before {
            left: 100%;
        }

        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-start.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
            background: #6c757d;
        }

        .btn-start i {
            margin-right: 10px;
        }

        .previous-attempts {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .attempt-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-passed {
            background: #d4edda;
            color: #155724;
        }

        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
                padding: 20px;
            }
            
            .card-header-gradient {
                padding: 30px 20px;
            }
            
            .card-header-gradient h1 {
                font-size: 24px;
            }
            
            .card-body {
                padding: 30px 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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