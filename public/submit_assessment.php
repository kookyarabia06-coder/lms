<?php

// Add at the very top of submit_assessment.php
error_log("=== SUBMIT ASSESSMENT CALLED ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("RAW INPUT: " . file_get_contents('php://input'));


// ... rest of your code

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['attempt_id']) || !isset($input['answers'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$attemptId = (int)$input['attempt_id'];
$answers = $input['answers'];

try {
    $pdo->beginTransaction();
    
    // Verify this attempt belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM assessment_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->execute([$attemptId, $u['id']]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        throw new Exception('Invalid attempt');
    }
    
    // Get assessment details
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id = ?");
    $stmt->execute([$attempt['assessment_id']]);
    $assessment = $stmt->fetch();
    
    if (!$assessment) {
        throw new Exception('Assessment not found');
    }
    
    // Get all questions and correct answers
    $stmt = $pdo->prepare("
        SELECT q.id, q.points, o.id as correct_option_id
        FROM assessment_questions q
        LEFT JOIN assessment_options o ON q.id = o.question_id AND o.is_correct = 1
        WHERE q.assessment_id = ?
    ");
    $stmt->execute([$assessment['id']]);
    $questionsData = $stmt->fetchAll();
    
    $questions = [];
    foreach ($questionsData as $qd) {
        if (!isset($questions[$qd['id']])) {
            $questions[$qd['id']] = [
                'points' => $qd['points'],
                'correct_option_id' => null
            ];
        }
        if ($qd['correct_option_id']) {
            $questions[$qd['id']]['correct_option_id'] = $qd['correct_option_id'];
        }
    }
    
    // Save answers and calculate score
    $totalPoints = 0;
    $earnedPoints = 0;
    
    foreach ($answers as $answer) {
        $questionId = (int)$answer['question_id'];
        $selectedOptionId = (int)$answer['selected_option_id'];
        
        // Check if correct
        $isCorrect = 0;
        if (isset($questions[$questionId]) && $questions[$questionId]['correct_option_id'] == $selectedOptionId) {
            $isCorrect = 1;
        }
        
        // Get points for this question
        $points = $questions[$questionId]['points'] ?? 1;
        $totalPoints += $points;
        
        if ($isCorrect) {
            $earnedPoints += $points;
        }
        
        // Save answer
        $stmt = $pdo->prepare("
            INSERT INTO assessment_answers (attempt_id, question_id, selected_option_id, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$attemptId, $questionId, $selectedOptionId, $isCorrect, $isCorrect ? $points : 0]);
    }
    
    // Calculate score percentage
    $scorePercentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
    $passed = ($scorePercentage >= $assessment['passing_score']) ? 1 : 0;
    
    // Update attempt
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts 
        SET status = 'completed', 
            completed_at = NOW(), 
            score = ?,
            passed = ?
        WHERE id = ?
    ");
    $stmt->execute([$scorePercentage, $passed, $attemptId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'attempt_id' => $attemptId]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Submit Assessment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error submitting assessment']);
}