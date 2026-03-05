<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

$act = $_GET['act'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Fetch all departments for dropdown
$dept_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$departments = $dept_stmt->fetchAll();

// Initialize course departments array
$course_departments = [];

// If editing, verify course exists and user has permission
if ($act === 'edit' && $id) {
    // First, check if course exists and get its data
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error_message'] = 'Course not found';
        header('Location: courses_crud.php');
        exit;
    }
    
    // Check permission: 
    // - Superadmin/Admin can edit ANY course
    // - Proponents can only edit their own courses
    if (!is_admin() && !is_superadmin() && $course['proponent_id'] != $_SESSION['user']['id']) {
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
}

/**
 * Calculate expiration date
 */
function calculateExpiry($expires_at, $valid_days) {
    if (!empty($valid_days) && is_numeric($valid_days) && $valid_days > 0) {
        return date('Y-m-d', strtotime("+{$valid_days} days"));
    }
    return $expires_at ?: null;
}

/**
 * Handle file upload
 */
function uploadFile($input, $dir, $allowed = []) {
    if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
    if ($allowed && !in_array($ext, $allowed)) {
        return null;
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $upload_path = __DIR__ . "/../uploads/$dir/";
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_path . $filename)) {
        return $filename;
    }

    return null;
}

/**
 * Check if current user can edit/delete course
 * Returns true for admins/superadmins OR if user owns the course
 */
function canModifyCourse($course_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true; // Superadmin and admin can modify ANY course
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
            
            // Validate department ID exists
            $check_dept = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
            $check_dept->execute([$dept_id]);
            if ($check_dept->fetch()) {
                $insert_stmt->execute([
                    ':course_id' => $course_id,
                    ':department_id' => $dept_id
                ]);
            }
        }
    }
}

// First, check if course_departments table exists and create it if not
try {
    $pdo->query("SELECT 1 FROM course_departments LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist, create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `course_departments` (
            `course_id` int(11) NOT NULL,
            `department_id` int(11) NOT NULL,
            PRIMARY KEY (`course_id`, `department_id`),
            KEY `department_id` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    
    // Add foreign key constraints if they don't exist
    try {
        $pdo->exec("ALTER TABLE `course_departments` ADD CONSTRAINT `course_departments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE");
        $pdo->exec("ALTER TABLE `course_departments` ADD CONSTRAINT `course_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE");
    } catch (Exception $e) {
        // Foreign keys might already exist
    }
}

/* =========================
ADD COURSE
========================= */
if ($act === 'addform' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $expires_at = calculateExpiry(
        $_POST['expires_at'] ?? null,
        $_POST['valid_days'] ?? null
    );

    $stmt = $pdo->prepare("
        INSERT INTO courses (
            title, description, summary, thumbnail, file_pdf, file_video,
            proponent_id, created_at, expires_at, is_active
        ) VALUES (
            :title, :description, :summary, :thumbnail, :pdf, :video,
            :proponent_id, NOW(), :expires_at, 1
        )
    ");

    $stmt->execute([
        ':title'         => $_POST['title'],
        ':description'   => $_POST['description'],
        ':summary'       => $_POST['summary'],
        ':thumbnail'     => uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp','gif']),
        ':pdf'           => uploadFile('file_pdf', 'pdf', ['pdf']),
        ':video'         => uploadFile('file_video', 'video', ['mp4','webm','avi','mov']),
        ':proponent_id'  => $_SESSION['user']['id'],
        ':expires_at'    => $expires_at
    ]);

    $course_id = $pdo->lastInsertId();

    // Save department associations
    if (isset($_POST['departments']) && is_array($_POST['departments'])) {
        saveCourseDepartments($course_id, $_POST['departments'], $pdo);
    }

    $_SESSION['success_message'] = 'Course added successfully!';
    header('Location: courses_crud.php');
    exit;
}

/* =========================
EDIT COURSE
========================= */
if ($act === 'edit' && $id) {
    // Course data already fetched above
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $expires_at = calculateExpiry(
            $_POST['expires_at'] ?? null,
            $_POST['valid_days'] ?? null
        );

        // Handle file uploads
        $thumbnail = uploadFile('thumbnail','images',['jpg','jpeg','png','webp','gif']);
        $pdf = uploadFile('file_pdf','pdf',['pdf']);
        $video = uploadFile('file_video','video',['mp4','webm','avi','mov']);

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
}

/* =========================
DELETE COURSE
========================= */
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
FETCH COURSES - WITH PROPER FILTERING
========================= */

// First, check if updated_at column exists and add it if not
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

// FIXED: Proper filtering based on user role
if (is_admin() || is_superadmin()) {
    // Admin/Superadmin: See ALL courses
    $stmt = $pdo->query("
        SELECT c.*, u.username 
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        ORDER BY c.updated_at DESC, c.created_at DESC
    ");
} else {
    // Proponent: See ONLY their own courses
    $stmt = $pdo->prepare("
        SELECT c.*, u.username 
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.proponent_id = :user_id
        ORDER BY c.updated_at DESC, c.created_at DESC
    ");
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
}
$courses = $stmt->fetchAll();

// Fetch departments for each course
foreach ($courses as &$course) {
    try {
        $dept_stmt = $pdo->prepare("
            SELECT d.id, d.name 
            FROM departments d
            INNER JOIN course_departments cd ON d.id = cd.department_id
            WHERE cd.course_id = :course_id
            ORDER BY d.name
        ");
        $dept_stmt->execute([':course_id' => $course['id']]);
        $course['departments'] = $dept_stmt->fetchAll();
    } catch (Exception $e) {
        $course['departments'] = [];
    }
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Courses CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/course.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .department-badge {
            display: inline-block;
            background-color: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .department-selector {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.5rem;
        }
        .department-selector .form-check {
            margin-bottom: 0.25rem;
        }
        .alert {
            margin-bottom: 1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.25rem;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .read-only-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.3rem;
            background-color: #6c757d;
            color: white;
            border-radius: 0.25rem;
            margin-left: 0.25rem;
        }
        .owned-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.3rem;
            background-color: #28a745;
            color: white;
            border-radius: 0.25rem;
            margin-left: 0.25rem;
        }
        .admin-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.3rem;
            background-color: #dc3545;
            color: white;
            border-radius: 0.25rem;
            margin-left: 0.25rem;
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

    <?php if (is_proponent() && !is_admin() && !is_superadmin()): ?>
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i> You are viewing only your own courses.
        </div>
    <?php endif; ?>

    <?php if (is_admin() || is_superadmin()): ?>
        <div class="alert alert-primary mb-3">
            <i class="fas fa-shield-alt"></i> You have full access as <?= is_superadmin() ? 'Superadmin' : 'Admin' ?>. You can edit and delete any course.
        </div>
    <?php endif; ?>

    <?php if ($act === 'addform' || $act === 'edit'): ?>
        <?php $editing = ($act === 'edit'); ?>
        <?php 
            // For add form, initialize with empty array or POST data
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['departments'])) {
                $selected_depts = $_POST['departments'];
            } elseif ($editing) {
                $selected_depts = $course_departments;
            } else {
                $selected_depts = [];
            }
        ?>

        <div class="card p-4 mb-4 shadow-sm bg-white rounded">
            <form method="post" enctype="multipart/form-data">
                <?php if ($editing): ?>
                    <input type="hidden" name="course_id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label>Course Title</label>
                    <input name="title" class="form-control" placeholder="Title" required
                           value="<?= $editing ? htmlspecialchars($course['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '') ?>">
                </div>

                <div class="mb-3">
                    <label>Course Description</label>
                    <input name="description" class="form-control" placeholder="Description" required
                           value="<?= $editing ? htmlspecialchars($course['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?>">
                </div>

                <div class="mb-3">
                    <label>Course Summary</label>
                    <textarea name="summary" class="form-control" rows="4" required
                              placeholder="Course Summary"><?= $editing ? htmlspecialchars($course['summary']) : (isset($_POST['summary']) ? htmlspecialchars($_POST['summary']) : '') ?></textarea>
                </div>

                <!-- Department Selection -->
                <div class="mb-3">
                    <label>Departments</label>
                    <div class="department-selector">
                        <?php if (empty($departments)): ?>
                            <p class="text-muted mb-0">No departments available. Please add departments first.</p>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="departments[]" 
                                           value="<?= $dept['id'] ?>" 
                                           id="dept_<?= $dept['id'] ?>"
                                           <?= in_array($dept['id'], $selected_depts) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="dept_<?= $dept['id'] ?>">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Select one or more departments for this course</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Expiration Date</label>
                        <input type="date" name="expires_at" id="expires_at" class="form-control"
                               value="<?= $editing && $course['expires_at'] ? $course['expires_at'] : (isset($_POST['expires_at']) ? $_POST['expires_at'] : '') ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Validity (Days)</label>
                        <input type="number" name="valid_days" id="valid_days" class="form-control"
                               placeholder="Example: 5"
                               value="<?= ($editing && !empty($course['expires_at']))
                                   ? max(0, (int) ceil((strtotime($course['expires_at']) - time()) / 86400))
                                   : (isset($_POST['valid_days']) ? $_POST['valid_days'] : '') ?>">
                        <small class="text-muted">Auto-calculated when date is selected</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Thumbnail</label>
                    <input type="file" name="thumbnail" class="form-control" accept="image/*">
                    <?php if ($editing && $course['thumbnail']): ?>
                        <div class="mt-2">
                            <img src="<?= BASE_URL ?>/uploads/images/<?= $course['thumbnail'] ?>" width="120" class="border rounded">
                            <small class="text-muted d-block">Leave empty to keep current image</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label>PDF</label>
                    <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                    <?php if ($editing && $course['file_pdf']): ?>
                        <div class="mt-2">
                            <a href="<?= BASE_URL ?>/uploads/pdf/<?= $course['file_pdf'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file-pdf"></i> View PDF
                            </a>
                            <small class="text-muted d-block">Leave empty to keep current PDF</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label>Video</label>
                    <input type="file" name="file_video" class="form-control" accept="video/*">
                    <?php if ($editing && $course['file_video']): ?>
                        <div class="mt-2">
                            <a href="<?= BASE_URL ?>/uploads/video/<?= $course['file_video'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-video"></i> View Video
                            </a>
                            <small class="text-muted d-block">Leave empty to keep current video</small>
                        </div>
                    <?php endif; ?>
                </div>

                <button class="btn btn-primary"><?= $editing ? 'Update Course' : 'Add Course' ?></button>
                <a href="courses_crud.php" class="btn btn-secondary ms-2">Back</a>
            </form>
        </div>

    <?php else: ?>

        <a href="?act=addform" class="btn btn-success mb-3">Add New Course</a>

        <?php if (empty($courses)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No courses found.
                <?php if (is_proponent() && !is_admin() && !is_superadmin()): ?>
                    Click "Add New Course" to create your first course.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="modern-courses-grid">
                <?php foreach ($courses as $c): ?>
                    <div class="modern-course-card">
                        <div class="modern-card-img">
                            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course Image">
                        </div>
                        <div class="modern-card-body">
                            <div class="modern-card-title">
                                <h6>
                                    <?= htmlspecialchars($c['title']) ?>
                                    <?php if ($c['proponent_id'] == $_SESSION['user']['id']): ?>
                                        <span class="owned-badge">Your Course</span>
                                    <?php endif; ?>
                                    <?php if ((is_admin() || is_superadmin()) && $c['proponent_id'] != $_SESSION['user']['id']): ?>
                                        <span class="admin-badge">Admin Access</span>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <p><?= htmlspecialchars(substr($c['description'], 0, 100)) ?>...</p>

                            <!-- Departments -->
                            <?php if (!empty($c['departments'])): ?>
                                <div class="mb-2">
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

                                    // Format updated date
                                    $updatedDate = !empty($c['updated_at']) 
                                        ? date('M d, Y h:i A', strtotime($c['updated_at'])) 
                                        : 'Never';
                                ?>
                                <p><strong>Created by:</strong> <span><?= htmlspecialchars($c['username'] ?? 'Unknown') ?></span></p>
                                <p><strong>Start:</strong> <span><?= $startDate ?></span></p>
                                <p><strong>Expires:</strong> <span><?= $expiryDate ?></span></p>
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
                                <!-- View button - Everyone can view -->
                                <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="modern-btn-primary modern-btn-sm">View</a>

                                <?php if (is_admin() || is_superadmin() || $c['proponent_id'] == $_SESSION['user']['id']): ?>
                                    <a href="?act=edit&id=<?= $c['id'] ?>" class="modern-btn-warning modern-btn-sm">Edit</a>
                                    <a href="?act=delete&id=<?= $c['id'] ?>" class="modern-btn-danger modern-btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">Delete</a>
                                <?php else: ?>
                                    <span class="btn btn-secondary btn-sm" disabled title="You can only edit your own courses">
                                        <i class="fas fa-lock"></i> Read Only
                                    </span>
                                <?php endif; ?>
                            </div>  
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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

            // Also handle days input change
            days.addEventListener('change', function () {
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