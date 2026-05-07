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
$module_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fetch committees assigned to the current user
if (is_superadmin() || is_admin()) {
    $committee_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name ASC");
    $user_committees = $committee_stmt->fetchAll();
} else {
    $committee_stmt = $pdo->prepare("
        SELECT c.id, c.name 
        FROM committees c
        JOIN user_departments ud ON ud.committee_id = c.id
        WHERE ud.user_id = ? AND ud.committee_id IS NOT NULL
        ORDER BY c.name
    ");
    $committee_stmt->execute([$_SESSION['user']['id']]);
    $user_committees = $committee_stmt->fetchAll();
}

// Fetch all committees for admins
$all_committees_stmt = $pdo->query("SELECT id, name FROM committees ORDER BY name ASC");
$all_committees = $all_committees_stmt->fetchAll();

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
 * Check if current user can modify module
 */
function canModifyModule($module_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT created_by FROM modules WHERE id = ?");
    $stmt->execute([$module_id]);
    $result = $stmt->fetch();
    
    return $result && $result['created_by'] == $_SESSION['user']['id'];
}

/* =========================
MODULE HANDLERS
========================= */

// Handle ADD MODULE
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : null;

    if (empty($title)) {
        $_SESSION['error_message'] = "Title is required";
        header('Location: module_crud.php?act=add');
        exit;
    }

    // Handle file uploads
    $thumbnail = uploadFile('thumbnail', 'images', ['jpg', 'jpeg', 'png', 'webp']);
    $pdf_file = uploadFile('file_pdf', 'pdf', ['pdf']);
    $video_file = uploadFile('file_video', 'video', ['mp4', 'webm']);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO modules (
                title, description, thumbnail, file_pdf, file_video, committee_id, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $title, $description, $thumbnail, $pdf_file, $video_file, $committee_id, $_SESSION['user']['id']
        ]);

        $_SESSION['success_message'] = 'Module added successfully!';
        header('Location: module_crud.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to add module: ' . $e->getMessage();
        header('Location: module_crud.php?act=add');
        exit;
    }
}

// Handle EDIT MODULE
if ($act === 'edit' && $module_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $committee_id = !empty($_POST['committee_id']) ? (int)$_POST['committee_id'] : null;

    if (empty($title)) {
        $_SESSION['error_message'] = "Title is required";
        header('Location: module_crud.php?act=edit&id=' . $module_id);
        exit;
    }

    // Handle file uploads
    $thumbnail = uploadFile('thumbnail', 'images', ['jpg', 'jpeg', 'png', 'webp']);
    $pdf_file = uploadFile('file_pdf', 'pdf', ['pdf']);
    $video_file = uploadFile('file_video', 'video', ['mp4', 'webm']);

    $sql = "UPDATE modules SET title = ?, description = ?, committee_id = ?";
    $params = [$title, $description, $committee_id];

    if ($thumbnail) {
        $sql .= ", thumbnail = ?";
        $params[] = $thumbnail;
    }
    if ($pdf_file) {
        $sql .= ", file_pdf = ?";
        $params[] = $pdf_file;
    }
    if ($video_file) {
        $sql .= ", file_video = ?";
        $params[] = $video_file;
    }

    $sql .= ", updated_at = NOW() WHERE id = ?";
    $params[] = $module_id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_message'] = 'Module updated successfully!';
        header('Location: module_crud.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to update module: ' . $e->getMessage();
        header('Location: module_crud.php?act=edit&id=' . $module_id);
        exit;
    }
}

// Handle DELETE MODULE
if ($act === 'delete' && $module_id) {
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
    }

    // Get file names to delete
    $stmt = $pdo->prepare("SELECT thumbnail, file_pdf, file_video FROM modules WHERE id = ?");
    $stmt->execute([$module_id]);
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

    try {
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$module_id]);

        $_SESSION['success_message'] = 'Module deleted successfully!';
        header('Location: module_crud.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Failed to delete module: ' . $e->getMessage();
        header('Location: module_crud.php');
        exit;
    }
}

/* =========================
GET DATA FOR DISPLAY
========================= */

// Fetch all modules with committee and creator info
$modules_stmt = $pdo->query("
    SELECT m.*, u.username as creator_name, c.name as committee_name
    FROM modules m
    LEFT JOIN users u ON m.created_by = u.id
    LEFT JOIN committees c ON m.committee_id = c.id
    ORDER BY m.created_at DESC
");
$modules = $modules_stmt->fetchAll();

// Get single module for editing
$edit_module = null;
if ($act === 'edit' && $module_id) {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$module_id]);
    $edit_module = $stmt->fetch();
    
    if (!$edit_module) {
        $_SESSION['error_message'] = 'Module not found';
        header('Location: module_crud.php');
        exit;
    }
    
    if (!canModifyModule($module_id, $pdo)) {
        http_response_code(403);
        exit('Access denied');
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Module Management - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <style>
        /* Search bar */
        .header-with-search {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-bar {
            position: relative;
            width: 300px;
        }
        
        .search-bar i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        
        .btn-add {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        /* Form styling */
        .module-form {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }
        
        /* Committee badge */
        .committee-badge {
            display: inline-block;
            background-color: #8227a9;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-top: 8px;
        }

        /* Committee styling - matching course cards */
        .committee-container {
            margin: 10px 0;
            padding: 5px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .committee-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .committee-badge-module {
            display: inline-block;
            background-color: #8227a9;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        /* ADDED: Custom Modal CSS for Module Delete Confirmation */
        .module-delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .module-delete-modal.active {
            display: flex;
        }

        .module-delete-content {
            background: white;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .module-delete-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .module-delete-header i {
            font-size: 22px;
            color: #dc3545;
        }

        .module-delete-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #111827;
        }

        .module-delete-body {
            padding: 20px 24px;
        }

        .module-delete-body p {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .module-delete-body .module-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            border-left: 3px solid #dc3545;
        }

        .module-delete-body .module-info strong {
            display: block;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .module-delete-body .module-info small {
            color: #64748b;
            font-size: 12px;
        }

        .module-warning-note {
            background: #fef3c7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .module-warning-note i {
            color: #f59e0b;
        }

        .module-delete-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .module-delete-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .module-delete-footer .btn-cancel-module {
            background: #f3f4f6;
            color: #374151;
        }

        .module-delete-footer .btn-cancel-module:hover {
            background: #e5e7eb;
        }

        .module-delete-footer .btn-confirm-module {
            background: #dc3545;
            color: white;
        }

        .module-delete-footer .btn-confirm-module:hover {
            background: #c82333;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
    <div class="container-fluid py-4">
        <div class="header-with-search">
            <h3 class="m-0">Module Management</h3>
            <?php if ($act !== 'add' && $act !== 'edit'): ?>
                <div class="d-flex gap-3">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="moduleSearch" placeholder="Search modules...">
                    </div>
                    <a href="?act=add" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add New Module
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Session Messages -->
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

        <!-- Add Module Form -->
        <?php if ($act === 'add'): ?>
            <div class="module-form">
                <h5 class="mb-4">Add New Module</h5>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Program Committee</label>
                        <select name="committee_id" class="form-select">
                            <option value="">-- Select Committee --</option>
                            <?php 
                            $display_committees = (is_superadmin() || is_admin()) ? $all_committees : $user_committees;
                            if (!empty($display_committees)):
                                foreach ($display_committees as $comm):
                            ?>
                                <option value="<?= $comm['id'] ?>"><?= htmlspecialchars($comm['name']) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <small class="text-muted">Select the committee this module belongs to</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Thumbnail (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">PDF File (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Video File (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Add Module</button>
                        <a href="module_crud.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        <!-- Edit Module Form -->
        <?php elseif ($act === 'edit' && $edit_module): ?>
            <div class="module-form">
                <h5 class="mb-4">Edit Module: <?= htmlspecialchars($edit_module['title']) ?></h5>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($edit_module['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($edit_module['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Program Committee</label>
                        <select name="committee_id" class="form-select">
                            <option value="">-- Select Committee --</option>
                            <?php 
                            $display_committees = (is_superadmin() || is_admin()) ? $all_committees : $user_committees;
                            if (!empty($display_committees)):
                                foreach ($display_committees as $comm):
                            ?>
                                <option value="<?= $comm['id'] ?>" <?= ($edit_module['committee_id'] == $comm['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comm['name']) ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                        <small class="text-muted">Select the committee this module belongs to</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Thumbnail (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if ($edit_module['thumbnail']): ?>
                            <small class="text-muted d-block mt-1">Current: <?= htmlspecialchars($edit_module['thumbnail']) ?> (leave empty to keep)</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">PDF File (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                        <?php if ($edit_module['file_pdf']): ?>
                            <small class="text-muted d-block mt-1">Current: <?= htmlspecialchars($edit_module['file_pdf']) ?> (leave empty to keep)</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Video File (Max: <?= $max_upload_size ?>)</label>
                        <input type="file" name="file_video" class="form-control" accept="video/mp4,video/webm">
                        <?php if ($edit_module['file_video']): ?>
                            <small class="text-muted d-block mt-1">Current: <?= htmlspecialchars($edit_module['file_video']) ?> (leave empty to keep)</small>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Module</button>
                        <a href="module_crud.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        <!-- Module List - Card View -->
        <?php else: ?>
            <div class="d-flex justify-content-end mb-3">
            </div>
            
            <?php if (empty($modules)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No modules found. Click "Add New Module" to create your first module.</p>
                </div>
            <?php else: ?>
                <div class="modern-courses-grid" id="modulesGrid">
                    <?php foreach ($modules as $mod): ?>
                        <div class="modern-course-card module-item" 
                            data-module-title="<?= strtolower(htmlspecialchars($mod['title'])) ?>" 
                            data-module-desc="<?= strtolower(htmlspecialchars($mod['description'] ?? '')) ?>">
                            <div class="modern-card-img">
                                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($mod['thumbnail'] ?: 'placeholder.png') ?>" 
                                    alt="<?= htmlspecialchars($mod['title']) ?>"
                                    onerror="this.src='<?= BASE_URL ?>/uploads/images/placeholder.png'">
                            </div>
                            <div class="modern-card-body">
                                <div class="modern-card-title">
                                    <h6><?= htmlspecialchars($mod['title']) ?></h6>
                                </div>
                                <p><?= htmlspecialchars(substr($mod['description'] ?? '', 0, 100)) ?>...</p>
                                
                                <!-- Program Committee Badge -->
                                <div class="committee-container">
                                    <div class="committee-label">
                                        Program Committee:
                                    </div>
                                    <div>
                                        <?php if ($mod['committee_name']): ?>
                                            <span class="committee-badge-module" style="background-color: #8227a9; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;">
                                                <?= htmlspecialchars($mod['committee_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="modern-course-info mt-2">
                                    <p><strong>Created by:</strong> <?= htmlspecialchars($mod['creator_name'] ?? 'Unknown') ?></p>
                                    <p><strong>Created:</strong> <?= date('M d, Y', strtotime($mod['created_at'])) ?></p>
                                </div>
                                
                                <div class="modern-card-actions">
                                    <a href="../public/module_view.php?id=<?= $mod['id'] ?>" class="modern-btn-primary modern-btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if (canModifyModule($mod['id'], $pdo)): ?>
                                        <a href="?act=edit&id=<?= $mod['id'] ?>" class="modern-btn-warning modern-btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?act=delete&id=<?= $mod['id'] ?>" class="modern-btn-danger modern-btn-sm delete-module-link" 
                                           data-module-id="<?= $mod['id'] ?>"
                                           data-module-title="<?= htmlspecialchars($mod['title']) ?>"
                                           data-module-creator="<?= htmlspecialchars($mod['creator_name'] ?? 'Unknown') ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- No Results Template -->
                <div class="no-results" id="noResults" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h5>No modules found</h5>
                    <p>Try adjusting your search term</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ADDED: Custom Module Delete Confirmation Modal -->
<div class="module-delete-modal" id="moduleDeleteModal">
    <div class="module-delete-content">
        <div class="module-delete-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Delete Module</h3>
        </div>
        <div class="module-delete-body">
            <p>Are you sure you want to delete this module?</p>
            <div class="module-info">
                <strong id="deleteModuleName">Module Name</strong>
                <small id="deleteModuleCreator">Created by: Creator</small>
            </div>
            <div class="module-warning-note">
                <i class="fas fa-exclamation-circle"></i>
                <span>Warning: This action cannot be undone. This will permanently delete the module and all associated files.</span>
            </div>
        </div>
        <div class="module-delete-footer">
            <button class="btn-cancel-module" id="cancelModuleDeleteBtn">Cancel</button>
            <button class="btn-confirm-module" id="confirmModuleDeleteBtn">Delete Module</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        let alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Real-time search for modules
    const searchInput = document.getElementById('moduleSearch');
    const modulesGrid = document.getElementById('modulesGrid');
    const noResults = document.getElementById('noResults');
    const moduleItems = document.querySelectorAll('.module-item');
    
    if (searchInput && modulesGrid) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            moduleItems.forEach(item => {
                const moduleTitle = item.getAttribute('data-module-title') || '';
                const moduleDesc = item.getAttribute('data-module-desc') || '';
                
                if (moduleTitle.includes(searchTerm) || moduleDesc.includes(searchTerm) || searchTerm === '') {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            if (noResults) {
                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                    modulesGrid.style.display = 'none';
                } else {
                    noResults.style.display = 'none';
                    modulesGrid.style.display = 'grid';
                }
            }
        });
    }

    // ADDED: Custom Module Delete Modal Logic
    const moduleDeleteModal = document.getElementById('moduleDeleteModal');
    const deleteModuleName = document.getElementById('deleteModuleName');
    const deleteModuleCreator = document.getElementById('deleteModuleCreator');
    const confirmModuleDeleteBtn = document.getElementById('confirmModuleDeleteBtn');
    const cancelModuleDeleteBtn = document.getElementById('cancelModuleDeleteBtn');
    
    let pendingModuleDeleteUrl = null;

    // Function to close modal
    function closeModuleDeleteModal() {
        moduleDeleteModal.classList.remove('active');
        pendingModuleDeleteUrl = null;
    }

    // Function to show delete modal
    function showModuleDeleteModal(moduleId, moduleTitle, moduleCreator) {
        deleteModuleName.textContent = moduleTitle;
        deleteModuleCreator.textContent = 'Created by: ' + moduleCreator;
        pendingModuleDeleteUrl = '?act=delete&id=' + moduleId;
        moduleDeleteModal.classList.add('active');
    }

    // Confirm delete button
    if (confirmModuleDeleteBtn) {
        confirmModuleDeleteBtn.onclick = function() {
            if (pendingModuleDeleteUrl) {
                window.location.href = pendingModuleDeleteUrl;
            }
        };
    }

    // Cancel button
    if (cancelModuleDeleteBtn) {
        cancelModuleDeleteBtn.onclick = closeModuleDeleteModal;
    }

    // Close modal when clicking outside
    if (moduleDeleteModal) {
        moduleDeleteModal.addEventListener('click', function(e) {
            if (e.target === moduleDeleteModal) {
                closeModuleDeleteModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && moduleDeleteModal && moduleDeleteModal.classList.contains('active')) {
            closeModuleDeleteModal();
        }
    });

    // Override delete links
    document.querySelectorAll('.delete-module-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const moduleId = this.getAttribute('data-module-id');
            const moduleTitle = this.getAttribute('data-module-title');
            const moduleCreator = this.getAttribute('data-module-creator');
            showModuleDeleteModal(moduleId, moduleTitle, moduleCreator);
        });
    });
</script>
</body>
</html>