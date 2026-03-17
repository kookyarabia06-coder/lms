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


// add department
if (isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

//add division
if (isset($_POST['add_division'])) {
    $department_id = $_POST['department_id'];
    $division_name = $_POST['Depts_name'];

    $stmt = $pdo->prepare("INSERT INTO depts (Depts_name, department_id) VALUES (?, ?)");
    $stmt->execute([$Depts_name, $department_id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}


// delete department
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

//edit department
if (isset($_POST['edit_department'])) {
    $id = $_POST['id'];
    $name = $_POST['name']; 
    

    $stmt = $pdo->prepare("UPDATE depts SET name = ?, WHERE id = ?");
    $stmt->execute([$name, $id]);

    header('Location: ' . BASE_URL . '/admin/departments_crud.php');
    exit;
}

?>

<html>
<head>
    <title>Departments Management</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</head>
<body>


   
        Departments Management
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">Add Department</button>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                   
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT * FROM departments");
                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "<td>
                            <button class='btn btn-sm btn-warning' data-bs-toggle='modal' data-bs-target='#editDepartmentModal{$row['id']}'>Edit</button>
                            <a href='?delete_id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                          </td>";
                    echo "</tr>";

                    // Edit Modal for each department
                    echo "
                    <div class='modal fade' id='editDepartmentModal{$row['id']}' tabindex='-1' aria-labelledby='editDepartmentModalLabel{$row['id']}' aria-hidden='true'>
                      <div class='modal-dialog'>
                        <div class='modal-content'>
                          <form method='POST'>
                            <div class='modal-header'>
                              <h5 class='modal-title' id='editDepartmentModalLabel{$row['id']}'>Edit Department</h5>
                              <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body'>
                              <input type='hidden' name='id' value='{$row['id']}'>
                              <div class='mb-3'>
                                <label for='name' class='form-label'>Name</label>
                                <input type='text' class='form-control' name='name' value='" . htmlspecialchars($row['name']) . "' required>
                              </div>
                              <div class='mb-3'>
                              </div>
                            </div>
                            <div class='modal-footer'>
                              <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                              <button type='submit' class='btn btn-primary' name='edit_department'>Add Division</button>
                                  <button type='submit' class='btn btn-primary' name='edit_department'>Save Changes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    ";

                    //add divion modal for each department
                    echo "                    <div class='modal fade' id='addDivisionModal{$row['id']}' tabindex='-1' aria-labelledby='addDivisionModalLabel{$row['id']}' aria-hidden='true'>
                      <div class='modal-dialog'>
                        <div class='modal-content'>
                          <form method='POST'>
                            <div class='modal-header'>
                              <h5 class='modal-title' id='addDivisionModalLabel{$row['id']}'>Add department to " . htmlspecialchars($row['name']) . "</h5>
                              <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body'>
                              <input type='hidden' name='department_id' value='{$row['id']}'>
                              <div class='mb-3'>
                                <label for='name' class='form-label'>Department Name</label>
                                <input type='text' class='form-control' name='division_name' required>
                              </div>
                            </div>
                            <div class='modal-footer'>
                              <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                              <button type='submit' class='btn btn-primary' name='add_division'>Add Division</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    ";


                }
                ?>
            </tbody>
        </table>
    </div>
    <script>
console.log("Department modals initialized.");


    </script>
    </html>