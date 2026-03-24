<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Only admin and superadmin can access this page
if (!is_admin() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

// ============================================
// DEPARTMENT (depts table) HANDLERS
// ============================================

// Add Department (under a Division)
if (isset($_POST['add_department'])) {
    $division_id = (int)$_POST['division_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $_SESSION['error'] = "Department name is required";
    } else {
        $stmt = $pdo->prepare("INSERT INTO depts (department_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$division_id, $name, $description]);
        $_SESSION['success'] = "Department added successfully";
    }
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// Edit Department
if (isset($_POST['edit_department'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $_SESSION['error'] = "Department name is required";
    } else {
        $stmt = $pdo->prepare("UPDATE depts SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        $_SESSION['success'] = "Department updated successfully";
    }
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// Delete Department
if (isset($_GET['delete_dept_id'])) {
    $id = (int)$_GET['delete_dept_id'];
    
    $stmt = $pdo->prepare("DELETE FROM depts WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Department deleted successfully";
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// ============================================
// COMMITTEE HANDLERS
// ============================================

// Add Committee
if (isset($_POST['add_committee'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $_SESSION['error'] = "Committee name is required";
    } else {
        $stmt = $pdo->prepare("INSERT INTO committees (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $_SESSION['success'] = "Committee added successfully";
    }
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// Edit Committee
if (isset($_POST['edit_committee'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $_SESSION['error'] = "Committee name is required";
    } else {
        $stmt = $pdo->prepare("UPDATE committees SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        $_SESSION['success'] = "Committee updated successfully";
    }
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// Delete Committee
if (isset($_GET['delete_comm_id'])) {
    $id = (int)$_GET['delete_comm_id'];
    
    $stmt = $pdo->prepare("DELETE FROM committees WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Committee deleted successfully";
    
    header('Location: deptcommittee_crud.php');
    exit;
}

// ============================================
// FETCH DATA
// ============================================

// Fetch all divisions (departments table)
$divisionsStmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$divisions = $divisionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments under each division
$departmentsByDivision = [];
foreach ($divisions as $division) {
    $deptStmt = $pdo->prepare("SELECT * FROM depts WHERE department_id = ? ORDER BY name ASC");
    $deptStmt->execute([$division['id']]);
    $departmentsByDivision[$division['id']] = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all committees
$committeesStmt = $pdo->query("SELECT * FROM committees ORDER BY created_at DESC");
$committees = $committeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get session messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';

// Clear session messages
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Department & Committee Management - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/manager.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <style>
        /* Main tabs styling */
        .main-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
        }
        
        .main-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 500;
            padding: 12px 24px;
            font-size: 15px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .main-tabs .nav-link:hover {
            color: #667eea;
            background: transparent;
            border: none;
        }
        
        .main-tabs .nav-link.active {
            color: #667eea;
            background: transparent;
            border: none;
        }
        
        .main-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        /* Division tabs styling */
        .division-tabs {
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
            margin-top: 20px;
            background: #f8fafc;
            padding: 8px 0 0 0;
            border-radius: 12px 12px 0 0;
        }
        
        .division-tabs .nav-link {
            border: none;
            color: #475569;
            font-weight: 500;
            padding: 10px 20px;
            font-size: 14px;
            transition: all 0.2s;
            border-radius: 8px 8px 0 0;
        }
        
        .division-tabs .nav-link:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.08);
        }
        
        .division-tabs .nav-link.active {
            color: #667eea;
            background: white;
            border-bottom: 2px solid #667eea;
        }
        
        /* Table container - FIXED HEIGHT WITH SCROLLBAR */
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: white;
        }
        
        .table-container table {
            margin-bottom: 0;
        }
        
        .table-container thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
            padding: 12px 16px;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
        }
        
        .table-container tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 13px;
        }
        
        /* Table column widths - NO ID COLUMN */
        .dept-table th:nth-child(1) { width: 30%; }  /* Department Name */
        .dept-table th:nth-child(2) { width: 45%; }  /* Description */
        .dept-table th:nth-child(3) { width: 25%; }  /* Actions */
        
        .comm-table th:nth-child(1) { width: 35%; }  /* Committee Name */
        .comm-table th:nth-child(2) { width: 45%; }  /* Description */
        .comm-table th:nth-child(3) { width: 20%; }  /* Actions */
        
        /* Action buttons - matching user table style */
        .table-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn-action {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-edit:hover {
            background: #ffca2c;
            transform: translateY(-1px);
            color: #212529;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
            color: white;
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
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        /* Division header with margin */
        .division-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            margin-bottom: 15px;
            padding: 0 4px;
        }
        
        .division-header-actions h5 {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Committee header with same margin */
        .committee-header-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            margin-bottom: 15px;
            padding: 0 4px;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 0;
        }
        
        /* Modal styling */
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .tab-content {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
    <div class="container-fluid py-3">
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0">Department & Committee Management</h3>
        </div>
        
        <!-- Session Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Main Tabs: Departments | Committees -->
        <ul class="nav nav-tabs main-tabs" id="mainTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="departments-main-tab" data-bs-toggle="tab" data-bs-target="#departments-main" type="button" role="tab" aria-controls="departments-main" aria-selected="true">
                    <i class="fas fa-sitemap me-2"></i>Departments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="committees-main-tab" data-bs-toggle="tab" data-bs-target="#committees-main" type="button" role="tab" aria-controls="committees-main" aria-selected="false">
                    <i class="fas fa-users me-2"></i>Committees
                </button>
            </li>
        </ul>
        
        <!-- Main Tab Content -->
        <div class="tab-content" id="mainTabContent">
            
            <!-- DEPARTMENTS MAIN TAB -->
            <div class="tab-pane fade show active" id="departments-main" role="tabpanel" aria-labelledby="departments-main-tab">
                
                <?php if (empty($divisions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <p>No divisions found. Please contact administrator to create divisions first.</p>
                    </div>
                <?php else: ?>
                    
                    <!-- Division Tabs -->
                    <ul class="nav nav-tabs division-tabs" id="divisionTab" role="tablist">
                        <?php foreach ($divisions as $index => $division): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                                        id="division-<?= $division['id'] ?>-tab" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#division-<?= $division['id'] ?>" 
                                        type="button" 
                                        role="tab">
                                    <?= htmlspecialchars($division['name']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Division Tab Content -->
                    <div class="tab-content">
                        <?php foreach ($divisions as $index => $division): 
                            $departments = $departmentsByDivision[$division['id']] ?? [];
                        ?>
                            <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
                                 id="division-<?= $division['id'] ?>" 
                                 role="tabpanel" 
                                 aria-labelledby="division-<?= $division['id'] ?>-tab">
                                
                                <div class="division-header-actions">
                                    <h5><i class="fas fa-folder-open me-2"></i>Departments under <?= htmlspecialchars($division['name']) ?></h5>
                                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addDepartmentModal" 
                                            onclick="setDivisionId(<?= $division['id'] ?>, '<?= htmlspecialchars($division['name']) ?>')">
                                        <i class="fas fa-plus me-1"></i>Add Department
                                    </button>
                                </div>
                                
                                <?php if (empty($departments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No departments found under this division</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Fixed Height Table Container with Scrollbar -->
                                    <div class="table-container">
                                        <table class="table table-hover mb-0 dept-table">
                                            <thead>
                                                <tr>
                                                    <th>Department Name</th>
                                                    <th>Description</th>
                                                    <th>Actions</th>
                                                </thead>
                                            <tbody>
                                                <?php foreach ($departments as $dept): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($dept['name']) ?>">
                                                        <?= htmlspecialchars($dept['name']) ?>
                                                    </td>
                                                    <td class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($dept['description'] ?? '') ?>">
                                                        <?= htmlspecialchars($dept['description'] ?? '—') ?>
                                                    </td>
                                                    <td>
                                                        <div class="table-actions">
                                                            <button class="btn-action btn-edit" onclick="openEditDepartmentModal(<?= $dept['id'] ?>, '<?= htmlspecialchars(addslashes($dept['name'])) ?>', '<?= htmlspecialchars(addslashes($dept['description'] ?? '')) ?>')">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <a href="?delete_dept_id=<?= $dept['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this department? This action cannot be undone.')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- COMMITTEES MAIN TAB -->
            <div class="tab-pane fade" id="committees-main" role="tabpanel" aria-labelledby="committees-main-tab">
                
                <div class="committee-header-actions">
                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addCommitteeModal">
                        <i class="fas fa-plus me-1"></i>Add Committee
                    </button>
                </div>
                
                <?php if (empty($committees)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No committees found. Add your first committee.</p>
                    </div>
                <?php else: ?>
                    <!-- Fixed Height Table Container with Scrollbar -->
                    <div class="table-container">
                        <table class="table table-hover mb-0 comm-table">
                            <thead>
                                    <th>Committee Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </thead>
                            <tbody>
                                <?php foreach ($committees as $committee): ?>
                                <tr>
                                    <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($committee['name']) ?>">
                                        <?= htmlspecialchars($committee['name']) ?>
                                    </td>
                                    <td class="text-truncate" style="max-width: 350px;" title="<?= htmlspecialchars($committee['description'] ?? '') ?>">
                                        <?= htmlspecialchars($committee['description'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-action btn-edit" onclick="openEditCommitteeModal(<?= $committee['id'] ?>, '<?= htmlspecialchars(addslashes($committee['name'])) ?>', '<?= htmlspecialchars(addslashes($committee['description'] ?? '')) ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete_comm_id=<?= $committee['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this committee? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODALS -->
<!-- ============================================ -->

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="division_id" id="add_dept_division_id">
                    <div class="mb-3">
                        <label class="form-label">Division</label>
                        <input type="text" class="form-control" id="add_dept_division_name" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="Enter department name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Enter department description (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_department">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_dept_id">
                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_dept_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_dept_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" name="edit_department">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Committee Modal -->
<div class="modal fade" id="addCommitteeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Committee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Committee Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="Enter committee name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Enter committee description (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_committee">Add Committee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Committee Modal -->
<div class="modal fade" id="editCommitteeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Committee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_comm_id">
                    <div class="mb-3">
                        <label class="form-label">Committee Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_comm_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_comm_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" name="edit_committee">Save Changes</button>
                </div>
            </form>
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
    
    // Set division ID for Add Department modal
    function setDivisionId(divisionId, divisionName) {
        document.getElementById('add_dept_division_id').value = divisionId;
        document.getElementById('add_dept_division_name').value = divisionName;
    }
    
    // Open Edit Department Modal
    function openEditDepartmentModal(id, name, description) {
        document.getElementById('edit_dept_id').value = id;
        document.getElementById('edit_dept_name').value = name;
        document.getElementById('edit_dept_description').value = description;
        
        var modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
        modal.show();
    }
    
    // Open Edit Committee Modal
    function openEditCommitteeModal(id, name, description) {
        document.getElementById('edit_comm_id').value = id;
        document.getElementById('edit_comm_name').value = name;
        document.getElementById('edit_comm_description').value = description;
        
        var modal = new bootstrap.Modal(document.getElementById('editCommitteeModal'));
        modal.show();
    }
    
    // Keep active tab state after page reload
    document.addEventListener('DOMContentLoaded', function() {
        // Save main tab state
        const activeMainTab = sessionStorage.getItem('activeMainTab');
        if (activeMainTab) {
            const tabButton = document.querySelector(`#mainTab button[data-bs-target="${activeMainTab}"]`);
            if (tabButton) {
                const tab = new bootstrap.Tab(tabButton);
                tab.show();
            }
        }
        
        // Save division tab state
        const activeDivisionTab = sessionStorage.getItem('activeDivisionTab');
        if (activeDivisionTab) {
            const tabButton = document.querySelector(`#divisionTab button[data-bs-target="${activeDivisionTab}"]`);
            if (tabButton) {
                const tab = new bootstrap.Tab(tabButton);
                tab.show();
            }
        }
        
        // Save active main tab on click
        document.querySelectorAll('#mainTab button').forEach(button => {
            button.addEventListener('click', function() {
                sessionStorage.setItem('activeMainTab', this.getAttribute('data-bs-target'));
            });
        });
        
        // Save active division tab on click
        document.querySelectorAll('#divisionTab button').forEach(button => {
            button.addEventListener('click', function() {
                sessionStorage.setItem('activeDivisionTab', this.getAttribute('data-bs-target'));
            });
        });
    });
</script>
</body>
</html>