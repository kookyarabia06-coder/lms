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

// Get user's committees based on their role
if (is_superadmin() || is_admin()) {
    // Superadmin and admin see all committees
    $committee_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
    $user_committees = $committee_stmt->fetchAll();
} else {
    // Get committees assigned to the current user
    try {
        $pdo->query("SELECT 1 FROM user_departments LIMIT 1");
        $committee_stmt = $pdo->prepare("
            SELECT c.id, c.name 
            FROM committees c
            JOIN user_departments ud ON ud.committee_id = c.id
            WHERE ud.user_id = ? AND ud.committee_id IS NOT NULL
            ORDER BY c.name
        ");
        $committee_stmt->execute([$_SESSION['user']['id']]);
        $user_committees = $committee_stmt->fetchAll();
    } catch (Exception $e) {
        // If user_departments table doesn't exist, show all committees
        $committee_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
        $user_committees = $committee_stmt->fetchAll();
    }
}

// Fetch all committees for dropdown (if needed for admins)
$all_committees_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name");
$all_committees = $all_committees_stmt->fetchAll();

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
        if (isset($_FILES[$input]['error']) && $_FILES[$input]['error'] === UPLOAD_ERR_INI_SIZE) {
            $_SESSION['error_message'] = "The uploaded file exceeds the maximum file size.";
        }
        return null;
    }

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
 * Save course committees
 */
function saveCourseCommittees($course_id, $committee_ids, $pdo) {
    // Delete existing committee associations
    $stmt = $pdo->prepare("DELETE FROM course_departments WHERE course_id = :course_id");
    $stmt->execute([':course_id' => $course_id]);

    // Insert new committee associations
    if (!empty($committee_ids) && is_array($committee_ids)) {
        $insert_stmt = $pdo->prepare("
            INSERT INTO course_departments (course_id, committee_id) 
            VALUES (:course_id, :committee_id)
        ");

        foreach ($committee_ids as $comm_id) {
            // Skip empty values
            if (empty($comm_id)) continue;
            
            $insert_stmt->execute([
                ':course_id' => $course_id,
                ':committee_id' => $comm_id
            ]);
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

        // Save committee associations
        if (isset($_POST['committees']) && is_array($_POST['committees'])) {
            saveCourseCommittees($course_id, $_POST['committees'], $pdo);
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
if ($act === 'edit' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {

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

    // Save committee associations
    if (isset($_POST['committees']) && is_array($_POST['committees'])) {
        saveCourseCommittees($id, $_POST['committees'], $pdo);
    } else {
        // If no committees selected, remove all associations
        saveCourseCommittees($id, [], $pdo);
    }

    $_SESSION['success_message'] = 'Course updated successfully!';
    header('Location: courses_crud.php');
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
    $stmt->execute([$id]);
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
    $stmt->execute([$id]);
    
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
        ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
} else {
    // Admins and superadmins see all courses
    $query = "
        SELECT c.*, u.username 
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
}
$courses_list = $stmt->fetchAll();

// Fetch committees for each course
foreach ($courses_list as &$course_item) {
    try {
        $comm_stmt = $pdo->prepare("
            SELECT c.id, c.name 
            FROM committees c
            JOIN course_departments cd ON cd.committee_id = c.id
            WHERE cd.course_id = ?
            ORDER BY c.name
        ");
        $comm_stmt->execute([$course_item['id']]);
        $course_item['committees'] = $comm_stmt->fetchAll();
    } catch (Exception $e) {
        $course_item['committees'] = [];
    }
}

// If editing, get the specific course data
$edit_course = null;
$course_committees = [];

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

    // Fetch course committees
    try {
        $comm_course_stmt = $pdo->prepare("
            SELECT committee_id 
            FROM course_departments 
            WHERE course_id = :course_id
        ");
        $comm_course_stmt->execute([':course_id' => $id]);
        $course_committees = $comm_course_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $course_committees = [];
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
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
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

<?php if ($act !== 'addform' && !($act === 'edit' && $id)): ?>
    <a href="?act=addform" class="btn btn-success mb-3">Add New Course</a>
<?php endif; ?>

    <?php if ($act === 'addform'): ?>
        <?php 
            // Get form values from session if available (for validation errors)
            $title_value = $form_data['title'] ?? '';
            $description_value = $form_data['description'] ?? '';
            $summary_value = $form_data['summary'] ?? '';
            $expires_value = $form_data['expires_at'] ?? '';
            $valid_days_value = $form_data['valid_days'] ?? '';
            $selected_committees = $form_data['committees'] ?? [];
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

                <!-- Committee Selection - Dropdown (Single Select) -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Program Committee</label>
                    <select name="committees[]" class="form-control">
                        <option value="">-- Select Committee --</option>
                        <?php 
                        $display_committees = (is_superadmin() || is_admin()) ? $all_committees : $user_committees;
                        
                        if (!empty($display_committees)): 
                            foreach ($display_committees as $comm): 
                        ?>
                            <option value="<?= $comm['id'] ?>" <?= in_array($comm['id'], $selected_committees) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($comm['name']) ?>
                            </option>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <option value="" disabled>No committees available</option>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted">Select the committee this course belongs to</small>
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
            
            // Get selected committees
            $selected_committees = isset($form_data['committees']) ? $form_data['committees'] : $course_committees;
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

                <!-- Committee Selection - Dropdown (Single Select) -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Program Committee</label>
                    <select name="committees[]" class="form-control">
                        <option value="">-- Select Committee --</option>
                        <?php 
                        $display_committees = (is_superadmin() || is_admin()) ? $all_committees : $user_committees;
                        
                        if (!empty($display_committees)): 
                            foreach ($display_committees as $comm): 
                        ?>
                            <option value="<?= $comm['id'] ?>" <?= in_array($comm['id'], $selected_committees) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($comm['name']) ?>
                            </option>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <option value="" disabled>No committees available</option>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted">Select the committee this course belongs to</small>
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

                <!-- Assessments Link -->
                <div class="mb-4 p-3 bg-light rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                            <strong>Course Assessments</strong>
                            <p class="text-muted small mb-0">Manage quizzes and exams for this course</p>
                        </div>
                        <a href="assessment_crud.php?course_id=<?= $id ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Manage Assessments
                        </a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Course</button>
                <a href="courses_crud.php" class="btn btn-secondary ms-2">Back to Courses</a>
            </form>
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

                    <!-- Display committees -->
                    <?php if (!empty($c['committees'])): ?>
                        <div class="committee-container">
                            <strong>Committees:</strong><br>
                            <?php foreach ($c['committees'] as $comm): ?>
                                <span class="committee-badge" style="background-color: #8227a9; color: white; padding: 5px 8px; border-radius: 4px; font-size: 11px; margin: 2px; display: inline-block;">
                                    <?= htmlspecialchars($comm['name']) ?>
                                </span>
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
    // Expiration date and validity days logic
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
</script>

</body>
</html>