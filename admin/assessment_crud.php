<?php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

// Verify course exists and user has permission
if (!$course_id) {
    $_SESSION['error_message'] = 'Course ID is required';
    header('Location: courses_crud.php');
    exit;
}

// Check if user can modify this course
function canModifyCourse($course_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true; 
    }

    $stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = :id");
    $stmt->execute([':id' => $course_id]);
    $course = $stmt->fetch();

    return $course && $course['proponent_id'] == $_SESSION['user']['id'];
}

if (!canModifyCourse($course_id, $pdo)) {
    http_response_code(403);
    exit('Access denied: You can only manage assessments for your own courses');
}

// Get course info
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error_message'] = 'Course not found';
    header('Location: courses_crud.php');
    exit;
}

/**
 * Save assessment function
 */
function saveAssessment($course_id, $data, $pdo) {
    // Check if assessment already exists for this course
    $stmt = $pdo->prepare("SELECT id FROM assessments WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing assessment
        $assessment_id = $existing['id'];
        $stmt = $pdo->prepare("
            UPDATE assessments 
            SET title = ?, description = ?, passing_score = ?, time_limit = ?, attempts_allowed = ?, updated_at = NOW()
            WHERE id = ? AND course_id = ?
        ");
        $stmt->execute([
            $data['assessment_title'],
            $data['assessment_description'],
            $data['passing_score'] ?? 70,
            $data['time_limit'] ?? null,
            $data['attempts_allowed'] ?? 0,
            $assessment_id,
            $course_id
        ]);
    } else {
        // Insert new assessment
        $stmt = $pdo->prepare("
            INSERT INTO assessments (course_id, title, description, passing_score, time_limit, attempts_allowed, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $course_id,
            $data['assessment_title'],
            $data['assessment_description'],
            $data['passing_score'] ?? 70,
            $data['time_limit'] ?? null,
            $data['attempts_allowed'] ?? 0
        ]);
        $assessment_id = $pdo->lastInsertId();
    }
    
    // Save questions
    saveQuestions($assessment_id, $data, $pdo);
    
    return $assessment_id;
}

/**
 * Save questions function
 */
function saveQuestions($assessment_id, $data, $pdo) {
    // Delete existing questions
    $stmt = $pdo->prepare("DELETE FROM assessment_questions WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    
    if (isset($data['questions']) && is_array($data['questions'])) {
        foreach ($data['questions'] as $index => $question) {
            // Skip empty questions
            if (empty($question['text'])) continue;
            
            // Insert question
            $stmt = $pdo->prepare("
                INSERT INTO assessment_questions (assessment_id, question_text, question_type, points, order_number)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $assessment_id,
                $question['text'],
                $question['type'],
                $question['points'] ?? 1,
                $index
            ]);
            
            $question_id = $pdo->lastInsertId();
            
            // Save options for multiple choice
            if ($question['type'] == 'multiple_choice' && isset($question['options'])) {
                foreach ($question['options'] as $opt_index => $option) {
                    if (empty($option['text'])) continue;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO assessment_options (question_id, option_text, is_correct, order_number)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $question_id,
                        $option['text'],
                        isset($option['is_correct']) ? 1 : 0,
                        $opt_index
                    ]);
                }
            }
            
            // Save for true/false
            if ($question['type'] == 'true_false' && isset($question['correct_answer'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO assessment_options (question_id, option_text, is_correct, order_number)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $question_id,
                    $question['correct_answer'] == 'true' ? 'True' : 'False',
                    1,
                    0
                ]);
            }
        }
    }
}

/* =========================
HANDLE POST REQUESTS
========================= */

// Handle ASSESSMENT SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    saveAssessment($course_id, $_POST, $pdo);
    $_SESSION['success_message'] = 'Assessment saved successfully!';
    header('Location: assessment_crud.php?course_id=' . $course_id);
    exit;
}

// Handle ASSESSMENT DELETION
if ($action === 'delete_assessment' && $id) {
    $stmt = $pdo->prepare("DELETE FROM assessments WHERE id = ? AND course_id = ?");
    $stmt->execute([$id, $course_id]);
    $_SESSION['success_message'] = 'Assessment deleted successfully!';
    header('Location: assessment_crud.php?course_id=' . $course_id);
    exit;
}

/* =========================
GET DATA FOR DISPLAY
========================= */

// Fetch the single assessment for this course (if exists)
$stmt = $pdo->prepare("
    SELECT a.*, 
    (SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = a.id) as question_count
    FROM assessments a 
    WHERE a.course_id = ? 
    LIMIT 1
");
$stmt->execute([$course_id]);
$assessment = $stmt->fetch();

// Fetch questions if assessment exists
$assessment_questions = [];
if ($assessment) {
    // First get all questions
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
            SELECT id, option_text as text, is_correct 
            FROM assessment_options 
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

// Get session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear session data
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assessment Management - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        /* Assessment Form Styles */
        .assessment-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .assessment-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .assessment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .assessment-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
        }
        
        .assessment-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .question-form {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .question-text {
            font-weight: 600;
            color: #495057;
        }
        
        .remove-question {
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .remove-question:hover {
            color: #bd2130;
        }
        
        .options-container {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .option-item {
            background: #f1f3f5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        
        .btn-add-question {
            background: #e7f5ff;
            color: #1971c2;
            border: 1px dashed #4dabf7;
            margin-top: 10px;
        }
        
        .btn-add-question:hover {
            background: #d0ebff;
            color: #1864ab;
        }
        
        .delete-assessment-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>
            <a href="courses_crud.php?act=edit&id=<?= $course_id ?>" class="text-decoration-none text-dark me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            Assessment for: <?= htmlspecialchars($course['title']) ?>
        </h3>
        <span class="badge bg-info">Course ID: <?= $course['id'] ?></span>
    </div>

    <!-- Display success/error messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Assessment Section -->
    <div class="card p-4 mb-4 shadow-sm bg-white rounded">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                Course Assessment
            </h5>
            <?php if ($assessment): ?>
                <a href="?course_id=<?= $course_id ?>&action=delete_assessment&id=<?= $assessment['id'] ?>" 
                   class="btn btn-outline-danger btn-sm"
                   onclick="return confirm('Delete this assessment? All questions will also be deleted.')">
                    <i class="fas fa-trash"></i> Delete Assessment
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Assessment Form -->
        <div id="assessmentForm" class="assessment-section">
            <form method="post">
                <input type="hidden" name="save_assessment" value="1">
                <?php if ($assessment): ?>
                    <input type="hidden" name="assessment_id" value="<?= $assessment['id'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="fw-bold">Assessment Title</label>
                        <input type="text" name="assessment_title" class="form-control" 
                               value="<?= htmlspecialchars($assessment['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Passing Score (%)</label>
                        <input type="number" name="passing_score" class="form-control" 
                               value="<?= $assessment['passing_score'] ?? 70 ?>" min="0" max="100">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Description</label>
                    <textarea name="assessment_description" class="form-control" rows="2"><?= htmlspecialchars($assessment['description'] ?? '') ?></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Time Limit (minutes)</label>
                        <input type="number" name="time_limit" class="form-control" 
                               value="<?= $assessment['time_limit'] ?? '' ?>" placeholder="Leave empty for no limit">
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Attempts Allowed</label>
                        <input type="number" name="attempts_allowed" class="form-control" 
                               value="<?= $assessment['attempts_allowed'] ?? '' ?>" min="0" placeholder="0 for unlimited">
                    </div>
                </div>

                <hr class="my-3">

                <!-- Questions Container -->
                <div id="questionsContainer" class="mb-3">
                    <!-- Questions will be loaded here dynamically -->
                </div>

                <!-- Add Question Button -->
                <button type="button" class="btn btn-add-question w-100" onclick="addQuestion()">
                    <i class="fas fa-plus me-2"></i>Add New Question
                </button>

                <hr class="my-3">

                <button type="submit" class="btn btn-primary">Save Assessment</button>
                <a href="courses_crud.php?act=edit&id=<?= $course_id ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let questionCount = 0;
let existingQuestions = <?= json_encode($assessment_questions) ?>;

function loadExistingQuestions() {
    existingQuestions.forEach((q, index) => {
        // Map database fields to expected format
        const questionData = {
            text: q.question_text,
            type: q.question_type,
            points: q.points,
            options: q.options || []
        };
        addQuestion(questionData, index);
    });
}

function addQuestion(questionData = null, index = null) {
    const container = document.getElementById('questionsContainer');
    const qIndex = index !== null ? index : questionCount;
    const question = questionData || { text: '', type: 'multiple_choice', points: 1, options: [] };
    
    let optionsHtml = '';
    if (question.type === 'multiple_choice') {
        // Create 4 options, filling in existing ones if available
        for (let i = 0; i < 4; i++) {
            const option = question.options && question.options[i] ? question.options[i] : { text: '', is_correct: false };
            optionsHtml += `
                <div class="option-item mb-2" id="option_${qIndex}_${i}">
                    <div class="row">
                        <div class="col-8">
                            <input type="text" name="questions[${qIndex}][options][${i}][text]" 
                                   class="form-control" placeholder="Option ${i+1}" 
                                   value="${escapeHtml(option.text || '')}" 
                                   onchange="checkDuplicateOptions(${qIndex}, ${i})"
                                   required>
                            <small class="text-danger option-error" id="option_error_${qIndex}_${i}" style="display: none;">Duplicate option</small>
                        </div>
                        <div class="col-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="questions[${qIndex}][options][${i}][is_correct]"
                                       ${option.is_correct ? 'checked' : ''}>
                                <label class="form-check-label">Correct</label>
                            </div>
                        </div>
                        <div class="col-1">
                            ${i >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeOption(${qIndex}, ${i})">
                                <i class="fas fa-times"></i>
                            </span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
    }
    
    const questionHtml = `
        <div class="question-form" id="question_${qIndex}">
            <div class="question-header">
                <span class="question-text">Question ${qIndex + 1}</span>
                <span class="remove-question" onclick="removeQuestion(${qIndex})">
                    <i class="fas fa-times"></i>
                </span>
            </div>
            
            <div class="mb-3">
                <label>Question Text</label>
                <input type="text" name="questions[${qIndex}][text]" class="form-control" 
                       value="${escapeHtml(question.text || '')}" required>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Question Type</label>
                    <select name="questions[${qIndex}][type]" class="form-control" 
                            onchange="toggleQuestionOptions(${qIndex}, this.value)">
                        <option value="multiple_choice" ${question.type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                        <option value="true_false" ${question.type === 'true_false' ? 'selected' : ''}>True/False</option>
                        <option value="essay" ${question.type === 'essay' ? 'selected' : ''}>Essay</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Points</label>
                    <input type="number" name="questions[${qIndex}][points]" class="form-control" 
                           value="${question.points || 1}" min="1">
                </div>
            </div>
            
            <div id="options_${qIndex}" class="options-container" 
                 style="display: ${question.type === 'multiple_choice' ? 'block' : 'none'};">
                ${optionsHtml}
            </div>
            
            <div id="true_false_${qIndex}" style="display: ${question.type === 'true_false' ? 'block' : 'none'};">
                <label>Correct Answer</label>
                <select name="questions[${qIndex}][correct_answer]" class="form-control">
                    <option value="true" ${question.correct_answer === 'true' ? 'selected' : ''}>True</option>
                    <option value="false" ${question.correct_answer === 'false' ? 'selected' : ''}>False</option>
                </select>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', questionHtml);
    
    if (questionData === null) {
        questionCount++;
    } else {
        questionCount = Math.max(questionCount, qIndex + 1);
    }
}

function checkDuplicateOptions(questionId, currentOptionIndex) {
    const currentInput = document.querySelector(`#option_${questionId}_${currentOptionIndex} input[type="text"]`);
    const currentValue = currentInput.value.trim().toLowerCase();
    const errorElement = document.getElementById(`option_error_${questionId}_${currentOptionIndex}`);
    
    if (!currentValue) {
        errorElement.style.display = 'none';
        return;
    }
    
    // Check all options in the same question
    let hasDuplicate = false;
    for (let i = 0; i < 4; i++) {
        if (i === currentOptionIndex) continue;
        
        const optionInput = document.querySelector(`#option_${questionId}_${i} input[type="text"]`);
        if (optionInput && optionInput.value.trim().toLowerCase() === currentValue) {
            hasDuplicate = true;
            break;
        }
    }
    
    if (hasDuplicate) {
        errorElement.style.display = 'block';
        currentInput.classList.add('is-invalid');
    } else {
        errorElement.style.display = 'none';
        currentInput.classList.remove('is-invalid');
    }
}

function validateForm() {
    // Check for duplicate options in all multiple choice questions
    const questions = document.querySelectorAll('[id^="question_"]');
    
    for (let q = 0; q < questions.length; q++) {
        const question = questions[q];
        const questionId = question.id.split('_')[1];
        const typeSelect = question.querySelector('select[name^="questions["][name$="[type]"]');
        
        if (typeSelect && typeSelect.value === 'multiple_choice') {
            const optionValues = [];
            let hasDuplicate = false;
            
            // Collect all option values
            for (let i = 0; i < 4; i++) {
                const optionInput = document.querySelector(`#option_${questionId}_${i} input[type="text"]`);
                if (optionInput) {
                    const value = optionInput.value.trim().toLowerCase();
                    if (value) {
                        if (optionValues.includes(value)) {
                            hasDuplicate = true;
                            // Show error for this duplicate
                            const errorElement = document.getElementById(`option_error_${questionId}_${i}`);
                            if (errorElement) {
                                errorElement.style.display = 'block';
                                optionInput.classList.add('is-invalid');
                            }
                        } else {
                            optionValues.push(value);
                        }
                    }
                }
            }
            
            if (hasDuplicate) {
                alert('Please fix duplicate options in Question ' + (q + 1));
                return false;
            }
        }
    }
    
    return true;
}

function removeQuestion(id) {
    document.getElementById(`question_${id}`).remove();
    renumberQuestions();
}

function renumberQuestions() {
    const container = document.getElementById('questionsContainer');
    const questions = container.children;
    
    for (let i = 0; i < questions.length; i++) {
        const question = questions[i];
        const questionId = question.id.split('_')[1];
        const newIndex = i;
        
        // Update question text
        const questionText = question.querySelector('.question-text');
        if (questionText) {
            questionText.textContent = `Question ${newIndex + 1}`;
        }
        
        // Update all input names
        const inputs = question.querySelectorAll('[name^="questions["]');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            const updatedName = name.replace(`questions[${questionId}]`, `questions[${newIndex}]`);
            input.setAttribute('name', updatedName);
        });
        
        // Update option IDs and their associated elements
        const options = question.querySelectorAll('[id^="option_"]');
        options.forEach(option => {
            const oldId = option.id;
            const parts = oldId.split('_');
            if (parts.length === 4) {
                const optionIndex = parts[3];
                option.id = `option_${newIndex}_${optionIndex}`;
                
                // Update error message ID if it exists
                const errorElement = option.querySelector('.option-error');
                if (errorElement) {
                    errorElement.id = `option_error_${newIndex}_${optionIndex}`;
                }
            }
        });
        
        // Update question ID
        question.id = `question_${newIndex}`;
    }
    
    questionCount = questions.length;
}

function toggleQuestionOptions(questionId, type) {
    const optionsDiv = document.getElementById(`options_${questionId}`);
    const trueFalseDiv = document.getElementById(`true_false_${questionId}`);
    
    if (type === 'multiple_choice') {
        optionsDiv.style.display = 'block';
        trueFalseDiv.style.display = 'none';
        // Ensure we have 4 options
        if (optionsDiv.children.length === 0) {
            for (let i = 0; i < 4; i++) {
                addOption(questionId, i);
            }
        }
    } else if (type === 'true_false') {
        optionsDiv.style.display = 'none';
        trueFalseDiv.style.display = 'block';
    } else {
        optionsDiv.style.display = 'none';
        trueFalseDiv.style.display = 'none';
    }
}

function addOption(questionId, optionIndex) {
    const optionsDiv = document.getElementById(`options_${questionId}`);
    
    const optionHtml = `
        <div class="option-item mb-2" id="option_${questionId}_${optionIndex}">
            <div class="row">
                <div class="col-8">
                    <input type="text" name="questions[${questionId}][options][${optionIndex}][text]" 
                           class="form-control" placeholder="Option ${optionIndex+1}" 
                           onchange="checkDuplicateOptions(${questionId}, ${optionIndex})"
                           required>
                    <small class="text-danger option-error" id="option_error_${questionId}_${optionIndex}" style="display: none;">Duplicate option</small>
                </div>
                <div class="col-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               name="questions[${questionId}][options][${optionIndex}][is_correct]">
                        <label class="form-check-label">Correct</label>
                    </div>
                </div>
                <div class="col-1">
                    ${optionIndex >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeOption(${questionId}, ${optionIndex})">
                        <i class="fas fa-times"></i>
                    </span>` : ''}
                </div>
            </div>
        </div>
    `;
    
    optionsDiv.insertAdjacentHTML('beforeend', optionHtml);
}

function removeOption(questionId, optionId) {
    if (optionId >= 4) {
        document.getElementById(`option_${questionId}_${optionId}`).remove();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Load existing questions if any
    if (existingQuestions && existingQuestions.length > 0) {
        loadExistingQuestions();
    } else {
        // Add one default question for new assessment
        addQuestion();
    }
    
    // Add form validation
    const form = document.querySelector('form[method="post"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }
});

// When clicking the back button, set a flag that we're returning with unsaved course changes
document.addEventListener('DOMContentLoaded', function() {
    const backButton = document.querySelector('a[href*="courses_crud.php?act=edit"]');
    if (backButton) {
        backButton.addEventListener('click', function(e) {
            // Set a flag that we're returning from assessment management
            sessionStorage.setItem('return_from_assessment', 'true');
            // Allow the link to continue
            return true;
        });
    }
});
</script>

</body>
</html>