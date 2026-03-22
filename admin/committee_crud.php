<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';

require_login();
if (!is_admin() && !is_superadmin()) {
    echo 'Admin only';
    exit;
}

// add committee
if (isset($_POST['add_committee'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO committees (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// delete committee
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    $stmt = $pdo->prepare("DELETE FROM committees WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . BASE_URL . '/admin/committee_crud.php');
    exit;
}

// edit committee
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

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Committee Management</title>
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
</head>

<body class="bg-light">
<div class="lms-sidebar-container">
  <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
<div class="container py-4">
  <h4>Committee Management</h4>
  <p>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCommitteeModal">Add Committee</button>
  </p>

  <div class="card shadow-sm p-3">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Name</th>
        <th>Description</th>
        <th style="width:150px;" class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $stmt = $pdo->query("SELECT * FROM committees ORDER BY created_at DESC");
      if ($stmt->rowCount() > 0) {
          while ($row = $stmt->fetch()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['name']) . "</td>";
              echo "<td>" . htmlspecialchars($row['description'] ?? '') . "</td>";
              echo "<td class='text-nowrap text-center'>";
              echo "  <button class='btn btn-sm btn-warning me-1' data-bs-toggle='modal' data-bs-target='#editCommitteeModal{$row['id']}'>Edit</button>";
              echo "  <a href='?delete_id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
              echo "</td>";
              echo "</tr>";
          }
      } else {
          echo "<tr><td colspan='3' class='text-center text-muted'>No committees found. Add your first committee.</td></tr>";
      }
      ?>
    </tbody>
  </table>
  </div>

  <?php
  // Output modals outside the table
  $stmt = $pdo->query("SELECT * FROM committees ORDER BY created_at DESC");
  if ($stmt->rowCount() > 0) {
      while ($row = $stmt->fetch()) {
          // Edit Modal for each committee
          echo "
          <div class='modal fade' id='editCommitteeModal{$row['id']}' tabindex='-1' aria-labelledby='editCommitteeModalLabel{$row['id']}' aria-hidden='true'>
            <div class='modal-dialog'>
              <div class='modal-content'>
                <form method='POST'>
                  <div class='modal-header'>
                    <h5 class='modal-title' id='editCommitteeModalLabel{$row['id']}'>Edit Committee</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                  </div>
                  <div class='modal-body'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <div class='mb-3'>
                      <label for='name' class='form-label'>Name</label>
                      <input type='text' class='form-control' name='name' value='" . htmlspecialchars($row['name']) . "' required>
                    </div>
                    <div class='mb-3'>
                      <label for='description' class='form-label'>Description</label>
                      <textarea class='form-control' name='description' rows='3'>" . htmlspecialchars($row['description'] ?? '') . "</textarea>
                    </div>
                  </div>
                  <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    <button type='submit' class='btn btn-primary' name='edit_committee'>Save Changes</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          ";
      }
  }
  ?>
</div>

<!-- Add Committee Modal -->
<div class="modal fade" id="addCommitteeModal" tabindex="-1" aria-labelledby="addCommitteeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="addCommitteeModalLabel">Add New Committee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="name" class="form-label">Committee Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="add_committee">Add Committee</button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>