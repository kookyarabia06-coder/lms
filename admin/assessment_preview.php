<script>
let questionCount = 0;
let existingQuestions = <?= json_encode($assessment_questions) ?>;

function loadExistingQuestions() {
    existingQuestions.forEach((q, index) => {
        addQuestion(q, index);
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
                                   value="${escapeHtml(option.text || '')}" required>
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
        
        // Update option IDs
        const options = question.querySelectorAll('[id^="option_"]');
        options.forEach(option => {
            const oldId = option.id;
            const parts = oldId.split('_');
            if (parts.length === 4) {
                const optionIndex = parts[3];
                option.id = `option_${newIndex}_${optionIndex}`;
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
                           class="form-control" placeholder="Option ${optionIndex+1}" required>
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