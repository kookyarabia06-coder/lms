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
    <title>Departments Management</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Main Layout - Fixed for sidebar */
        .lms-sidebar-container {
            float: left;
            width: 250px;
        }

        .container.mt-4 {
            margin-left: 250px; /* Match sidebar width */
            padding: 2rem;
            max-width: calc(100% - 250px);
        }

        /* Clear float */
        .container.mt-4::after {
            content: "";
            display: table;
            clear: both;
        }

        /* Page Title */
        .container.mt-4 h2 {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: #000000;
            margin-bottom: 1.5rem;
        }

        /* Add Main Department Button */
        .btn-primary.mb-3 {
            background-color: #0d6efd;
            border: none;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            transition: background-color 0.15s ease;
            margin-bottom: 1rem !important;
            border: 1px solid #0d6efd;
        }

        .btn-primary.mb-3:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        /* Table Styling */
        .table-bordered {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #dee2e6;
        }

        /* Resized table columns */
        .table-bordered thead th:first-child {
            width: 50%; /* Main Department Name column */
        }

        .table-bordered thead th:nth-child(2) {
            width: 30%; /* Description column */
        }

        .table-bordered thead th:last-child {
            width: 20%; /* Actions column */
        }

        .table-bordered thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table-bordered thead th {
            font-size: 1rem;
            font-weight: 700;
            color: #000000;
            padding: 0.75rem;
            text-align: left;
            border-right: 1px solid #dee2e6;
            vertical-align: bottom;
        }

        .table-bordered thead th:last-child {
            border-right: none;
        }

        .table-bordered tbody tr {
            border-bottom: 1px solid #dee2e6;
        }

        .table-bordered tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .table-bordered tbody td {
            padding: 0.75rem;
            color: #212529;
            font-size: 1rem;
            border-right: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .table-bordered tbody td:last-child {
            border-right: none;
        }

        /* Main Department Name */
        .table-bordered tbody td:first-child {
            font-weight: 500;
            color: #212529;
        }

        /* Description */
        .table-bordered tbody td:nth-child(2) {
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* Action Buttons */
        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: 1px solid #17a2b8;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-block;
            line-height: 1.5;
            margin-right: 0.25rem;
        }

        .btn-view:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            border-radius: 3px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.25rem;
            line-height: 1.5;
        }

        .btn-sm:last-child {
            margin-right: 0;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000000;
            border-color: #ffc107;
        }

        .btn-warning:hover {
            background-color: #ffca2c;
            border-color: #ffc720;
            color: #000000;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }

        /* Divisions table inside modal */
        .division-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .division-table thead th {
            background-color: #f8f9fa;
            padding: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .division-table tbody td {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .division-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .division-actions {
            white-space: nowrap;
        }

        .btn-sm-division {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
            border-radius: 3px;
            margin-right: 0.25rem;
        }

        .btn-add-division {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
            margin-bottom: 1rem;
        }

        .btn-add-division:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .dept-info {
            background-color: #e9ecef;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .dept-info h6 {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        .dept-info p {
            margin-bottom: 0;
            color: #6c757d;
        }

        /* Modal Styling */
        .modal-content {
            border: 1px solid rgba(0,0,0,.2);
            border-radius: 6px;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1rem;
        }

        .modal-header .modal-title {
            font-size: 1.25rem;
            font-weight: 500;
            color: #000000;
        }

        .modal-header .btn-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #000000;
            opacity: 0.5;
        }

        .modal-header .btn-close:hover {
            opacity: 0.75;
        }

        .modal-body {
            padding: 1rem;
            background-color: #ffffff;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-body .form-label {
            font-size: 1rem;
            font-weight: 500;
            color: #212529;
            margin-bottom: 0.5rem;
            display: block;
        }

        .modal-body .form-control {
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            font-family: inherit;
        }

        .modal-body .form-control:focus {
            outline: none;
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
        }

        .modal-body textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .modal-footer .btn {
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background-color 0.15s ease;
        }

        .modal-footer .btn-secondary {
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
        }

        .modal-footer .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }

        .modal-footer .btn-primary {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .modal-footer .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        /* Empty State */
        .table-bordered tbody tr td[colspan] {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: normal;
        }

        .empty-divisions {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .lms-sidebar-container {
                float: none;
                width: 100%;
            }
           
            .container.mt-4 {
                margin-left: 0;
                padding: 1rem;
                max-width: 100%;
            }
           
            .table-bordered {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
           
            .table-bordered thead th:first-child,
            .table-bordered thead th:last-child {
                width: auto;
            }
           
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.875rem;
            }
           
            .container.mt-4 h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="container mt-4">
    <h2>DEPARTMENTS / Divisions Management</h2>
    
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">Add Department</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>DEPARTMENT</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get all departments from departments table
            $stmt = $pdo->query("SELECT * FROM departments ORDER BY id DESC");
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch()) {
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                        <td>
                            <button class='btn-view' data-bs-toggle='modal' data-bs-target='#viewDivisionsModal<?= $row['id'] ?>'>
                                View Divisions
                            </button>
                            <button class='btn-edit btn-sm' data-bs-toggle='modal' data-bs-target='#editDepartmentModal<?= $row['id'] ?>'>
                                Edit
                            </button>
                            <a href='?delete_id=<?= $row['id'] ?>' class='btn btn-sm btn-danger' onclick='return confirm("Are you sure you want to delete this department and all its divisions?")'>Delete</a>
                        </td>
                    </tr>

                    <!-- View Divisions Modal -->
                    <div class='modal fade' id='viewDivisionsModal<?= $row['id'] ?>' tabindex='-1' aria-labelledby='viewDivisionsModalLabel<?= $row['id'] ?>' aria-hidden='true'>
                        <div class='modal-dialog modal-lg'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='viewDivisionsModalLabel<?= $row['id'] ?>'>
                                        Divisions under Department: <?= htmlspecialchars($row['name']) ?>
                                    </h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                </div>
                                <div class='modal-body'>
                                    <!-- Main Department Info -->
                                    <div class='dept-info'>
                                        <h6>Department Description:</h6>
                                        <p><?= htmlspecialchars($row['description'] ?? 'No description provided') ?></p>
                                    </div>
                                    
                                    <!-- Add Division Button -->
                                    <button class='btn btn-add-division btn-sm' data-bs-toggle='modal' data-bs-target='#addDivisionModal<?= $row['id'] ?>'>
                                        + Add Division 
                                    </button>
                                    
                                    <!-- Divisions Table -->
                                    <table class='division-table'>
                                        <thead>
                                            <tr>
                                                <th>Division Name</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get all divisions from depts table where department_id matches
                                            $division_stmt = $pdo->prepare("SELECT * FROM depts WHERE department_id = ? ORDER BY id DESC");
                                            $division_stmt->execute([$row['id']]);
                                            if ($division_stmt->rowCount() > 0) {
                                                while ($division = $division_stmt->fetch()) {
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($division['name']) ?></td>
                                                        <td><?= htmlspecialchars($division['description'] ?? 'No description') ?></td>
                                                        <td class='division-actions'>
                                                            <button class='btn-edit btn-sm-division' data-bs-toggle='modal' data-bs-target='#editDivisionModal<?= $division['id'] ?>'>Edit</button>
                                                            <a href='?delete_division_id=<?= $division['id'] ?>' class='btn-delete btn-sm-division' onclick='return confirm("Are you sure?")'>Delete</a>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit Division Modal -->
                                                    <div class='modal fade' id='editDivisionModal<?= $division['id'] ?>' tabindex='-1' aria-hidden='true'>
                                                        <div class='modal-dialog'>
                                                            <div class='modal-content'>
                                                                <form method='POST'>
                                                                    <div class='modal-header'>
                                                                        <h5 class='modal-title'>Edit Division</h5>
                                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                                    </div>
                                                                    <div class='modal-body'>
                                                                        <input type='hidden' name='id' value='<?= $division['id'] ?>'>
                                                                        <div class='mb-3'>
                                                                            <label class='form-label'>Division Name</label>
                                                                            <input type='text' class='form-control' name='name' value='<?= htmlspecialchars($division['name']) ?>' required>
                                                                        </div>
                                                                        <div class='mb-3'>
                                                                            <label class='form-label'>Description</label>
                                                                            <textarea class='form-control' name='description' rows='3'><?= htmlspecialchars($division['description'] ?? '') ?></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class='modal-footer'>
                                                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                                        <button type='submit' class='btn btn-primary' name='edit_division'>Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                                echo "<tr><td colspan='3' class='empty-divisions'>No divisions found under this department.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class='modal-footer'>
                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Division Modal -->
                    <div class='modal fade' id='addDivisionModal<?= $row['id'] ?>' tabindex='-1' aria-hidden='true'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <form method='POST'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>Add Division to <?= htmlspecialchars($row['name']) ?></h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <input type='hidden' name='department_id' value='<?= $row['id'] ?>'>
                                        <div class='mb-3'>
                                            <label class='form-label'>Division Name</label>
                                            <input type='text' class='form-control' name='division_name' required>
                                        </div>
                                        <div class='mb-3'>
                                            <label class='form-label'>Description</label>
                                            <textarea class='form-control' name='division_description' rows='3'></textarea>
                                        </div>
                                    </div>
                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                        <button type='submit' class='btn btn-primary' name='add_division'>Add Division</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Department Modal -->
                    <div class='modal fade' id='editDepartmentModal<?= $row['id'] ?>' tabindex='-1' aria-hidden='true'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <form method='POST'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>Edit Department</h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <input type='hidden' name='id' value='<?= $row['id'] ?>'>
                                        <div class='mb-3'>
                                            <label class='form-label'>Department Name</label>
                                            <input type='text' class='form-control' name='name' value='<?= htmlspecialchars($row['name']) ?>' required>
                                        </div>
                                        <div class='mb-3'>
                                            <label class='form-label'>Description</label>
                                            <textarea class='form-control' name='description' rows='3'><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                        <button type='submit' class='btn btn-primary' name='edit_department'>Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<tr><td colspan='3' class='text-center'>No departments found. Add your first department.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Add Department Modal -->
<div class='modal fade' id='addDepartmentModal' tabindex='-1' aria-labelledby='addDepartmentModalLabel' aria-hidden='true'>
    <div class='modal-dialog'>
        <div class='modal-content'>
            <form method='POST'>
                <div class='modal-header'>
                    <h5 class='modal-title' id='addDepartmentModalLabel'>Add New Department</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body'>
                    <div class='mb-3'>
                        <label for='name' class='form-label'>Department Name</label>
                        <input type='text' class='form-control' name='name' required>
                    </div>
                    <div class='mb-3'>
                        <label for='description' class='form-label'>Description</label>
                        <textarea class='form-control' name='description' rows='3'></textarea>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    <button type='submit' class='btn btn-primary' name='add_department'>Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
console.log("Department management initialized with correct hierarchy");
</script>
</body>
</html>