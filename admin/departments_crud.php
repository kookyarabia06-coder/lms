<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';
require_login();

// Only admin can access this page
if (!is_admin() && !is_superadmin()) {
    echo 'Admin only';
    exit;
}

// Add main department (to departments table)
if (isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

// Add division (to depts table, linked to departments)
if (isset($_POST['add_division'])) {
    $department_id = $_POST['department_id'];
    $division_name = $_POST['division_name'];
    $division_description = $_POST['division_description'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO depts (department_id, name, description) VALUES (?, ?, ?)");
    $stmt->execute([$department_id, $division_name, $division_description]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

// Delete main department (from departments table)
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    // First delete all divisions under this main department
    $stmt = $pdo->prepare("DELETE FROM depts WHERE department_id = ?");
    $stmt->execute([$id]);
    
    // Then delete the main department
    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

// Edit main department (departments table)
if (isset($_POST['edit_department'])) {
    $id = $_POST['id'];
    $name = $_POST['name']; 
    $description = $_POST['description'] ?? '';

    $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

// Edit division (depts table)
if (isset($_POST['edit_division'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'] ?? '';

    $stmt = $pdo->prepare("UPDATE depts SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

// Delete division (from depts table)
if (isset($_GET['delete_division_id'])) {
    $id = $_GET['delete_division_id'];

    $stmt = $pdo->prepare("DELETE FROM depts WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

?>

<html>
<head>
    <title>Division and Department Management</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Modern CSS Reset and Variables - Minimalist Design */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --success: #16a34a;
            --warning: #ca8a04;
            --danger: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius: 0.375rem;
            --radius-lg: 0.5rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #ffffff;
            color: var(--gray-900);
            line-height: 1.5;
        }

        /* Main Layout */
        .lms-sidebar-container {
            float: left;
            width: 250px;
            position: fixed;
            height: 100vh;
            background: white;
            border-right: 1px solid var(--gray-200);
            z-index: 10;
        }

        .container.mt-4 {
            margin-left: 250px;
            padding: 2rem;
            max-width: calc(100% - 250px);
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--gray-900);
            margin: 0;
        }

        /* Buttons - Minimalist */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            gap: 0.375rem;
            background: white;
            color: var(--gray-700);
            border-color: var(--gray-200);
        }

        .btn:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-300);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }

        .btn-success {
            background-color: white;
            color: var(--success);
            border-color: var(--gray-200);
        }

        .btn-success:hover {
            background-color: var(--gray-50);
            border-color: var(--success);
        }

        .btn-warning {
            background-color: white;
            color: var(--warning);
            border-color: var(--gray-200);
        }

        .btn-warning:hover {
            background-color: var(--gray-50);
            border-color: var(--warning);
        }

        .btn-danger {
            background-color: white;
            color: var(--danger);
            border-color: var(--gray-200);
        }

        .btn-danger:hover {
            background-color: var(--gray-50);
            border-color: var(--danger);
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Departments Grid */
        .departments-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            margin-top: 1rem;
        }

        /* Left Panel - Departments List */
        .departments-panel {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .panel-header {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid var(--gray-200);
        }

        .panel-header h3 {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: var(--gray-500);
            margin: 0;
        }

        .departments-list {
            max-height: 600px;
            overflow-y: auto;
        }

        /* Department items - only showing name */
        .department-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .department-item:hover {
            background-color: var(--gray-50);
        }

        .department-item.active {
            background-color: var(--gray-100);
            border-left: 3px solid var(--primary);
        }

        .department-name {
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--gray-900);
        }

        /* Right Panel - Divisions Panel */
        .divisions-panel {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .divisions-header {
            padding: 1rem;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .divisions-header h3 {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-900);
            margin: 0;
        }

        .divisions-header h3 span {
            color: var(--gray-500);
            font-weight: 400;
            margin-left: 0.5rem;
            font-size: 0.875rem;
        }

        .divisions-list {
            padding: 1rem;
        }

        .divisions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .divisions-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .divisions-table td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100);
        }

        .divisions-table tr {
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .divisions-table tr:hover td {
            background-color: var(--gray-50);
        }

        .divisions-table tr.active td {
            background-color: var(--gray-100);
        }

        .division-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Division Detail View */
        .division-detail {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .detail-header h4 {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        .detail-content {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            width: 120px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--gray-500);
        }

        .detail-value {
            flex: 1;
            font-size: 0.9375rem;
            color: var(--gray-800);
        }

        .detail-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state p {
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Modal Styling - Minimalist */
        .modal-content {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }

        .modal-header .modal-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-900);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-body .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
            display: block;
        }

        .modal-body .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .modal-body .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            background: white;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Hide original table */
        .table-bordered {
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .lms-sidebar-container {
                position: static;
                width: 100%;
                height: auto;
                float: none;
            }

            .container.mt-4 {
                margin-left: 0;
                padding: 1rem;
                max-width: 100%;
            }

            .departments-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="container mt-4">
    <div class="page-header">
        <h2>Divisions & Departments</h2>   
    </div>

    <!-- Departments Grid -->
    <div class="departments-grid">
        <!-- Left Panel - Departments List - Shows only department names -->
        <div class="departments-panel">
            <div class="panel-header">
                <h3>All Divisions</h3>
            </div>
            <div class="departments-list">
                <?php
                $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY id DESC");
                if ($dept_stmt->rowCount() > 0) {
                    $first_dept = true;
                    while ($dept_row = $dept_stmt->fetch()) {
                        ?>
                        <!-- Only showing department name, no description -->
                        <div class="department-item <?= $first_dept ? 'active' : '' ?>" 
                             onclick="showDepartment(<?= $dept_row['id'] ?>, this)">
                            <div class="department-name"><?= htmlspecialchars($dept_row['name']) ?></div>
                        </div>
                        <?php
                        $first_dept = false;
                    }
                } else {
                    ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📁</div>
                        <p>No departments found</p>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            Create your first department
                        </button>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <!-- Right Panel - Divisions Panel -->
        <div class="divisions-panel">
            <?php
            $dept_stmt2 = $pdo->query("SELECT * FROM departments ORDER BY id DESC");
            if ($dept_stmt2->rowCount() > 0) {
                $first_panel = true;
                while ($dept_row = $dept_stmt2->fetch()) {
                    ?>
                    <div id="dept-<?= $dept_row['id'] ?>" class="department-divisions" 
                         style="<?= $first_panel ? 'display: block;' : 'display: none;' ?>">
                        
                        <!-- Original divisions header - no description -->
                        <div class="divisions-header">
                            <h3>Department</h3>
                            <button class="btn btn-success btn-sm" onclick="openAddDivisionModal(<?= $dept_row['id'] ?>)">
                                + Add Department
                            </button>
                        </div>

                        <?php
                        $division_stmt = $pdo->prepare("SELECT * FROM depts WHERE department_id = ? ORDER BY id DESC");
                        $division_stmt->execute([$dept_row['id']]);
                        
                        if ($division_stmt->rowCount() > 0) {
                            $divisions = $division_stmt->fetchAll();
                            ?>
                            <div class="divisions-list">
                                <table class="divisions-table">
                                    <thead>
                                        <tr>
                                            <th >Department Name</th>
                                            <th width="50%">Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($divisions as $index => $division): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($division['name']) ?></td>
                                            <td><?= !empty($division['description']) ? htmlspecialchars($division['description']) : '<span style="color: var(--gray-400);">No description</span>' ?></td>
                                            <td>
                                                <div class="division-actions">
                                                    <button class="btn btn-warning btn-sm" onclick="openEditDivisionModal(<?= $division['id'] ?>)">Edit</button>
                                                    <a href="?delete_division_id=<?= $division['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this division?')">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📄</div>
                                <p>No divisions found for this department</p>
                                <button class="btn btn-success btn-sm" onclick="openAddDivisionModal(<?= $dept_row['id'] ?>)">
                                    Add your first Department
                                </button>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                    $first_panel = false;
                }
            }
            ?>
        </div>
    </div>
</div>

<?php
// Generate modals for each department
$dept_modal_stmt = $pdo->query("SELECT * FROM departments ORDER BY id DESC");
while ($row = $dept_modal_stmt->fetch()) {
    ?>
    <!-- Add Division Modal for <?= htmlspecialchars($row['name']) ?> -->
    <div class="modal fade" id="addDivisionModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Department to <?= htmlspecialchars($row['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="department_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="division_name" placeholder="e.g., Software Development" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="division_description" rows="4" placeholder="Describe the division's focus..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" name="add_division">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal for <?= htmlspecialchars($row['name']) ?> -->
    <div class="modal fade" id="editDepartmentModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($row['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" name="edit_department">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// Generate edit division modals for all divisions
$div_modal_stmt = $pdo->query("SELECT * FROM depts ORDER BY id DESC");
while ($division = $div_modal_stmt->fetch()) {
    ?>
    <!-- Edit Division Modal for <?= htmlspecialchars($division['name']) ?> -->
    <div class="modal fade" id="editDivisionModal<?= $division['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $division['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($division['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($division['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" name="edit_division">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
?>

<script>
function showDepartment(deptId, element) {
    // Hide all departments
    document.querySelectorAll('.department-divisions').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show selected department
    document.getElementById('dept-' + deptId).style.display = 'block';
    
    // Update active state in departments list
    document.querySelectorAll('.department-item').forEach(el => {
        el.classList.remove('active');
    });
    element.classList.add('active');
}

function showDivisionDetail(deptId, divisionId, element) {
    // Remove active class from all rows in this department
    document.querySelectorAll('#dept-' + deptId + ' .divisions-table tr').forEach(el => {
        el.classList.remove('active');
    });
    
    // Add active class to clicked row
    element.classList.add('active');
    
    // Hide all division details in this department
    document.querySelectorAll('#dept-' + deptId + ' .division-detail').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show selected division detail
    document.getElementById('division-detail-' + divisionId).style.display = 'block';
}

function hideDivisionDetail(deptId) {
    // Hide all division details in this department
    document.querySelectorAll('#dept-' + deptId + ' .division-detail').forEach(el => {
        el.style.display = 'none';
    });
    
    // Remove active class from all rows
    document.querySelectorAll('#dept-' + deptId + ' .divisions-table tr').forEach(el => {
        el.classList.remove('active');
    });
}

function openEditDepartmentModal(deptId) {
    var modal = new bootstrap.Modal(document.getElementById('editDepartmentModal' + deptId));
    modal.show();
}

function openAddDivisionModal(deptId) {
    var modal = new bootstrap.Modal(document.getElementById('addDivisionModal' + deptId));
    modal.show();
}

function openEditDivisionModal(divisionId) {
    var modal = new bootstrap.Modal(document.getElementById('editDivisionModal' + divisionId));
    modal.show();
}
</script>
</body>
</html>