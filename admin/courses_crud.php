<?php

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

// Set maximum file upload size (in bytes)
define('MAX_FILE_SIZE', 250 * 1024 * 1024); // 250MB

// Check if POST data exists (if not, might be file size issue)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $max_size = ini_get('post_max_size');
    $_SESSION['error_message'] = "File upload failed. Maximum file size is $max_size. Please try uploading smaller files.";
    header('Location: ' . $_SERVER['PHP_SELF'] . (!empty($_GET['act']) ? '?act=' . $_GET['act'] . (isset($_GET['id']) ? '&id=' . $_GET['id'] : '') : ''));
    exit;
}

$act = $_GET['act'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : null;
$action = $_GET['action'] ?? '';

// First, check if course_departments table exists and create it if not
try {
    $pdo->query("SELECT 1 FROM course_departments LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, create it with proper structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `course_departments` (
            `course_id` int(11) NOT NULL,
            `department_id` int(11) NOT NULL,
            PRIMARY KEY (`course_id`, `department_id`),
            KEY `department_id` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    
    // Add foreign key constraints if the tables exist
    try {
        $pdo->exec("
            ALTER TABLE `course_departments`
            ADD CONSTRAINT `course_departments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `course_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
        ");
    } catch (Exception $e) {
        // Foreign keys might already exist or tables don't exist yet - ignore
    }
}

// Check if updated_at column exists and add it if not
try {
    $pdo->query("SELECT updated_at FROM courses LIMIT 1");
} catch (Exception $e) {
    // Column doesn't exist, add it
    try {
        $pdo->exec("ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
    } catch (Exception $e) {
        // Column might already exist
    }
}

// Get user's departments based on their role
if (is_superadmin() || is_admin()) {
    // Superadmin and admin see all departments
    $dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $user_departments = $dept_stmt->fetchAll();
} else {
    // Get departments assigned to the current user
    // First check if user_departments table exists
    try {
        $pdo->query("SELECT 1 FROM user_departments LIMIT 1");
        $dept_stmt = $pdo->prepare("
            SELECT d.id, d.name 
            FROM departments d
            JOIN user_departments ud ON ud.department_id = d.id
            WHERE ud.user_id = ?
            ORDER BY d.name
        ");
        $dept_stmt->execute([$_SESSION['user']['id']]);
        $user_departments = $dept_stmt->fetchAll();
    } catch (Exception $e) {
        // If user_departments table doesn't exist, show all departments
        $dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        $user_departments = $dept_stmt->fetchAll();
    }
}

// Fetch all departments for dropdown (if needed for admins)
$all_dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$all_departments = $all_dept_stmt->fetchAll();

/**
 * Calculate expiration date
 */
function calculateExpiry($expires_at, $valid_days) {
    if (!empty($valid_days) && is_numeric($valid_days) && $valid_days > 0) {
        return date('Y-m-d', strtotime("+{$valid_days} days"));
    }
    return !empty($expires_at) ? $expires_at : null;
}

/**
 * Handle file upload with size validation
 */
function uploadFile($input, $dir, $allowed = [], $max_size = MAX_FILE_SIZE) {
    if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
        // Check for specific upload errors
        if (isset($_FILES[$input]['error']) && $_FILES[$input]['error'] === UPLOAD_ERR_INI_SIZE) {
            $_SESSION['error_message'] = "The uploaded file exceeds the maximum file size.";
        }
        return null;
    }

    // Check file size
    if ($_FILES[$input]['size'] > $max_size) {
        $_SESSION['error_message'] = "File is too large. Maximum size is " . ($max_size / 1024 / 1024) . "MB.";
        return null;
    }

    $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
    if ($allowed && !in_array($ext, $allowed)) {
        $_SESSION['error_message'] = "File type not allowed. Allowed types: " . implode(', ', $allowed);
        return null;
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $upload_dir = __DIR__ . "/../uploads/$dir/";
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $filename)) {
        return $filename;
    }
    
    $_SESSION['error_message'] = "Failed to upload file.";
    return null;
}

/**
 * Check if current user can edit/delete course
 * Returns true for admins OR if user owns the course
 */
function canModifyCourse($course_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true; 
    }

    $stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = :id");
    $stmt->execute([':id' => $course_id]);
    $course = $stmt->fetch();

    return $course && $course['proponent_id'] == $_SESSION['user']['id'];
}

/**
 * Save course departments
 */
function saveCourseDepartments($course_id, $department_ids, $pdo) {
    // Delete existing department associations
    $stmt = $pdo->prepare("DELETE FROM course_departments WHERE course_id = :course_id");
    $stmt->execute([':course_id' => $course_id]);

    // Insert new department associations
    if (!empty($department_ids) && is_array($department_ids)) {
        $insert_stmt = $pdo->prepare("
            INSERT INTO course_departments (course_id, department_id) 
            VALUES (:course_id, :department_id)
        ");

        foreach ($department_ids as $dept_id) {
            // Skip empty values
            if (empty($dept_id)) continue;
            
            $insert_stmt->execute([
                ':course_id' => $course_id,
                ':department_id' => $dept_id
            ]);
        }
    }
}

/**
 * Save assessment function
 */
function saveAssessment($course_id, $data, $pdo) {
    if (empty($data['assessment_id'])) {
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
            $data['attempts_allowed'] ?? 1
        ]);
        $assessment_id = $pdo->lastInsertId();
    } else {
        // Update existing assessment
        $assessment_id = $data['assessment_id'];
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
            $data['attempts_allowed'] ?? 1,
            $assessment_id,
            $course_id
        ]);
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
HANDLE ALL POST REQUESTS FIRST
========================= */

// Handle ADD COURSE
if ($act === 'addform' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate required fields
    $errors = [];
    if (empty($_POST['title'])) {
        $errors[] = "Title is required";
    }
    if (empty($_POST['description'])) {
        $errors[] = "Description is required";
    }
    if (empty($_POST['summary'])) {
        $errors[] = "Summary is required";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
        header('Location: ?act=addform');
        exit;
    }

    $expires_at = calculateExpiry(
        $_POST['expires_at'] ?? null,
        $_POST['valid_days'] ?? null
    );

    // Check if courses table has auto-increment id
    try {
        $stmt = $pdo->prepare("
            INSERT INTO courses (
                title, description, summary, thumbnail, file_pdf, file_video,
                proponent_id, created_at, expires_at, is_active
            ) VALUES (
                :title, :description, :summary, :thumbnail, :pdf, :video,
                :proponent_id, NOW(), :expires_at, 1
            )
        ");

        $thumbnail = uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']);
        $pdf = uploadFile('file_pdf', 'pdf', ['pdf']);
        $video = uploadFile('file_video', 'video', ['mp4','webm']);

        $stmt->execute([
            ':title'         => $_POST['title'],
            ':description'   => $_POST['description'],
            ':summary'       => $_POST['summary'],
            ':thumbnail'     => $thumbnail,
            ':pdf'           => $pdf,
            ':video'         => $video,
            ':proponent_id'  => $_SESSION['user']['id'],
            ':expires_at'    => $expires_at
        ]);

        $course_id = $pdo->lastInsertId();

        // Save department associations
        if (isset($_POST['departments']) && is_array($_POST['departments'])) {
            saveCourseDepartments($course_id, $_POST['departments'], $pdo);
        }

        // Save assessment if provided
        if (isset($_POST['save_assessment']) && $_POST['save_assessment'] == 1) {
            saveAssessment($course_id, $_POST, $pdo);
        }

        $_SESSION['success_message'] = 'Course added successfully!';
        header('Location: courses_crud.php');
        exit;
        
    } catch (PDOException $e) {
        // If error is about id field, show more specific message
        if (strpos($e->getMessage(), 'Field id doesn\'t have a default value') !== false) {
            $_SESSION['error_message'] = 'Error: The courses table needs to have an auto-increment id. Please run: ALTER TABLE courses MODIFY id INT AUTO_INCREMENT;';
        } else {
            $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        }
        $_SESSION['form_data'] = $_POST;
        header('Location: ?act=addform');
        exit;
    }
}

// Handle EDIT COURSE
if ($act === 'edit' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_assessment'])) {

    // Fetch the course to verify it exists
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $edit_course = $stmt->fetch();

    if (!$edit_course) {
        $_SESSION['error_message'] = 'Course not found';
        header('Location: courses_crud.php');
        exit;
    }

    // Check user permissions
    if (!canModifyCourse($id, $pdo)) {
        http_response_code(403);
        exit('Access denied: You can only edit your own courses');
    }

    // Validate required fields
    $errors = [];
    if (empty($_POST['title'])) {
        $errors[] = "Title is required";
    }
    if (empty($_POST['description'])) {
        $errors[] = "Description is required";
    }
    if (empty($_POST['summary'])) {
        $errors[] = "Summary is required";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
        header('Location: ?act=edit&id=' . $id);
        exit;
    }

    $expires_at = calculateExpiry(
        $_POST['expires_at'] ?? null,
        $_POST['valid_days'] ?? null
    );

    // Handle file uploads
    $thumbnail = uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']);
    $pdf = uploadFile('file_pdf', 'pdf', ['pdf']);
    $video = uploadFile('file_video', 'video', ['mp4','webm']);

    $stmt = $pdo->prepare("
        UPDATE courses SET
            title       = :title,
            description = :description,
            summary     = :summary,
            expires_at  = :expires_at,
            thumbnail   = COALESCE(:thumbnail, thumbnail),
            file_pdf    = COALESCE(:pdf, file_pdf),
            file_video  = COALESCE(:video, file_video),
            updated_at  = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':title'       => $_POST['title'],
        ':description' => $_POST['description'],
        ':summary'     => $_POST['summary'],
        ':expires_at'  => $expires_at,
        ':thumbnail'   => $thumbnail,
        ':pdf'         => $pdf,
        ':video'       => $video,
        ':id'          => $id
    ]);

    // Save department associations
    if (isset($_POST['departments']) && is_array($_POST['departments'])) {
        saveCourseDepartments($id, $_POST['departments'], $pdo);
    } else {
        // If no departments selected, remove all associations
        saveCourseDepartments($id, [], $pdo);
    }

    $_SESSION['success_message'] = 'Course updated successfully!';
    header('Location: courses_crud.php');
    exit;
}

// Handle ASSESSMENT SAVE
if ($act === 'edit' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    saveAssessment($id, $_POST, $pdo);
    $_SESSION['success_message'] = 'Assessment saved successfully!';
    header('Location: ?act=edit&id=' . $id);
    exit;
}

// Handle ASSESSMENT DELETION
if ($act === 'edit' && $id && $action === 'delete_assessment' && $assessment_id) {
    $stmt = $pdo->prepare("DELETE FROM assessments WHERE id = ? AND course_id = ?");
    $stmt->execute([$assessment_id, $id]);
    $_SESSION['success_message'] = 'Assessment deleted successfully!';
    header('Location: ?act=edit&id=' . $id);
    exit;
}

// Handle DELETE COURSE
if ($act === 'delete' && $id) {
    // Check if user can delete this course
    if (!canModifyCourse($id, $pdo)) {
        http_response_code(403);
        exit('Access denied: You can only delete your own courses');
    }

    // Get file names to delete
    $stmt = $pdo->prepare("SELECT thumbnail, file_pdf, file_video FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $files = $stmt->fetch();

    // Delete files from server
    if ($files) {
        if ($files['thumbnail'] && file_exists(__DIR__ . "/../uploads/images/" . $files['thumbnail'])) {
            unlink(__DIR__ . "/../uploads/images/" . $files['thumbnail']);
        }
        if ($files['file_pdf'] && file_exists(__DIR__ . "/../uploads/pdf/" . $files['file_pdf'])) {
            unlink(__DIR__ . "/../uploads/pdf/" . $files['file_pdf']);
        }
        if ($files['file_video'] && file_exists(__DIR__ . "/../uploads/video/" . $files['file_video'])) {
            unlink(__DIR__ . "/../uploads/video/" . $files['file_video']);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    $_SESSION['success_message'] = 'Course deleted successfully!';
    header('Location: courses_crud.php');
    exit;
}

/* =========================
GET DATA FOR DISPLAY
========================= */

// Get all courses for listing
if (is_proponent() && !is_admin() && !is_superadmin()) {
    // Proponents see only their own courses
    $query = "
        SELECT c.*, u.username 
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.proponent_id = :user_id
        ORDER BY c.updated_at DESC, c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
} else {
    // Admins and superadmins see all courses
    $query = "
        SELECT c.*, u.username 
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        ORDER BY c.updated_at DESC, c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}
$courses_list = $stmt->fetchAll();

// Fetch departments for each course
foreach ($courses_list as &$course_item) {
    try {
        $dept_stmt = $pdo->prepare("
            SELECT d.id, d.name 
            FROM departments d
            JOIN course_departments cd ON cd.department_id = d.id
            WHERE cd.course_id = ?
            ORDER BY d.name
        ");
        $dept_stmt->execute([$course_item['id']]);
        $course_item['departments'] = $dept_stmt->fetchAll();
    } catch (Exception $e) {
        $course_item['departments'] = [];
    }
}

// If editing, get the specific course data
$edit_course = null;
$course_departments = [];
$assessments = [];
$editing_assessment = null;
$assessment_questions = [];

if ($act === 'edit' && $id) {
    // Fetch the specific course for editing
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $edit_course = $stmt->fetch();

    if (!$edit_course) {
        $_SESSION['error_message'] = 'Course not found';
        header('Location: courses_crud.php');
        exit;
    }

    // Check user permissions
    if (!canModifyCourse($id, $pdo)) {
        http_response_code(403);
        exit('Access denied: You can only edit your own courses');
    }

    // Fetch course departments
    try {
        $dept_course_stmt = $pdo->prepare("
            SELECT department_id 
            FROM course_departments 
            WHERE course_id = :course_id
        ");
        $dept_course_stmt->execute([':course_id' => $id]);
        $course_departments = $dept_course_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $course_departments = [];
    }

    // Fetch assessments for this course
    $stmt = $pdo->prepare("
        SELECT a.*, 
        (SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = a.id) as question_count
        FROM assessments a 
        WHERE a.course_id = ? 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$id]);
    $assessments = $stmt->fetchAll();

    // Fetch assessment data if editing a specific assessment
    if ($assessment_id && $action === 'edit_assessment') {
        $stmt = $pdo->prepare("SELECT * FROM assessments WHERE id = ? AND course_id = ?");
        $stmt->execute([$assessment_id, $id]);
        $editing_assessment = $stmt->fetch();
        
        if ($editing_assessment) {
            // Get questions with their options
            $stmt = $pdo->prepare("
                SELECT q.*, 
                (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', o.id, 'text', o.option_text, 'is_correct', o.is_correct)) 
                 FROM assessment_options o WHERE o.question_id = q.id) as options
                FROM assessment_questions q
                WHERE q.assessment_id = ?
                ORDER BY q.order_number ASC
            ");
            $stmt->execute([$assessment_id]);
            $assessment_questions = $stmt->fetchAll();
            
            foreach ($assessment_questions as &$q) {
                $q['options'] = json_decode($q['options'], true) ?? [];
            }
        }
    }
}

// Get session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];

// Clear session data
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['form_data']);

// Get PHP upload limits for display (used only in forms, not as a banner)
$max_upload_size = ini_get('upload_max_filesize');
$max_post_size = ini_get('post_max_size');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Courses Management - LMS</title>
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
        
        .add-assessment-btn {
            margin-top: 10px;
            margin-bottom: 20px;
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
        
        .btn-add-option {
            background: #f1f3f5;
            color: #495057;
            border: 1px dashed #adb5bd;
            margin-top: 5px;
        }
        
        .btn-add-option:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
    <h3 class="mb-4">Courses Management</h3>

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

    <?php if (is_proponent()): ?>
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i> You are viewing only the courses you have created.
        </div>
    <?php endif; ?>

    <a href="?act=addform" class="btn btn-success mb-3">Add New Course</a>

    <?php if ($act === 'addform'): ?>
        <?php 
            // Get form values from session if available (for validation errors)
            $title_value = $form_data['title'] ?? '';
            $description_value = $form_data['description'] ?? '';
            $summary_value = $form_data['summary'] ?? '';
            $expires_value = $form_data['expires_at'] ?? '';
            $valid_days_value = $form_data['valid_days'] ?? '';
            $selected_depts = $form_data['departments'] ?? [];
        ?>

        <div class="card p-4 mb-4 shadow-sm bg-white rounded">
            <h5 class="mb-3">Add New Course</h5>
            <form method="post" enctype="multipart/form-data" id="courseForm">
                <div class="mb-3">
                    <label>Course Title</label>
                    <input name="title" class="form-control" placeholder="Title" required
                        value="<?= htmlspecialchars($title_value) ?>">
                </div>

                <div class="mb-3">
                    <label>Course Description</label>
                    <input name="description" class="form-control" placeholder="Description" required
                        value="<?= htmlspecialchars($description_value) ?>">
                </div>

                <div class="mb-3">
                    <label>Course Summary</label>
                    <textarea name="summary" class="form-control" rows="4" required
                        placeholder="Course Summary"><?= htmlspecialchars($summary_value) ?></textarea>
                </div>

                <!-- Department Selection - Dropdown (Single Select) -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Course Department</label>
                    <select name="departments[]" class="form-control">
                        <option value="">-- Select Department --</option>
                        <?php 
                        $display_departments = (is_superadmin() || is_admin()) ? $all_departments : $user_departments;
                        
                        if (!empty($display_departments)): 
                            foreach ($display_departments as $dept): 
                        ?>
                            <option value="<?= $dept['id'] ?>" <?= in_array($dept['id'], $selected_depts) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <option value="" disabled>No departments available</option>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted">Select the department this course belongs to</small>
                </div>

                <!-- Date -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Expiration Date</label>
                        <input type="date" name="expires_at" id="expires_at" class="form-control"
                            value="<?= htmlspecialchars($expires_value) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Validity (Days)</label>
                        <input type="number" name="valid_days" id="valid_days" class="form-control"
                            placeholder="Example: 5"
                            value="<?= htmlspecialchars($valid_days_value) ?>">
                    </div>
                </div>

                <!-- File Uploads -->
                <div class="mb-3">
                    <label>Thumbnail (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="mb-3">
                    <label>PDF (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                </div>

                <div class="mb-3">
                    <label>Video (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                </div>

                <!-- Assessment Section - Button to show/hide -->
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-primary" id="toggleAssessmentBtn" onclick="toggleAssessmentForm()">
                        <i class="fas fa-clipboard-list me-2"></i>Add Assessment to Course
                    </button>
                </div>

                <!-- Assessment Form (Hidden by default) -->
                <div id="assessmentForm" class="assessment-section" style="display: none;">
                    <h5 class="mb-3 text-primary">
                        <i class="fas fa-clipboard-list me-2"></i>Course Assessment
                    </h5>
                    
                    <input type="hidden" name="save_assessment" value="1" id="saveAssessment">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="fw-bold">Assessment Title</label>
                            <input type="text" name="assessment_title" id="assessment_title" class="form-control" placeholder="e.g., Final Exam, Module 1 Quiz">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Passing Score (%)</label>
                            <input type="number" name="passing_score" id="passing_score" class="form-control" value="70" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">Description</label>
                        <textarea name="assessment_description" id="assessment_description" class="form-control" rows="2" placeholder="Assessment description"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Time Limit (minutes)</label>
                            <input type="number" name="time_limit" id="time_limit" class="form-control" placeholder="Leave empty for no limit">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Attempts Allowed</label>
                            <input type="number" name="attempts_allowed" id="attempts_allowed" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Questions Container -->
                    <div id="questionsContainer" class="mb-3">
                        <!-- Questions will be added here dynamically -->
                    </div>

                    <!-- Add Question Button -->
                    <button type="button" class="btn btn-add-question w-100" onclick="addQuestion()">
                        <i class="fas fa-plus me-2"></i>Add New Question
                    </button>

                    <hr class="my-3">
                    
                    <small class="text-muted d-block">
                        <i class="fas fa-info-circle"></i> Multiple choice questions will have 4 options by default. You can mark the correct answer(s) using the checkboxes.
                    </small>
                </div>

                <hr>

                <button type="submit" class="btn btn-primary">Add Course</button>
                <a href="courses_crud.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>

    <?php elseif ($act === 'edit' && $id && $edit_course): ?>
        <?php 
            // Get form values with proper precedence: submitted form data > course data > empty
            $title_value = isset($form_data['title']) ? $form_data['title'] : ($edit_course['title'] ?? '');
            $description_value = isset($form_data['description']) ? $form_data['description'] : ($edit_course['description'] ?? '');
            $summary_value = isset($form_data['summary']) ? $form_data['summary'] : ($edit_course['summary'] ?? '');
            $expires_value = isset($form_data['expires_at']) ? $form_data['expires_at'] : ($edit_course['expires_at'] ?? '');
            
            // Calculate valid days from expiration date
            if (empty($form_data['valid_days']) && !empty($edit_course['expires_at'])) {
                $expires_timestamp = strtotime($edit_course['expires_at']);
                $now_timestamp = time();
                $valid_days_value = max(0, (int) ceil(($expires_timestamp - $now_timestamp) / 86400));
            } else {
                $valid_days_value = $form_data['valid_days'] ?? '';
            }
            
            // Get selected departments
            $selected_depts = isset($form_data['departments']) ? $form_data['departments'] : $course_departments;
        ?>

        <!-- Course Edit Form -->
        <div class="card p-4 mb-4 shadow-sm bg-white rounded">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Edit Course: <?= htmlspecialchars($edit_course['title']) ?></h5>
                <span class="badge bg-info">Course ID: <?= $edit_course['id'] ?></span>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>Course Title</label>
                    <input name="title" class="form-control" placeholder="Title" required
                        value="<?= htmlspecialchars($title_value) ?>">
                </div>

                <div class="mb-3">
                    <label>Course Description</label>
                    <input name="description" class="form-control" placeholder="Description" required
                        value="<?= htmlspecialchars($description_value) ?>">
                </div>

                <div class="mb-3">
                    <label>Course Summary</label>
                    <textarea name="summary" class="form-control" rows="4" required
                        placeholder="Course Summary"><?= htmlspecialchars($summary_value) ?></textarea>
                </div>

                <!-- Department Selection - Dropdown (Single Select) -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Course Department</label>
                    <select name="departments[]" class="form-control">
                        <option value="">-- Select Department --</option>
                        <?php 
                        $display_departments = (is_superadmin() || is_admin()) ? $all_departments : $user_departments;
                        
                        if (!empty($display_departments)): 
                            foreach ($display_departments as $dept): 
                        ?>
                            <option value="<?= $dept['id'] ?>" <?= in_array($dept['id'], $selected_depts) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <option value="" disabled>No departments available</option>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted">Select the department this course belongs to</small>
                </div>

                <!-- Date -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Expiration Date</label>
                        <input type="date" name="expires_at" id="expires_at" class="form-control"
                            value="<?= htmlspecialchars($expires_value) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Validity (Days)</label>
                        <input type="number" name="valid_days" id="valid_days" class="form-control"
                            placeholder="Example: 5"
                            value="<?= htmlspecialchars($valid_days_value) ?>">
                    </div>
                </div>

                <!-- File Uploads - Show existing files -->
                <div class="mb-3">
                    <label>Thumbnail (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <?php if ($edit_course['thumbnail']): ?>
                        <div class="mt-2">
                            <img src="<?= BASE_URL ?>/uploads/images/<?= $edit_course['thumbnail'] ?>" width="120" class="border rounded">
                            <small class="text-muted d-block">Leave empty to keep current image</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label>PDF (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                    <?php if ($edit_course['file_pdf']): ?>
                        <div class="mt-2">
                            <a href="<?= BASE_URL ?>/uploads/pdf/<?= $edit_course['file_pdf'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file-pdf"></i> View Current PDF
                            </a>
                            <small class="text-muted d-block">Leave empty to keep current file</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label>Video (Max: <?= $max_upload_size ?>)</label>
                    <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                    <?php if ($edit_course['file_video']): ?>
                        <div class="mt-2">
                            <a href="<?= BASE_URL ?>/uploads/video/<?= $edit_course['file_video'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-video"></i> View Current Video
                            </a>
                            <small class="text-muted d-block">Leave empty to keep current file</small>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Update Course</button>
                <a href="courses_crud.php" class="btn btn-secondary ms-2">Back to Courses</a>
            </form>
        </div>

        <!-- Assessments Section -->
        <div class="card p-4 mb-4 shadow-sm bg-white rounded">
            <h5 class="mb-3">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                Course Assessments
            </h5>
            
            <!-- Display existing assessments -->
            <?php if (!empty($assessments)): ?>
                <?php foreach ($assessments as $assess): ?>
                <div class="assessment-card">
                    <div class="assessment-header">
                        <div>
                            <span class="assessment-title"><?= htmlspecialchars($assess['title']) ?></span>
                            <span class="badge bg-info ms-2"><?= $assess['question_count'] ?> Questions</span>
                            <?php if ($assess['question_count'] > 0): ?>
                                <span class="badge bg-success ms-1">Ready</span>
                            <?php else: ?>
                                <span class="badge bg-warning ms-1">No Questions</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="?act=edit&id=<?= $id ?>&action=edit_assessment&assessment_id=<?= $assess['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?act=edit&id=<?= $id ?>&action=delete_assessment&assessment_id=<?= $assess['id'] ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this assessment? All questions will also be deleted.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                    
                    <p class="text-muted small mb-2"><?= htmlspecialchars($assess['description'] ?: 'No description') ?></p>
                    
                    <div class="assessment-meta">
                        <span class="me-3"><i class="fas fa-check-circle"></i> Passing: <?= $assess['passing_score'] ?>%</span>
                        <?php if ($assess['time_limit']): ?>
                            <span class="me-3"><i class="fas fa-clock"></i> Time: <?= $assess['time_limit'] ?> mins</span>
                        <?php endif; ?>
                        <span><i class="fas fa-redo"></i> Attempts: <?= $assess['attempts_allowed'] ?></span>
                    </div>

                    <!-- Show questions if editing this assessment -->
                    <?php if ($assessment_id == $assess['id'] && $action === 'edit_assessment'): ?>
                        <div class="mt-3 p-3 bg-white rounded border">
                            <h6 class="text-primary">Edit Assessment Questions</h6>
                            <form method="post" id="assessmentEditForm">
                                <input type="hidden" name="save_assessment" value="1">
                                <input type="hidden" name="assessment_id" value="<?= $assess['id'] ?>">
                                <input type="hidden" name="assessment_title" value="<?= htmlspecialchars($assess['title']) ?>">
                                <input type="hidden" name="assessment_description" value="<?= htmlspecialchars($assess['description']) ?>">
                                <input type="hidden" name="passing_score" value="<?= $assess['passing_score'] ?>">
                                <input type="hidden" name="time_limit" value="<?= $assess['time_limit'] ?>">
                                <input type="hidden" name="attempts_allowed" value="<?= $assess['attempts_allowed'] ?>">
                                
                                <div id="editQuestionsContainer">
                                    <!-- Existing questions will be loaded here -->
                                </div>
                                
                                <button type="button" class="btn btn-add-question w-100" onclick="addEditQuestion()">
                                    <i class="fas fa-plus me-2"></i>Add New Question
                                </button>
                                
                                <hr>
                                <button type="submit" class="btn btn-success">Save Changes</button>
                                <a href="?act=edit&id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>

                        <script>
                            let editQuestionCount = 0;
                            let existingQuestions = <?= json_encode($assessment_questions) ?>;
                            
                            function loadExistingQuestions() {
                                existingQuestions.forEach((q, index) => {
                                    addEditQuestion(q, index);
                                });
                            }
                            
                            function addEditQuestion(questionData = null, index = null) {
                                const container = document.getElementById('editQuestionsContainer');
                                const qIndex = index !== null ? index : editQuestionCount;
                                const question = questionData || { text: '', type: 'multiple_choice', points: 1, options: [] };
                                
                                let optionsHtml = '';
                                if (question.type === 'multiple_choice') {
                                    // Create 4 options, filling in existing ones if available
                                    for (let i = 0; i < 4; i++) {
                                        const option = question.options && question.options[i] ? question.options[i] : { text: '', is_correct: false };
                                        optionsHtml += `
                                            <div class="option-item mb-2" id="edit_option_${qIndex}_${i}">
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
                                                        ${i >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeEditOption(${qIndex}, ${i})">
                                                            <i class="fas fa-times"></i>
                                                        </span>` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    }
                                }
                                
                                const questionHtml = `
                                    <div class="question-form" id="edit_question_${qIndex}">
                                        <div class="question-header">
                                            <span class="question-text">Question ${qIndex + 1}</span>
                                            <span class="remove-question" onclick="removeEditQuestion(${qIndex})">
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
                                                        onchange="toggleEditQuestionOptions(${qIndex}, this.value)">
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
                                        
                                        <div id="edit_options_${qIndex}" class="options-container" 
                                             style="display: ${question.type === 'multiple_choice' ? 'block' : 'none'};">
                                            ${optionsHtml}
                                        </div>
                                        
                                        <div id="edit_true_false_${qIndex}" style="display: ${question.type === 'true_false' ? 'block' : 'none'};">
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
                                    editQuestionCount++;
                                } else {
                                    editQuestionCount = Math.max(editQuestionCount, qIndex + 1);
                                }
                            }
                            
                            function removeEditQuestion(id) {
                                document.getElementById(`edit_question_${id}`).remove();
                            }
                            
                            function toggleEditQuestionOptions(questionId, type) {
                                const optionsDiv = document.getElementById(`edit_options_${questionId}`);
                                const trueFalseDiv = document.getElementById(`edit_true_false_${questionId}`);
                                
                                if (type === 'multiple_choice') {
                                    optionsDiv.style.display = 'block';
                                    trueFalseDiv.style.display = 'none';
                                    // Ensure we have 4 options
                                    if (optionsDiv.children.length === 0) {
                                        for (let i = 0; i < 4; i++) {
                                            addEditOption(questionId, i);
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
                            
                            function addEditOption(questionId, optionIndex) {
                                const optionsDiv = document.getElementById(`edit_options_${questionId}`);
                                
                                const optionHtml = `
                                    <div class="option-item mb-2" id="edit_option_${questionId}_${optionIndex}">
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
                                                ${optionIndex >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeEditOption(${questionId}, ${optionIndex})">
                                                    <i class="fas fa-times"></i>
                                                </span>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                optionsDiv.insertAdjacentHTML('beforeend', optionHtml);
                            }
                            
                            function removeEditOption(questionId, optionId) {
                                if (optionId >= 4) {
                                    document.getElementById(`edit_option_${questionId}_${optionId}`).remove();
                                }
                            }
                            
                            function escapeHtml(text) {
                                if (!text) return '';
                                const div = document.createElement('div');
                                div.textContent = text;
                                return div.innerHTML;
                            }
                            
                            // Load existing questions on page load
                            document.addEventListener('DOMContentLoaded', function() {
                                if (existingQuestions && existingQuestions.length > 0) {
                                    loadExistingQuestions();
                                }
                            });
                        </script>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No assessments created yet for this course.
                </div>
            <?php endif; ?>

            <!-- Add Assessment Button -->
            <button class="btn btn-outline-primary add-assessment-btn" onclick="window.location.href='?act=edit&id=<?= $id ?>&action=new_assessment'">
                <i class="fas fa-plus me-2"></i>Create New Assessment
            </button>

            <!-- New Assessment Form -->
            <?php if ($action === 'new_assessment'): ?>
            <div id="newAssessmentForm" class="assessment-section">
                <h6 class="mb-3 text-primary">Create New Assessment</h6>
                <form method="post">
                    <input type="hidden" name="save_assessment" value="1">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="fw-bold">Assessment Title</label>
                            <input type="text" name="assessment_title" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Passing Score (%)</label>
                            <input type="number" name="passing_score" class="form-control" value="70" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">Description</label>
                        <textarea name="assessment_description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Time Limit (minutes)</label>
                            <input type="number" name="time_limit" class="form-control" placeholder="Leave empty for no limit">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Attempts Allowed</label>
                            <input type="number" name="attempts_allowed" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Questions Container -->
                    <div id="newQuestionsContainer" class="mb-3">
                        <!-- Questions will be added here dynamically -->
                    </div>

                    <!-- Add Question Button -->
                    <button type="button" class="btn btn-add-question w-100" onclick="addNewQuestion()">
                        <i class="fas fa-plus me-2"></i>Add New Question
                    </button>

                    <hr class="my-3">

                    <button type="submit" class="btn btn-primary">Save Assessment</button>
                    <a href="?act=edit&id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>

            <script>
                let newQuestionCount = 0;
                
                function addNewQuestion() {
                    const container = document.getElementById('newQuestionsContainer');
                    const qIndex = newQuestionCount;
                    
                    // Generate 4 default options for multiple choice
                    let optionsHtml = '';
                    for (let i = 0; i < 4; i++) {
                        optionsHtml += `
                            <div class="option-item mb-2" id="new_option_${qIndex}_${i}">
                                <div class="row">
                                    <div class="col-8">
                                        <input type="text" name="questions[${qIndex}][options][${i}][text]" 
                                               class="form-control" placeholder="Option ${i+1}" required>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="questions[${qIndex}][options][${i}][is_correct]">
                                            <label class="form-check-label">Correct</label>
                                        </div>
                                    </div>
                                    <div class="col-1">
                                        ${i >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeNewOption(${qIndex}, ${i})">
                                            <i class="fas fa-times"></i>
                                        </span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    const questionHtml = `
                        <div class="question-form" id="new_question_${qIndex}">
                            <div class="question-header">
                                <span class="question-text">Question ${newQuestionCount + 1}</span>
                                <span class="remove-question" onclick="removeNewQuestion(${qIndex})">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <label>Question Text</label>
                                <input type="text" name="questions[${qIndex}][text]" class="form-control" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Question Type</label>
                                    <select name="questions[${qIndex}][type]" class="form-control" 
                                            onchange="toggleNewQuestionOptions(${qIndex}, this.value)">
                                        <option value="multiple_choice" selected>Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                        <option value="essay">Essay</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Points</label>
                                    <input type="number" name="questions[${qIndex}][points]" class="form-control" value="1" min="1">
                                </div>
                            </div>
                            
                            <div id="new_options_${qIndex}" class="options-container">
                                ${optionsHtml}
                            </div>
                            
                            <div id="new_true_false_${qIndex}" style="display: none;">
                                <label>Correct Answer</label>
                                <select name="questions[${qIndex}][correct_answer]" class="form-control">
                                    <option value="true">True</option>
                                    <option value="false">False</option>
                                </select>
                            </div>
                        </div>
                    `;
                    
                    container.insertAdjacentHTML('beforeend', questionHtml);
                    newQuestionCount++;
                }
                
                function removeNewQuestion(id) {
                    document.getElementById(`new_question_${id}`).remove();
                }
                
                function toggleNewQuestionOptions(questionId, type) {
                    const optionsDiv = document.getElementById(`new_options_${questionId}`);
                    const trueFalseDiv = document.getElementById(`new_true_false_${questionId}`);
                    
                    if (type === 'multiple_choice') {
                        optionsDiv.style.display = 'block';
                        trueFalseDiv.style.display = 'none';
                        // Ensure we have 4 options
                        if (optionsDiv.children.length === 0) {
                            for (let i = 0; i < 4; i++) {
                                addNewOption(questionId, i);
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
                
                function addNewOption(questionId, optionIndex) {
                    const optionsDiv = document.getElementById(`new_options_${questionId}`);
                    
                    const optionHtml = `
                        <div class="option-item mb-2" id="new_option_${questionId}_${optionIndex}">
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
                                    ${optionIndex >= 4 ? `<span class="text-danger" style="cursor: pointer;" onclick="removeNewOption(${questionId}, ${optionIndex})">
                                        <i class="fas fa-times"></i>
                                    </span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    optionsDiv.insertAdjacentHTML('beforeend', optionHtml);
                }
                
                function removeNewOption(questionId, optionId) {
                    if (optionId >= 4) {
                        document.getElementById(`new_option_${questionId}_${optionId}`).remove();
                    }
                }
            </script>
            <?php endif; ?>
        </div>

    <?php else: ?>

    <div class="modern-courses-grid">
        <?php if (empty($courses_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No courses found. Click "Add New Course" to create one.
            </div>
        <?php else: ?>
            <?php foreach ($courses_list as $c): ?>
            <div class="modern-course-card">
                <div class="modern-card-img">
                    <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
                </div>
                <div class="modern-card-body">
                    <div class="modern-card-title">
                        <h6>
                            <?= htmlspecialchars($c['title']) ?>
                        </h6>
                    </div>
                    <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>

                    <!-- Display departments -->
                    <?php if (!empty($c['departments'])): ?>
                        <div class="department-container">
                            <strong>Departments:</strong><br>
                            <?php foreach ($c['departments'] as $dept): ?>
                                <span class="department-badge"><?= htmlspecialchars($dept['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="modern-course-info">
                        <?php
                        $startDate = date('M d, Y', strtotime($c['created_at']));
                        $expiryDate = $c['expires_at']
                            ? date('M d, Y', strtotime($c['expires_at']))
                            : 'No expiry';
                        $isExpired = !empty($c['expires_at']) && strtotime($c['expires_at']) <= time();

                        $updatedDate = !empty($c['updated_at']) 
                            ? date('M d, Y h:i A', strtotime($c['updated_at'])) 
                            : 'Never';
                        ?>
                        <p><strong>Created by:</strong> <?= htmlspecialchars($c['username'] ?? 'Unknown') ?></p>
                        <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                        <p><strong>Expires:</strong> 
                            <span class="<?= $isExpired ? 'text-danger' : '' ?>">
                                <?= $expiryDate ?>
                                <?php if($isExpired): ?>
                                    <i class="fas fa-exclamation-circle" title="Expired"></i>
                                <?php endif; ?>
                            </span>
                        </p>
                        <p><strong>Last Edited:</strong> 
                            <span class="<?= $c['updated_at'] ? 'text-primary' : 'text-muted' ?>">
                                <?= $updatedDate ?>
                                <?php if($c['updated_at']): ?>
                                    <i class="fas fa-pen-alt ms-1" style="font-size: 11px;"></i>
                                <?php endif; ?>
                            </span>
                        </p>
                    </div>
                    <div class="modern-card-actions">
                        <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="modern-btn-primary modern-btn-sm">View</a>

                        <?php if (canModifyCourse($c['id'], $pdo)): ?>
                            <a href="?act=edit&id=<?= $c['id'] ?>" class="modern-btn-warning modern-btn-sm">Edit</a>
                            <a href="?act=delete&id=<?= $c['id'] ?>" class="modern-btn-danger modern-btn-sm"
                                onclick="return confirm('Delete this course? This will also delete all associated assessments.')">Delete</a>
                        <?php else: ?>
                            <span class="btn btn-secondary btn-sm" disabled>Read Only</span>
                        <?php endif; ?>
                    </div>  
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const expires = document.getElementById('expires_at');
    const days = document.getElementById('valid_days');

    if (expires && days) {
        expires.addEventListener('change', function () {
            if (!this.value) {
                days.value = '';
                return;
            }

            const today = new Date();
            today.setHours(0,0,0,0);

            const exp = new Date(this.value);
            exp.setHours(0,0,0,0);

            const diff = Math.ceil((exp - today) / (1000 * 60 * 60 * 24));
            days.value = diff >= 0 ? diff : 0;
        });

        days.addEventListener('change', function() {
            if (this.value && parseInt(this.value) > 0) {
                const today = new Date();
                today.setHours(0,0,0,0);
                
                const exp = new Date(today);
                exp.setDate(today.getDate() + parseInt(this.value));
                
                const year = exp.getFullYear();
                const month = String(exp.getMonth() + 1).padStart(2, '0');
                const day = String(exp.getDate()).padStart(2, '0');
                
                expires.value = `${year}-${month}-${day}`;
            }
        });
    }

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Toggle assessment form in add course
function toggleAssessmentForm() {
    const form = document.getElementById('assessmentForm');
    const btn = document.getElementById('toggleAssessmentBtn');
    
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-times me-2"></i>Remove Assessment';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-outline-danger');
    } else {
        form.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-clipboard-list me-2"></i>Add Assessment to Course';
        btn.classList.remove('btn-outline-danger');
        btn.classList.add('btn-outline-primary');
    }
}

// For add course - add question to new assessment
let questionCount = 0;

function addQuestion() {
    const container = document.getElementById('questionsContainer');
    const qIndex = questionCount;
    
    // Generate 4 default options for multiple choice
    let optionsHtml = '';
    for (let i = 0; i < 4; i++) {
        optionsHtml += `
            <div class="option-item mb-2" id="option_${qIndex}_${i}">
                <div class="row">
                    <div class="col-8">
                        <input type="text" name="questions[${qIndex}][options][${i}][text]" 
                               class="form-control" placeholder="Option ${i+1}" required>
                    </div>
                    <div class="col-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="questions[${qIndex}][options][${i}][is_correct]">
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
    
    const questionHtml = `
        <div class="question-form" id="question_${qIndex}">
            <div class="question-header">
                <span class="question-text">Question ${questionCount + 1}</span>
                <span class="remove-question" onclick="removeQuestion(${qIndex})">
                    <i class="fas fa-times"></i>
                </span>
            </div>
            
            <div class="mb-3">
                <label>Question Text</label>
                <input type="text" name="questions[${qIndex}][text]" class="form-control" required>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Question Type</label>
                    <select name="questions[${qIndex}][type]" class="form-control" 
                            onchange="toggleQuestionOptions(${qIndex}, this.value)">
                        <option value="multiple_choice" selected>Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Points</label>
                    <input type="number" name="questions[${qIndex}][points]" class="form-control" value="1" min="1">
                </div>
            </div>
            
            <div id="options_${qIndex}" class="options-container">
                ${optionsHtml}
            </div>
            
            <div id="true_false_${qIndex}" style="display: none;">
                <label>Correct Answer</label>
                <select name="questions[${qIndex}][correct_answer]" class="form-control">
                    <option value="true">True</option>
                    <option value="false">False</option>
                </select>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', questionHtml);
    questionCount++;
}

function removeQuestion(id) {
    document.getElementById(`question_${id}`).remove();
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
</script>

</body>
</html>