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
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$module_id = isset($_GET['module_id']) ? (int)$_GET['module_id'] : null;
$resource_id = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;
$attachment_id = isset($_GET['attachment_id']) ? (int)$_GET['attachment_id'] : null;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

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
 * Handle module attachment upload
 */
function uploadAttachment($file, $module_id, $pdo) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $upload_dir = __DIR__ . "/../uploads/attachments/";
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $stmt = $pdo->prepare("
            INSERT INTO module_attachments (module_id, filename, original_filename, filepath, filesize, filetype)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $module_id,
            $filename,
            $file['name'],
            'uploads/attachments/' . $filename,
            $file['size'],
            $ext
        ]);
        return true;
    }
    
    return false;
}

/**
 * Check if current user can modify module
 */
function canModifyModule($module_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT c.proponent_id 
        FROM modules m
        JOIN courses c ON m.course_id = c.id
        WHERE m.id = ?
    ");
    $stmt->execute([$module_id]);
    $result = $stmt->fetch();
    
    return $result && $result['proponent_id'] == $_SESSION['user']['id'];
}

/* =========================
MODULE HANDLERS
========================= */

// Handle ADD MODULE
if ($act === 'add' && $course_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user can modify this course
    $course_stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = ?");
    $course_stmt->execute([$course_id]);
    $course = $course_stmt->fetch();
    
    if (!$course || (!is_admin() && !is_superadmin() && $course['proponent_id'] != $_SESSION['user']['id'])) {
        http_response_code(403);
        exit('Access denied');
    }

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
    $status = $_POST['status'] ?? 'draft';
    $is_required = isset($_POST['is_required']) ? 1 : 0;

    // Get the next order number
    $order_stmt = $pdo->prepare("SELECT MAX(order_number) as max_order FROM modules WHERE course_id = ?");
    $order_stmt->execute([$course_id]);
    $max_order = $order_stmt->fetch(PDO::FETCH_ASSOC)['max_order'];
    $order_number = ($max_order !== null) ? $max_order + 1 : 1;

    // Handle file uploads
    $pdf_file = uploadFile('file_pdf', 'pdf', ['pdf']);
    $video_file = uploadFile('file_video', 'video', ['mp4', 'webm']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO modules (
                course_id, title, description, content, order_number, 
                file_pdf, file_video, duration_minutes, is_required, status,
                published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            $course_id, $title, $description, $content, $order_number,
            $pdf_file, $video_file, $duration, $is_required, $status,
            $published_at
        ]);

        $new_module_id = $pdo->lastInsertId();

        // Handle attachments if any
        if (!empty($_FILES['attachments']['name'][0])) {
            $files = $_FILES['attachments'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    uploadAttachment($file, $new_module_id, $pdo);
                }
            }
        }

        $pdo->commit();

        $_SESSION['success_message'] = 'Module added successfully!';
        header('Location: module_crud.php?act=list&course_id=' . $course_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to add module: ' . $e->getMessage();
        header('Location: module_crud.php?act=add&course_id=' . $course_id);
        exit;
    }
}

// Handle EDIT MODULE
if ($act === 'edit' && $module_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user can modify this module
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
    }

    // Get module to get course_id
    $module_stmt = $pdo->prepare("SELECT course_id FROM modules WHERE id = ?");
    $module_stmt->execute([$module_id]);
    $module_data = $module_stmt->fetch();
    $course_id = $module_data['course_id'];

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
    $status = $_POST['status'] ?? 'draft';
    $is_required = isset($_POST['is_required']) ? 1 : 0;

    // Handle file uploads
    $pdf_file = uploadFile('file_pdf', 'pdf', ['pdf']);
    $video_file = uploadFile('file_video', 'video', ['mp4', 'webm']);

    $sql = "UPDATE modules SET 
            title = ?, description = ?, content = ?, 
            duration_minutes = ?, is_required = ?, status = ?";
    $params = [$title, $description, $content, $duration, $is_required, $status];

    if ($pdf_file) {
        $sql .= ", file_pdf = ?";
        $params[] = $pdf_file;
    }
    if ($video_file) {
        $sql .= ", file_video = ?";
        $params[] = $video_file;
    }

    // Update published_at if status changes to published and it wasn't published before
    if ($status === 'published') {
        $sql .= ", published_at = COALESCE(published_at, NOW())";
    }

    $sql .= ", updated_at = NOW() WHERE id = ?";
    $params[] = $module_id;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Handle new attachments if any
        if (!empty($_FILES['attachments']['name'][0])) {
            $files = $_FILES['attachments'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    uploadAttachment($file, $module_id, $pdo);
                }
            }
        }

        $pdo->commit();

        $_SESSION['success_message'] = 'Module updated successfully!';
        header('Location: module_crud.php?act=list&course_id=' . $course_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to update module: ' . $e->getMessage();
        header('Location: module_crud.php?act=edit&module_id=' . $module_id);
        exit;
    }
}

// Handle DELETE MODULE
if ($act === 'delete' && $module_id) {
    // Check if user can delete this module
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
    }

    // Get module data to get course_id and file names
    $module_stmt = $pdo->prepare("SELECT course_id, file_pdf, file_video FROM modules WHERE id = ?");
    $module_stmt->execute([$module_id]);
    $module_data = $module_stmt->fetch();
    
    if (!$module_data) {
        $_SESSION['error_message'] = 'Module not found';
        header('Location: module_crud.php');
        exit;
    }

    // Delete files
    if ($module_data['file_pdf'] && file_exists(__DIR__ . "/../uploads/pdf/" . $module_data['file_pdf'])) {
        unlink(__DIR__ . "/../uploads/pdf/" . $module_data['file_pdf']);
    }
    if ($module_data['file_video'] && file_exists(__DIR__ . "/../uploads/video/" . $module_data['file_video'])) {
        unlink(__DIR__ . "/../uploads/video/" . $module_data['file_video']);
    }

    // Get attachments to delete files
    $att_stmt = $pdo->prepare("SELECT filepath FROM module_attachments WHERE module_id = ?");
    $att_stmt->execute([$module_id]);
    $attachments = $att_stmt->fetchAll();
    
    foreach ($attachments as $att) {
        if (file_exists(__DIR__ . "/../" . $att['filepath'])) {
            unlink(__DIR__ . "/../" . $att['filepath']);
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$module_id]);

        $_SESSION['success_message'] = 'Module deleted successfully!';
        header('Location: module_crud.php?act=list&course_id=' . $module_data['course_id']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to delete module: ' . $e->getMessage();
        header('Location: module_crud.php?act=list&course_id=' . $module_data['course_id']);
        exit;
    }
}

// Handle ADD RESOURCE
if ($act === 'add_resource' && $module_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
    }

    $title = $_POST['title'] ?? '';
    $url = $_POST['url'] ?? '';
    $description = $_POST['description'] ?? '';
    $type = $_POST['type'] ?? 'link';

    if (empty($title) || empty($url)) {
        $_SESSION['error_message'] = 'Title and URL are required';
        header('Location: module_crud.php?act=view&module_id=' . $module_id);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO module_resources (module_id, title, url, description, type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$module_id, $title, $url, $description, $type]);

        $_SESSION['success_message'] = 'Resource added successfully!';
        header('Location: module_crud.php?act=view&module_id=' . $module_id);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to add resource: ' . $e->getMessage();
        header('Location: module_crud.php?act=view&module_id=' . $module_id);
        exit;
    }
}

// Handle DELETE RESOURCE
if ($act === 'delete_resource' && $resource_id) {
    $stmt = $pdo->prepare("SELECT module_id FROM module_resources WHERE id = ?");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if ($resource && canModifyModule($resource['module_id'], $pdo)) {
        $pdo->prepare("DELETE FROM module_resources WHERE id = ?")->execute([$resource_id]);
        $_SESSION['success_message'] = 'Resource deleted successfully!';
        header('Location: module_crud.php?act=view&module_id=' . $resource['module_id']);
        exit;
    }
    
    $_SESSION['error_message'] = 'Resource not found or access denied';
    header('Location: module_crud.php');
    exit;
}

// Handle DELETE ATTACHMENT
if ($act === 'delete_attachment' && $attachment_id) {
    $stmt = $pdo->prepare("SELECT module_id, filepath FROM module_attachments WHERE id = ?");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch();
    
    if ($attachment && canModifyModule($attachment['module_id'], $pdo)) {
        // Delete file
        if (file_exists(__DIR__ . "/../" . $attachment['filepath'])) {
            unlink(__DIR__ . "/../" . $attachment['filepath']);
        }
        
        $pdo->prepare("DELETE FROM module_attachments WHERE id = ?")->execute([$attachment_id]);
        $_SESSION['success_message'] = 'Attachment deleted successfully!';
        header('Location: module_crud.php?act=view&module_id=' . $attachment['module_id']);
        exit;
    }
    
    $_SESSION['error_message'] = 'Attachment not found or access denied';
    header('Location: module_crud.php');
    exit;
}

/* =========================
GET DATA FOR DISPLAY
========================= */

// Get all courses for dropdown (only those user can modify)
if (is_admin() || is_superadmin()) {
    $courses_stmt = $pdo->query("SELECT id, title FROM courses ORDER BY title");
    $courses = $courses_stmt->fetchAll();
} else {
    $courses_stmt = $pdo->prepare("SELECT id, title FROM courses WHERE proponent_id = ? ORDER BY title");
    $courses_stmt->execute([$_SESSION['user']['id']]);
    $courses = $courses_stmt->fetchAll();
}

// Get modules for a specific course
$modules = [];
$current_course = null;
if ($course_id) {
    $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $course_stmt->execute([$course_id]);
    $current_course = $course_stmt->fetch();
    
    if ($current_course) {
        $module_stmt = $pdo->prepare("
            SELECT * FROM modules 
            WHERE course_id = ? 
            ORDER BY order_number ASC
        ");
        $module_stmt->execute([$course_id]);
        $modules = $module_stmt->fetchAll();
    }
}

// Get single module for viewing/editing
$module = null;
$module_resources = [];
$module_attachments = [];

if ($module_id) {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$module_id]);
    $module = $stmt->fetch();
    
    if ($module) {
        // Get course
        $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $course_stmt->execute([$module['course_id']]);
        $current_course = $course_stmt->fetch();
        
        // Get resources
        $res_stmt = $pdo->prepare("SELECT * FROM module_resources WHERE module_id = ? ORDER BY created_at DESC");
        $res_stmt->execute([$module_id]);
        $module_resources = $res_stmt->fetchAll();
        
        // Get attachments
        $att_stmt = $pdo->prepare("SELECT * FROM module_attachments WHERE module_id = ? ORDER BY created_at DESC");
        $att_stmt->execute([$module_id]);
        $module_attachments = $att_stmt->fetchAll();
    }
}

// Get session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get PHP upload limits
$max_upload_size = ini_get('upload_max_filesize');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .module-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s;
        }
        .module-card:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .status-published {
            background: #d4edda;
            color: #155724;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .status-archived {
            background: #f8d7da;
            color: #721c24;
        }
        .required-badge {
            background: #ffc107;
            color: #000;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .resource-item, .attachment-item {
            border-bottom: 1px solid #e9ecef;
            padding: 10px 0;
        }
        .resource-item:last-child, .attachment-item:last-child {
            border-bottom: none;
        }
        .module-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
    <div class="container-fluid py-4">
        <h3 class="mb-4">Module Management</h3>

        <!-- Display messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Course Selection -->
        <?php if (!$course_id && !$module_id && $act !== 'add'): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Select a Course</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($courses)): ?>
                        <p class="text-muted">No courses available. <a href="courses_crud.php?act=addform">Create a course first</a>.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($courses as $course): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="?act=list&course_id=<?= $course['id'] ?>" class="text-decoration-none">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($course['title']) ?></h6>
                                                <p class="card-text small text-muted">Click to manage modules</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Module List View -->
        <?php if ($act === 'list' && $current_course): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <a href="module_crud.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Courses
                    </a>
                    <h4 class="d-inline-block ms-3">Modules for: <?= htmlspecialchars($current_course['title']) ?></h4>
                </div>
                <a href="?act=add&course_id=<?= $course_id ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Module
                </a>
            </div>

            <?php if (empty($modules)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No modules yet. Click "Add Module" to create your first module.
                </div>
            <?php else: ?>
                <?php foreach ($modules as $index => $mod): ?>
                    <div class="module-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1">
                                    <?= $index + 1 ?>. <?= htmlspecialchars($mod['title']) ?>
                                    <?php if (!$mod['is_required']): ?>
                                        <span class="required-badge ms-2">Optional</span>
                                    <?php endif; ?>
                                </h5>
                                <div class="mb-2">
                                    <span class="status-badge status-<?= $mod['status'] ?> me-2">
                                        <?= ucfirst($mod['status']) ?>
                                    </span>
                                    <?php if ($mod['duration_minutes']): ?>
                                        <span class="text-muted me-3">
                                            <i class="far fa-clock"></i> <?= $mod['duration_minutes'] ?> min
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-muted">
                                        <i class="far fa-calendar"></i> Updated: <?= date('M d, Y', strtotime($mod['updated_at'] ?? $mod['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-muted mb-0"><?= htmlspecialchars(substr($mod['description'] ?? '', 0, 150)) ?>...</p>
                            </div>
                            <div class="btn-group">
                                <a href="?act=view&module_id=<?= $mod['id'] ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?act=edit&module_id=<?= $mod['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?act=delete&module_id=<?= $mod['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this module? This will also delete all associated resources and attachments.')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <!-- Add Module Form -->
        <?php elseif ($act === 'add' && $course_id): ?>
            <?php
            $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $course_stmt->execute([$course_id]);
            $course = $course_stmt->fetch();
            ?>
            <div class="mb-3">
                <a href="?act=list&course_id=<?= $course_id ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Modules
                </a>
            </div>

            <div class="module-form">
                <h5 class="mb-3">Add New Module to: <?= htmlspecialchars($course['title']) ?></h5>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Module Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Content</label>
                        <textarea name="content" class="form-control" rows="8"></textarea>
                        <small class="text-muted">You can write detailed lesson content here</small>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" min="1">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Required</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_required" class="form-check-input" id="isRequired" checked>
                                <label class="form-check-label" for="isRequired">This module is required</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">PDF File (Optional)</label>
                        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                        <small class="text-muted">Max size: <?= $max_upload_size ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Video File (Optional)</label>
                        <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                        <small class="text-muted">Max size: <?= $max_upload_size ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Attachments (Optional)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                        <small class="text-muted">You can upload multiple files (images, documents, etc.)</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Module</button>
                </form>
            </div>

        <!-- Edit Module Form -->
        <?php elseif ($act === 'edit' && $module): ?>
            <div class="mb-3">
                <a href="?act=list&course_id=<?= $module['course_id'] ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Modules
                </a>
            </div>

            <div class="module-form">
                <h5 class="mb-3">Edit Module: <?= htmlspecialchars($module['title']) ?></h5>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Module Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($module['title']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($module['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Content</label>
                        <textarea name="content" class="form-control" rows="8"><?= htmlspecialchars($module['content'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" min="1" value="<?= htmlspecialchars($module['duration_minutes'] ?? '') ?>">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-control">
                                <option value="draft" <?= $module['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= $module['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                                <option value="archived" <?= $module['status'] == 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Required</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_required" class="form-check-input" id="isRequired" 
                                       <?= $module['is_required'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isRequired">This module is required</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">PDF File</label>
                        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                        <?php if ($module['file_pdf']): ?>
                            <small class="text-muted">Current file: <?= htmlspecialchars($module['file_pdf']) ?> (leave empty to keep)</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Video File</label>
                        <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                        <?php if ($module['file_video']): ?>
                            <small class="text-muted">Current file: <?= htmlspecialchars($module['file_video']) ?> (leave empty to keep)</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Attachments</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                        <small class="text-muted">Upload new attachments (existing ones will be kept)</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Module</button>
                    <a href="?act=view&module_id=<?= $module_id ?>" class="btn btn-info">View Module</a>
                </form>
            </div>

            <!-- Existing Attachments -->
            <?php
            $att_stmt = $pdo->prepare("SELECT * FROM module_attachments WHERE module_id = ? ORDER BY created_at DESC");
            $att_stmt->execute([$module_id]);
            $existing_attachments = $att_stmt->fetchAll();
            ?>
            
            <?php if (!empty($existing_attachments)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-paperclip"></i> Existing Attachments</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($existing_attachments as $att): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <a href="<?= BASE_URL ?>/<?= $att['filepath'] ?>" target="_blank">
                                        <?= htmlspecialchars($att['original_filename']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= round($att['filesize'] / 1024, 2) ?> KB</small>
                                </div>
                                <a href="?act=delete_attachment&attachment_id=<?= $att['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this attachment?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <!-- View Module -->
        <?php elseif ($act === 'view' && $module && $current_course): ?>
            <div class="mb-3">
                <a href="?act=list&course_id=<?= $current_course['id'] ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Modules
                </a>
                <a href="?act=edit&module_id=<?= $module_id ?>" class="btn btn-primary ms-2">
                    <i class="fas fa-edit"></i> Edit Module
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($module['title']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Course:</strong> <?= htmlspecialchars($current_course['title']) ?></p>
                            <p><strong>Status:</strong> 
                                <span class="status-badge status-<?= $module['status'] ?>">
                                    <?= ucfirst($module['status']) ?>
                                </span>
                            </p>
                            <p><strong>Duration:</strong> <?= $module['duration_minutes'] ? $module['duration_minutes'] . ' minutes' : 'Not set' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Order:</strong> <?= $module['order_number'] ?></p>
                            <p><strong>Required:</strong> <?= $module['is_required'] ? 'Yes' : 'No' ?></p>
                            <p><strong>Created:</strong> <?= date('M d, Y', strtotime($module['created_at'])) ?></p>
                            <?php if ($module['published_at']): ?>
                                <p><strong>Published:</strong> <?= date('M d, Y', strtotime($module['published_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($module['description']): ?>
                        <div class="mb-4">
                            <h6>Description</h6>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($module['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($module['content']): ?>
                        <div class="mb-4">
                            <h6>Content</h6>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($module['content'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($module['file_pdf'] || $module['file_video']): ?>
                        <div class="mb-4">
                            <h6>Module Files</h6>
                            <div class="d-flex gap-2">
                                <?php if ($module['file_pdf']): ?>
                                    <a href="<?= BASE_URL ?>/uploads/pdf/<?= $module['file_pdf'] ?>" target="_blank" class="btn btn-outline-danger">
                                        <i class="fas fa-file-pdf"></i> View PDF
                                    </a>
                                <?php endif; ?>
                                <?php if ($module['file_video']): ?>
                                    <a href="<?= BASE_URL ?>/uploads/video/<?= $module['file_video'] ?>" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-video"></i> View Video
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resources Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-link"></i> Resources</h6>
                    <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#addResourceForm">
                        <i class="fas fa-plus"></i> Add Resource
                    </button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-3" id="addResourceForm">
                        <form method="post" action="?act=add_resource&module_id=<?= $module_id ?>">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <input type="text" name="title" class="form-control form-control-sm" placeholder="Title" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="url" name="url" class="form-control form-control-sm" placeholder="URL" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="type" class="form-control form-control-sm">
                                        <option value="link">Link</option>
                                        <option value="video">Video</option>
                                        <option value="document">Document</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description (optional)"></textarea>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($module_resources)): ?>
                        <p class="text-muted mb-0">No resources added yet.</p>
                    <?php else: ?>
                        <?php foreach ($module_resources as $resource): ?>
                            <div class="resource-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="<?= htmlspecialchars($resource['url']) ?>" target="_blank">
                                        <?= htmlspecialchars($resource['title']) ?>
                                    </a>
                                    <?php if ($resource['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($resource['description']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge bg-secondary me-2"><?= $resource['type'] ?></span>
                                    <a href="?act=delete_resource&resource_id=<?= $resource['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Delete this resource?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attachments Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-paperclip"></i> Attachments</h6>
                    <button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#addAttachmentForm">
                        <i class="fas fa-plus"></i> Add Attachment
                    </button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-3" id="addAttachmentForm">
                        <form method="post" enctype="multipart/form-data" action="?act=edit&module_id=<?= $module_id ?>">
                            <div class="mb-2">
                                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                                <small class="text-muted">You can select multiple files</small>
                            </div>
                            <button type="submit" name="add_attachments" class="btn btn-sm btn-primary">Upload</button>
                        </form>
                    </div>

                    <?php if (empty($module_attachments)): ?>
                        <p class="text-muted mb-0">No attachments yet.</p>
                    <?php else: ?>
                        <?php foreach ($module_attachments as $attachment): ?>
                            <div class="attachment-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="<?= BASE_URL ?>/<?= $attachment['filepath'] ?>" target="_blank">
                                        <?= htmlspecialchars($attachment['original_filename']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= round($attachment['filesize'] / 1024, 2) ?> KB</small>
                                </div>
                                <a href="?act=delete_attachment&attachment_id=<?= $attachment['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this attachment?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <!-- Default View - Show course selection -->
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Module Management</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">Select a course to manage its modules:</p>
                    <?php if (empty($courses)): ?>
                        <p class="text-muted">No courses available. <a href="courses_crud.php?act=addform">Create a course first</a>.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($courses as $course): ?>
                                <a href="?act=list&course_id=<?= $course['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($course['title']) ?>
                                    <?php
                                    $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM modules WHERE course_id = ?");
                                    $count_stmt->execute([$course['id']]);
                                    $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                    ?>
                                    <span class="badge bg-primary rounded-pill"><?= $count ?> modules</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
</body>
</html>