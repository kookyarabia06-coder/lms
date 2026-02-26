<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Get preview data from session
$previewData = $_SESSION['preview_data'] ?? null;
if (!$previewData) {
    header('Location: assessment_crud.php');
    exit;
}

$userId = $_SESSION['user']['id'];
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assessment Preview - <?= htmlspecialchars($previewData['assessment_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/assessment.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .preview-badge {
            background: #f8961e;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            margin-left: 20px;
        }
        .correct-answer-indicator {
            color: #4cc9f0;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        .asmpg-preview-footer {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="asmpg-main-container">
        <div class="asmpg-header">
            <h1 class="asmpg-title">
                Assessment Preview: <?= htmlspecialchars($previewData['assessment_title']) ?>
                <span class="preview-badge">PREVIEW MODE</span>
            </h1>
        </div>
        
        <div class="centered-content">
            <div class="asmpg-form" style="background: white;">
                
                <?php if (!empty($previewData['assessment_description'])): ?>
                <div class="mb-4 p-3 bg-light rounded">
                    <p class="mb-0"><strong>Description:</strong> <?= htmlspecialchars($previewData['assessment_description']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (isset($previewData['questions']) && is_array($previewData['questions'])): ?>
                    <?php foreach ($previewData['questions'] as $index => $question): ?>
                        <?php if (empty($question['text'])) continue; ?>
                        
                        <div class="asmpg-question-container" style="background: #f8f9fa; margin-bottom: 30px;">
                            <h3 class="asmpg-question-header">
                                Question <?= $index + 1 ?>: 
                                <span class="text-dark"><?= htmlspecialchars($question['text']) ?></span>
                            </h3>
                            
                            <?php if (isset($question['options']) && is_array($question['options'])): ?>
                                <?php foreach ($question['options'] as $optIndex => $option): ?>
                                    <?php if (empty($option['text'])) continue; ?>
                                    
                                    <div class="asmpg-option-item <?= (isset($question['correct_answer']) && $question['correct_answer'] == $optIndex) ? 'asmpg-selected' : '' ?>" 
                                         style="<?= (isset($question['correct_answer']) && $question['correct_answer'] == $optIndex) ? 'background: rgba(76, 201, 240, 0.1); border-color: #4cc9f0;' : '' ?>">
                                        <span class="asmpg-option-letter"><?= chr(65 + $optIndex) ?></span>
                                        <span class="asmpg-option-label" style="margin-left: 10px;">
                                            <?= htmlspecialchars($option['text']) ?>
                                        </span>
                                        <?php if (isset($question['correct_answer']) && $question['correct_answer'] == $optIndex): ?>
                                            <span class="correct-answer-indicator">
                                                <i class="fas fa-check-circle" style="color: #4cc9f0;"></i> Correct Answer
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="asmpg-preview-footer">
                    <p class="text-muted mb-3">This is a preview of how the assessment will look to students.</p>
                    <div class="asmpg-button-group" style="justify-content: center;">
                        <a href="../admin/assessment_crud.php" class="asmpg-btn-preview" style="text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Back to Edit
                        </a>
                        <button type="button" onclick="window.print()" class="asmpg-btn-create">
                            <i class="fas fa-print"></i> Print Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>