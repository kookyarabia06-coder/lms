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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }

        body {
            background: #f4f6f9;
        }

        .main-content-wrapper {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .result-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Header Section */
        .result-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .result-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: <?= $attempt['passed'] ? 'var(--success-gradient)' : 'var(--danger-gradient)' ?>;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .title-section h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }

        .title-section .course-name {
            color: #667eea;
            font-weight: 600;
            font-size: 16px;
        }

        .title-section .course-name i {
            margin-right: 8px;
        }

        .score-section {
            text-align: center;
        }

        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin: 0 auto 10px;
            position: relative;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .score-circle.passed {
            background: var(--success-gradient);
        }

        .score-circle.failed {
            background: var(--danger-gradient);
        }

        .score-circle::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            background: inherit;
            opacity: 0.3;
            z-index: -1;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.1; }
            100% { transform: scale(1); opacity: 0.3; }
        }

        .result-badge {
            display: inline-block;
            padding: 8px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
        }

        .badge-passed {
            background: #d4edda;
            color: #155724;
        }

        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0 0;
        }

        .stat-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.total { background: var(--primary-gradient); }
        .stat-icon.correct { background: var(--success-gradient); }
        .stat-icon.points { background: var(--warning-gradient); }
        .stat-icon.time { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-details h4 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #333;
        }

        .stat-details p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        /* Performance Meter */
        .performance-meter {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .meter-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .meter-title h4 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .meter-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .progress-bar-custom {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 10px;
            transition: width 1s ease;
            position: relative;
            animation: fillAnimation 1.5s ease-out;
        }

        @keyframes fillAnimation {
            from { width: 0; }
            to { width: <?= $attempt['score'] ?>%; }
        }

        .milestones {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .milestone {
            text-align: center;
            flex: 1;
        }

        .milestone-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #dee2e6;
            margin: 0 auto 5px;
        }

        .milestone-dot.passed {
            background: #28a745;
        }

        .milestone-label {
            font-size: 12px;
            color: #666;
        }

        .milestone-value {
            font-weight: 600;
            color: #333;
        }

        /* Answers List */
        .answers-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }

        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .answers-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .answer-item {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .answer-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .answer-header {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .answer-header.correct {
            border-bottom-color: #28a745;
        }

        .answer-header.incorrect {
            border-bottom-color: #dc3545;
        }

        .answer-header.partial {
            border-bottom-color: #ffc107;
        }

        .question-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .question-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .question-type {
            font-size: 13px;
            color: #666;
        }

        .score-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .score-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 13px;
        }

        .score-badge.correct {
            background: #d4edda;
            color: #155724;
        }

        .score-badge.incorrect {
            background: #f8d7da;
            color: #721c24;
        }

        .score-badge.partial {
            background: #fff3cd;
            color: #856404;
        }

        .expand-icon {
            color: #999;
            transition: all 0.3s;
        }

        .answer-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .answer-content.expanded {
            padding: 20px;
            max-height: 500px;
        }

        .answer-detail {
            margin-bottom: 15px;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .detail-value {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            font-weight: 500;
        }

        .detail-value.correct {
            background: #d4edda;
        }

        .detail-value.incorrect {
            background: #f8d7da;
        }

        .explanation-box {
            margin-top: 15px;
            padding: 15px;
            background: #e7f5ff;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .btn-retake {
            background: var(--warning-gradient);
            color: white;
            border: none;
        }

        .btn-retake:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 193, 7, 0.3);
            color: white;
        }

        .btn-retake.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Share Card */
        .share-card {
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
            border-radius: 16px;
            padding: 20px;
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .share-text {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .share-text i {
            font-size: 24px;
            color: #667eea;
        }

        .share-text span {
            font-weight: 600;
            color: #333;
        }

        .share-buttons {
            display: flex;
            gap: 10px;
        }

        .share-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .share-btn.facebook { background: #3b5998; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.linkedin { background: #0077b5; }
        .share-btn.email { background: #ea4335; }

        .share-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Print Styles */
        @media print {
            .lms-sidebar-container,
            .action-buttons,
            .share-card,
            .filter-buttons {
                display: none !important;
            }
            
            .main-content-wrapper {
                margin-left: 0;
                padding: 20px;
            }
            
            .result-header {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .answer-content {
                max-height: none !important;
                padding: 20px !important;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
                padding: 20px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .share-card {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Print button */
        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
            border: none;
            z-index: 1000;
            transition: all 0.3s;
        }

        .btn-print:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Print button -->
    <button class="btn-print" onclick="window.print()" title="Print Result">
        <i class="fas fa-print"></i>
    </button>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="main-content-wrapper">
        <div class="result-container">
            <!-- Result Header -->
            <div class="result-header">
                <div class="header-content">
                    <div class="title-section">
                        <h2><?= htmlspecialchars($attempt['assessment_title']) ?></h2>
                        <div class="course-name">
                            <i class="fas fa-book"></i>
                            <?= htmlspecialchars($attempt['course_title']) ?>
                        </div>
                    </div>
                    
                    <div class="score-section">
                        <div class="score-circle <?= $attempt['passed'] ? 'passed' : 'failed' ?>">
                            <?= round($attempt['score']) ?>%
                        </div>
                        <div class="result-badge <?= $attempt['passed'] ? 'badge-passed' : 'badge-failed' ?>">
                            <i class="fas <?= $attempt['passed'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                            <?= $attempt['passed'] ? 'PASSED' : 'FAILED' ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h4><?= $totalQuestions ?></h4>
                            <p>Total Questions</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon correct">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h4><?= $correctAnswers ?></h4>
                            <p>Correct Answers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon points">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-details">
                            <h4><?= $earnedPoints ?>/<?= $totalPoints ?></h4>
                            <p>Points Earned</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon time">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h4><?= date('M d, Y', strtotime($attempt['completed_at'])) ?></h4>
                            <p>Date Completed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Meter -->
            <div class="performance-meter">
                <div class="meter-title">
                    <h4>Performance Overview</h4>
                    <span class="meter-value"><?= round($attempt['score']) ?>%</span>
                </div>
                
                <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: <?= $attempt['score'] ?>%;"></div>
                </div>
                
                <div class="milestones">
                    <div class="milestone">
                        <div class="milestone-dot <?= $attempt['score'] >= 25 ? 'passed' : '' ?>"></div>
                        <div class="milestone-label">Poor</div>
                        <div class="milestone-value">25%</div>
                    </div>
                    <div class="milestone">
                        <div class="milestone-dot <?= $attempt['score'] >= 50 ? 'passed' : '' ?>"></div>
                        <div class="milestone-label">Fair</div>
                        <div class="milestone-value">50%</div>
                    </div>
                    <div class="milestone">
                        <div class="milestone-dot <?= $attempt['score'] >= 75 ? 'passed' : '' ?>"></div>
                        <div class="milestone-label">Good</div>
                        <div class="milestone-value">75%</div>
                    </div>
                    <div class="milestone">
                        <div class="milestone-dot <?= $attempt['score'] >= 90 ? 'passed' : '' ?>"></div>
                        <div class="milestone-label">Excellent</div>
                        <div class="milestone-value">90%</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Answers -->
            <div class="answers-section">
                <div class="section-title">
                    <h3>Detailed Answers</h3>
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterAnswers('all')">All</button>
                        <button class="filter-btn" onclick="filterAnswers('correct')">Correct</button>
                        <button class="filter-btn" onclick="filterAnswers('incorrect')">Incorrect</button>
                    </div>
                </div>

                <div class="answers-list" id="answersList">
                    <?php foreach ($answers as $index => $answer): 
                        $status = $answer['is_correct'] ? 'correct' : 'incorrect';
                        if ($answer['question_type'] == 'essay' && $answer['points_earned'] < $answer['points'] && $answer['points_earned'] > 0) {
                            $status = 'partial';
                        }
                    ?>
                    <div class="answer-item" data-status="<?= $status ?>">
                        <div class="answer-header <?= $status ?>" onclick="toggleAnswer(this)">
                            <div class="question-info">
                                <span class="question-number"><?= $index + 1 ?></span>
                                <div>
                                    <div class="fw-600">Question <?= $index + 1 ?></div>
                                    <div class="question-type">
                                        <i class="fas <?= $answer['question_type'] == 'essay' ? 'fa-pencil-alt' : 'fa-check-circle' ?> me-1"></i>
                                                                                <?= ucfirst(str_replace('_', ' ', $answer['question_type'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="score-indicator">
                                <span class="score-badge <?= $status ?>">
                                    <i class="fas <?= $status == 'correct' ? 'fa-check-circle' : ($status == 'partial' ? 'fa-adjust' : 'fa-times-circle') ?> me-1"></i>
                                    <?= $answer['points_earned'] ?>/<?= $answer['points'] ?> pts
                                </span>
                                <i class="fas fa-chevron-down expand-icon"></i>
                            </div>
                        </div>
                        
                        <div class="answer-content">
                            <div class="answer-detail">
                                <div class="detail-label">Question:</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($answer['question_text'])) ?></div>
                            </div>
                            
                            <div class="answer-detail">
                                <div class="detail-label">Your Answer:</div>
                                <div class="detail-value <?= $status ?>">
                                    <?php if ($answer['question_type'] == 'essay'): ?>
                                        <?= nl2br(htmlspecialchars($answer['essay_answer'] ?: 'No answer provided')) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($answer['selected_option_text'] ?: 'No answer selected') ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($answer['question_type'] != 'essay' && $answer['correct_answer']): ?>
                            <div class="answer-detail">
                                <div class="detail-label">Correct Answer:</div>
                                <div class="detail-value correct">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <?= htmlspecialchars($answer['correct_answer']) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($answer['question_type'] == 'essay'): ?>
                            <div class="explanation-box">
                                <i class="fas fa-info-circle me-2" style="color: #667eea;"></i>
                                <strong>Note:</strong> Essay questions are graded manually. The score shown (<?= $answer['points_earned'] ?>/<?= $answer['points'] ?>) may be adjusted by the instructor.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Share Card -->
            <div class="share-card">
                <div class="share-text">
                    <i class="fas fa-share-alt"></i>
                    <span>Share your achievement!</span>
                </div>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(BASE_URL . '/assessment_result.php?attempt_id=' . $attemptId) ?>" 
                       target="_blank" class="share-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=I just scored <?= $attempt['score'] ?>% on <?= urlencode($attempt['assessment_title']) ?>&url=<?= urlencode(BASE_URL . '/assessment_result.php?attempt_id=' . $attemptId) ?>" 
                       target="_blank" class="share-btn twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode(BASE_URL . '/assessment_result.php?attempt_id=' . $attemptId) ?>&title=<?= urlencode($attempt['assessment_title']) ?>&summary=I just scored <?= $attempt['score'] ?>% on this assessment" 
                       target="_blank" class="share-btn linkedin">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="mailto:?subject=My Assessment Result&body=I just scored <?= $attempt['score'] ?>% on <?= $attempt['assessment_title'] ?>. View my result here: <?= BASE_URL ?>/assessment_result.php?attempt_id=<?= $attemptId ?>" 
                       class="share-btn email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="course_view.php?id=<?= $attempt['course_id'] ?>" class="btn-action btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Course
                </a>
                
                <a href="assessments.php" class="btn-action btn-outline">
                    <i class="fas fa-list"></i>
                    All Assessments
                </a>
                
                <?php if (!$attempt['passed'] && $canRetake): ?>
                    <a href="take_assessment.php?assessment_id=<?= $attempt['assessment_id'] ?>" class="btn-action btn-retake">
                        <i class="fas fa-redo-alt"></i>
                        Retake Assessment
                    </a>
                <?php elseif (!$attempt['passed'] && !$canRetake): ?>
                    <button class="btn-action btn-retake disabled" disabled>
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
            const icon = header.querySelector('.expand-icon');
            
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
            const answers = document.querySelectorAll('.answer-item');
            const buttons = document.querySelectorAll('.filter-btn');
            
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
            const contents = document.querySelectorAll('.answer-content');
            const icons = document.querySelectorAll('.expand-icon');
            
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
            const contents = document.querySelectorAll('.answer-content');
            const icons = document.querySelectorAll('.expand-icon');
            
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
            hint.style.cssText = `
                position: fixed;
                bottom: 80px;
                right: 20px;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 8px 16px;
                border-radius: 30px;
                font-size: 12px;
                z-index: 999;
                backdrop-filter: blur(5px);
                border: 1px solid rgba(255,255,255,0.2);
            `;
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
            const incorrectAnswers = document.querySelectorAll('.answer-item[data-status="incorrect"]');
            incorrectAnswers.forEach(answer => {
                const header = answer.querySelector('.answer-header');
                const content = answer.querySelector('.answer-content');
                const icon = answer.querySelector('.expand-icon');
                
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