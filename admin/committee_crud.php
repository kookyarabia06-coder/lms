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

// Add committee
if (isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO committees (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// Add department to committee
if (isset($_POST['add_department_to_committee'])) {
    $committee_id = $_POST['committee_id'];
    $department_name = $_POST['department_name'];
    $department_description = $_POST['department_description'];

    $stmt = $pdo->prepare("INSERT INTO committees (name, description) VALUES (?, ?, ?)");
    $stmt->execute([$id, $desciption, $name]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// Edit department
if (isset($_POST['edit_department'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("UPDATE committees SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $id]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// Delete department
if (isset($_GET['delete_dept_id'])) {
    $id = $_GET['delete_dept_id'];

    $stmt = $pdo->prepare("DELETE FROM committees WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// Delete committee
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // First delete all departments under this committee
    $stmt = $pdo->prepare("DELETE FROM committees WHERE id = ?");
    $stmt->execute([$id]);
    
    // Then delete the committee
    $stmt = $pdo->prepare("DELETE FROM commitittees WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// Edit committee
if (isset($_POST['edit_committee'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("UPDATE committees SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $id]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

?>

<html>
<head>
    <title>Committee Management</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <style>
        /* departments.css - Matching News Table Style with Resized Table */

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

        /* Add Committee Button */
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

        /* Table Styling - Matching News Table with Resized Columns */
        .table-bordered {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #dee2e6;
        }

        /* Resized table columns */
        .table-bordered thead th:first-child {
            width: 50%; /* Committee Name column */
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

        /* Committee Name */
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

        .btn-edit {
            background-color: #ffc107;
            color: #000;
            border: 1px solid #ffc107;
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

        .btn-edit:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-block;
            line-height: 1.5;
        }

        .btn-delete:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        /* Department table inside modal */
        .dept-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .dept-table thead th {
            background-color: #f8f9fa;
            padding: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .dept-table tbody td {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .dept-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .dept-actions {
            white-space: nowrap;
        }

        .btn-sm-dept {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
            border-radius: 3px;
            margin-right: 0.25rem;
        }

        .btn-add-dept {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
            margin-bottom: 1rem;
        }

        .btn-add-dept:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .committee-info {
            background-color: #e9ecef;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .committee-info h6 {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        .committee-info p {
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

        .empty-depts {
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
            
            .btn-view, .btn-edit, .btn-delete {
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

<!-- Sidebar placeholder -->
<div class="lms-sidebar-container">
    <?php if (file_exists(__DIR__ . '/../inc/sidebar.php')): ?>
        <?php include_once __DIR__ . '/../inc/sidebar.php'; ?>
    <?php endif; ?>
</div>

<div class="container mt-4">
    <h2>Committee Management</h2>
    <!-- Add Committee Button if user is admin or superadmin this will show -->
     <?php if($u && (is_admin() || is_superadmin())): ?>
    
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addCommitteeModal">Add Committee</button>
         <?php endif; ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Committee Name</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM committees ORDER BY id DESC");
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch()) {
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                        <td>
                            <button class='btn-view' data-bs-toggle='modal' data-bs-target='#viewDepartmentsModal<?= $row['id'] ?>'>
                                View Depts
                            </button>
                            <button class='btn-edit' data-bs-toggle='modal' data-bs-target='#editCommitteeModal<?= $row['id'] ?>'>
                                Edit
                            </button>
                            <a href='?delete_committee_id=<?= $row['id'] ?>' class='btn-delete' onclick='return confirm("Are you sure you want to delete this committee and all its departments?")'>Delete</a>
                        </td>
                    </tr>
                    
                    <!-- View Departments Modal for each committee -->
                    <div class='modal fade' id='viewDepartmentsModal<?= $row['id'] ?>' tabindex='-1' aria-labelledby='viewDepartmentsModalLabel<?= $row['id'] ?>' aria-hidden='true'>
                        <div class='modal-dialog modal-lg'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='viewDepartmentsModalLabel<?= $row['id'] ?>'>
                                        Departments under: <?= htmlspecialchars($row['name']) ?>
                                    </h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                </div>
                                <div class='modal-body'>
                                    <!-- Committee Info -->
                                    <div class='committee-info'>
                                        <h6>Committee Description:</h6>
                                        <p><?= htmlspecialchars($row['description'] ?? 'No description provided') ?></p>
                                    </div>
                                    
                                    <!-- Add Department Button - FIXED: Removed data-bs-dismiss -->
                                    <button class='btn btn-add-dept btn-sm' data-bs-toggle='modal' data-bs-target='#addDepartmentModal<?= $row['id'] ?>'>
                                        + Add New Department
                                    </button>
                                    
                                    <!-- Departments Table -->
                                    <table class='dept-table'>
                                        <thead>
                                            <tr>
                                                <th>Committee Name</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            //tabler
                                            $dept_stmt = $pdo->prepare("SELECT * FROM committees WHERE name = ? ORDER BY id DESC");
                                            $dept_stmt->execute([$row['id']]);
                                            if ($dept_stmt->rowCount() > 0) {
                                                while ($dept = $dept_stmt->fetch()) {
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dept['name']) ?></td>
                                                        <td><?= htmlspecialchars($dept['description'] ?? 'No description') ?></td>
                                                        <td class='dept-actions'>
                                                            <button class='btn-edit btn-sm-dept' data-bs-toggle='modal' data-bs-target='#editDepartmentModal<?= $dept['id'] ?>'>Edit</button>
                                                            <a href='?delete_dept_id=<?= $dept['id'] ?>' class='btn-delete btn-sm-dept' onclick='return confirm("Are you sure?")'>Delete</a>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- Edit Department Modal -->
                                                    <div class='modal fade' id='editDepartmentModal<?= $dept['id'] ?>' tabindex='-1' aria-hidden='true'>
                                                        <div class='modal-dialog'>
                                                            <div class='modal-content'>
                                                                <form method='POST'>
                                                                    <div class='modal-header'>
                                                                        <h5 class='modal-title'>Edit Department</h5>
                                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                                    </div>
                                                                    <div class='modal-body'>
                                                                        <input type='hidden' name='id' value='<?= $dept['id'] ?>'>
                                                                        <div class='mb-3'>
                                                                            <label class='form-label'>Department Name</label>
                                                                            <input type='text' class='form-control' name='name' value='<?= htmlspecialchars($dept['name']) ?>' required>
                                                                        </div>
                                                                        <div class='mb-3'>
                                                                            <label class='form-label'>Description</label>
                                                                            <textarea class='form-control' name='description' rows='3'><?= htmlspecialchars($dept['description'] ?? '') ?></textarea>
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
                                                echo "<tr><td colspan='3' class='empty-depts'>No committee pownd.</td></tr>";
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
                    
                    <!-- Add Department Modal for this committee -->
                    <div class='modal fade' id='addDepartmentModal<?= $row['id'] ?>' tabindex='-1' aria-hidden='true'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <form method='POST'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>addcommitee <?= htmlspecialchars($row['name']) ?></h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <input type='hidden' name='committee_id' value='<?= $row['id'] ?>'>
                                        <div class='mb-3'>
                                            <label class='form-label'>committee Name</label>
                                            <input type='text' class='form-control' name='department_name' required>
                                        </div>
                                        <div class='mb-3'>
                                            <label class='form-label'>Description</label>
                                            <textarea class='form-control' name='department_description' rows='3'></textarea>
                                        </div>
                                    </div>
                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                        <button type='submit' class='btn btn-primary' name='add_department_to_committee'>Add Department</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Committee Modal -->
                    <div class='modal fade' id='editCommitteeModal<?= $row['id'] ?>' tabindex='-1' aria-hidden='true'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <form method='POST'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title'>Edit Committee</h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                    </div>
                                    <div class='modal-body'>
                                        <input type='hidden' name='id' value='<?= $row['id'] ?>'>
                                        <div class='mb-3'>
                                            <label class='form-label'>Committee Name</label>
                                            <input type='text' class='form-control' name='name' value='<?= htmlspecialchars($row['name']) ?>' required>
                                        </div>
                                        <div class='mb-3'>
                                            <label class='form-label'>Description</label>
                                            <textarea class='form-control' name='description' rows='3'><?= htmlspecialchars($row['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class='modal-footer'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                        <button type='submit' class='btn btn-primary' name='edit_committee'>Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<tr><td colspan='3' class='text-center'>No committees found. Add your first committee.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Add Committee Modal -->
<div class='modal fade' id='addCommitteeModal' tabindex='-1' aria-labelledby='addCommitteeModalLabel' aria-hidden='true'>
    <div class='modal-dialog'>
        <div class='modal-content'>
            <form method='POST'>
                <div class='modal-header'>
                    <h5 class='modal-title' id='addCommitteeModalLabel'>Add New Committee</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body'>
                    <div class='mb-3'>
                        <label for='name' class='form-label'>Committee Name</label>
                        <input type='text' class='form-control' name='name' required>
                    </div>
                    <div class='mb-3'>
                        <label for='description' class='form-label'>Description</label>
                        <textarea class='form-control' name='description' rows='3'></textarea>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    <button type='submit' class='btn btn-primary' name='add_department'>Add Committee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
console.log("Committee management initialized with department views");
</script>
</body>
</html>