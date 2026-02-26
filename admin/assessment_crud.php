<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];
$message = '';
$error = '';

// Handle CREATE button (save assessment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_assessment') {
        // Validate required fields
        if (empty($_POST['assessment_title']) || empty($_POST['questions'])) {
            $error = 'Assessment title and at least one question are required.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insert assessment
                $stmt = $pdo->prepare("INSERT INTO assessment (title, description, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->execute([$_POST['assessment_title'], $_POST['assessment_description'] ?? '', $userId]);
                $assessmentId = $pdo->lastInsertId();
                
                // Insert questions and options
                foreach ($_POST['questions'] as $index => $question) {
                    if (empty($question['text'])) continue;
                    
                    $stmt = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, question_text, question_order, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$assessmentId, $question['text'], $index + 1]);
                    $questionId = $pdo->lastInsertId();
                    
                    // Insert options
                    foreach ($question['options'] as $optIndex => $option) {
                        if (empty($option['text'])) continue;
                        
                        $isCorrect = (isset($question['correct_answer']) && $question['correct_answer'] == $optIndex) ? 1 : 0;
                        
                        $stmt = $pdo->prepare("INSERT INTO assessment_options (question_id, option_text, is_correct, option_order, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$questionId, $option['text'], $isCorrect, $optIndex + 1]);
                    }
                }
                
                $pdo->commit();
                $message = 'Assessment saved successfully!';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error saving assessment: ' . $e->getMessage();
            }
        }
    }
    
    // Handle PREVIEW button
    if ($_POST['action'] === 'preview_assessment') {
        $_SESSION['preview_data'] = $_POST;
        header('Location: assessment_preview.php');
        exit;
    }
}

// Get existing assessments for display
$stmt = $pdo->query("SELECT * FROM assessment ORDER BY created_at DESC");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LMS Assessment Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/assessment.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
</head>
<body>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="asmpg-main-container">
        <?php if ($message): ?>
            <div class="toast-message alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="toast-message alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="asmpg-header">
            <h1 class="asmpg-title">Assessment Management</h1>
        </div>
        
        <div class="centered-content">
            <div class="asmpg-button-group">
                <button type="button" id="createBtn" class="asmpg-btn-create">CREATE</button> 
                <button type="button" id="previewBtn" class="asmpg-btn-preview">PREVIEW</button>
            </div>

            <form method="POST" action="" id="assessmentForm" class="asmpg-form">
                <input type="hidden" name="action" id="formAction" value="save_assessment">
                
                <div class="mb-4">
                    <label for="assessment_title" class="asmpg-label">Assessment Title</label>
                    <input type="text" name="assessment_title" id="assessment_title" class="asmpg-input" placeholder="Enter assessment title" required>
                </div>
                
                <div class="mb-4">
                    <label for="assessment_description" class="asmpg-label">Description (Optional)</label>
                    <textarea name="assessment_description" id="assessment_description" class="asmpg-input" rows="3" placeholder="Enter assessment description"></textarea>
                </div>

                <div id="questionsContainer">
                    <!-- Questions will be added here dynamically -->
                </div>

                <button type="button" id="addQuestionBtn" class="asmpg-btn-add">
                    <i class="fas fa-plus-circle"></i> ADD QUESTION
                </button>
            </form>
        </div>
    </div>

    <script>
        let questionCount = 0;

        // Question template
        function getQuestionTemplate(index) {
            return `
                <div class="asmpg-question-container" data-question-index="${index}">
                    <h3 class="asmpg-question-header">Question ${index + 1} of 25:</h3>
                    
                    <label class="asmpg-label">Question Text</label>
                    <input type="text" name="questions[${index}][text]" class="asmpg-input" placeholder="Enter your question" required>

                    <div class="mb-3">
                        <label class="asmpg-label">Options (Select the correct answer)</label>
                        ${getOptionsTemplate(index)}
                    </div>

                    <button type="button" class="asmpg-btn-remove" onclick="removeQuestion(this)" style="background: #f72585; color: white; border: none; padding: 8px 16px; border-radius: 8px; margin-top: 10px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Remove Question
                    </button>
                </div>
            `;
        }

        function getOptionsTemplate(questionIndex) {
            const letters = ['A', 'B', 'C', 'D'];
            let html = '';
            
            for (let i = 0; i < 4; i++) {
                html += `
                    <div class="asmpg-option-item">
                        <input type="radio" name="questions[${questionIndex}][correct_answer]" value="${i}" id="q${questionIndex}_opt${i}" class="asmpg-radio" required>
                        <span class="asmpg-option-letter">${letters[i]}</span>
                        <input type="text" name="questions[${questionIndex}][options][${i}][text]" class="asmpg-option-input" placeholder="Option ${letters[i]}" required style="flex: 1; margin-left: 10px; padding: 8px; border: 1px solid #dee2e6; border-radius: 8px;">
                    </div>
                `;
            }
            
            return html;
        }

        // Add new question
        document.getElementById('addQuestionBtn').addEventListener('click', function() {
            if (questionCount >= 25) {
                alert('Maximum 25 questions allowed per assessment!');
                return;
            }
            
            const container = document.getElementById('questionsContainer');
            container.insertAdjacentHTML('beforeend', getQuestionTemplate(questionCount));
            questionCount++;
            
            // Add event listeners to new radio buttons
            attachRadioListeners();
        });

        // Remove question
        function removeQuestion(button) {
            const container = button.closest('.asmpg-question-container');
            if (container && confirm('Are you sure you want to remove this question?')) {
                container.remove();
                questionCount--;
                // Renumber remaining questions
                renumberQuestions();
            }
        }

        // Renumber questions after removal
        function renumberQuestions() {
            const containers = document.querySelectorAll('.asmpg-question-container');
            containers.forEach((container, index) => {
                // Update header
                const header = container.querySelector('.asmpg-question-header');
                if (header) {
                    header.textContent = `Question ${index + 1} of 25:`;
                }
                
                // Update input names
                container.dataset.questionIndex = index;
                
                // Update question text input name
                const textInput = container.querySelector('input[name^="questions"][name$="[text]"]');
                if (textInput) {
                    textInput.name = `questions[${index}][text]`;
                }
                
                // Update radio buttons and option inputs
                const radios = container.querySelectorAll('input[type="radio"]');
                radios.forEach((radio, optIndex) => {
                    radio.name = `questions[${index}][correct_answer]`;
                    radio.value = optIndex;
                    radio.id = `q${index}_opt${optIndex}`;
                    
                    // Update associated label
                    const label = container.querySelector(`label[for="${radio.id}"]`);
                    if (label) {
                        label.htmlFor = radio.id;
                    }
                });
                
                // Update option inputs
                const optionInputs = container.querySelectorAll('input[class="asmpg-option-input"]');
                optionInputs.forEach((input, optIndex) => {
                    input.name = `questions[${index}][options][${optIndex}][text]`;
                    input.placeholder = `Option ${String.fromCharCode(65 + optIndex)}`;
                    
                    // Update option letter
                    const letterSpan = input.closest('.asmpg-option-item')?.querySelector('.asmpg-option-letter');
                    if (letterSpan) {
                        letterSpan.textContent = String.fromCharCode(65 + optIndex);
                    }
                });
            });
            
            questionCount = containers.length;
        }

        // Attach change listeners to radio buttons for visual feedback
        function attachRadioListeners() {
            document.querySelectorAll('.asmpg-option-item .asmpg-radio').forEach(radio => {
                radio.removeEventListener('change', radioChangeHandler);
                radio.addEventListener('change', radioChangeHandler);
            });
        }

        function radioChangeHandler() {
            // Remove selected class from all option items in the same question
            const questionContainer = this.closest('.asmpg-question-container');
            questionContainer.querySelectorAll('.asmpg-option-item').forEach(item => {
                item.classList.remove('asmpg-selected');
            });
            
            // Add selected class to parent of checked radio
            if (this.checked) {
                this.closest('.asmpg-option-item').classList.add('asmpg-selected');
            }
        }

        // CREATE button - submit form
        document.getElementById('createBtn').addEventListener('click', function() {
            if (validateForm()) {
                document.getElementById('formAction').value = 'save_assessment';
                document.getElementById('assessmentForm').submit();
            }
        });

        // PREVIEW button - preview assessment
        document.getElementById('previewBtn').addEventListener('click', function() {
            if (validateForm()) {
                document.getElementById('formAction').value = 'preview_assessment';
                document.getElementById('assessmentForm').submit();
            }
        });

        // Form validation
        function validateForm() {
            const title = document.getElementById('assessment_title').value.trim();
            if (!title) {
                alert('Please enter an assessment title');
                return false;
            }
            
            const questions = document.querySelectorAll('.asmpg-question-container');
            if (questions.length === 0) {
                alert('Please add at least one question');
                return false;
            }
            
            // Check if each question has a correct answer selected
            for (let i = 0; i < questions.length; i++) {
                const radios = questions[i].querySelectorAll('input[type="radio"]:checked');
                if (radios.length === 0) {
                    alert(`Please select the correct answer for Question ${i + 1}`);
                    return false;
                }
            }
            
            return true;
        }

        // Add first question by default
        window.addEventListener('load', function() {
            document.getElementById('addQuestionBtn').click();
            
            // Auto-dismiss alerts after 3 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 3000);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>