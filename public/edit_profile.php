<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();
$userId = $_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Fetch all divisions from departments table
$divisionStmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$divisions = $divisionStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all committees
$committeeStmt = $pdo->query("SELECT id, name FROM committees ORDER BY name ASC");
$committees = $committeeStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current assignments
$userDivision = null;
$userDepartment = null;
$userCommittees = [];

if ($user['role'] === 'user') {
    // Get user's department and its division (single selection)
    $stmt = $pdo->prepare("
        SELECT d.id as dept_id, d.name as dept_name, d.department_id as division_id
        FROM user_departments ud
        JOIN depts d ON ud.dept_id = d.id
        WHERE ud.user_id = ? AND ud.dept_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $userDept = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userDept) {
        $userDepartment = $userDept['dept_id'];
        $userDivision = $userDept['division_id'];
    }
} else {
    // Get user's committees
    $stmt = $pdo->prepare("
        SELECT committee_id 
        FROM user_departments 
        WHERE user_id = ? AND committee_id IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $userCommittees = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname    = trim($_POST['fname']);
    $lname    = trim($_POST['lname']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    // Handle department selection for students (single selection)
    $selectedDept = isset($_POST['department']) && !empty($_POST['department']) ? (int)$_POST['department'] : null;

    // Handle committees selection for proponents/admins
    $selectedCommittees = isset($_POST['committees']) ? $_POST['committees'] : [];
    if (!is_array($selectedCommittees)) {
        $selectedCommittees = [$selectedCommittees];
    }
    $selectedCommittees = array_filter($selectedCommittees, function($value) {
        return !empty($value) && is_numeric($value);
    });
    $selectedCommittees = array_values($selectedCommittees);

    try {
        $pdo->beginTransaction();

        // Update user basic info
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET fname=?, lname=?, email=?, password=? WHERE id=?";
            $params = [$fname, $lname, $email, $hash, $userId];
        } else {
            $sql = "UPDATE users SET fname=?, lname=?, email=? WHERE id=?";
            $params = [$fname, $lname, $email, $userId];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Delete all existing assignments
        $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?")->execute([$userId]);

        // Insert new assignments based on role
        if ($user['role'] === 'user') {
            // Insert single department for student
            if ($selectedDept) {
                $checkStmt = $pdo->prepare("SELECT id FROM depts WHERE id = ?");
                $checkStmt->execute([$selectedDept]);
                if ($checkStmt->fetch()) {
                    $deptStmt = $pdo->prepare("INSERT INTO user_departments (user_id, dept_id) VALUES (?, ?)");
                    $deptStmt->execute([$userId, $selectedDept]);
                }
            }
        } else {
            // Insert committees for proponents/admins
            if (!empty($selectedCommittees)) {
                $placeholders = implode(',', array_fill(0, count($selectedCommittees), '?'));
                $checkStmt = $pdo->prepare("SELECT id FROM committees WHERE id IN ($placeholders)");
                $checkStmt->execute($selectedCommittees);
                $validCommIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($validCommIds)) {
                    $commStmt = $pdo->prepare("INSERT INTO user_departments (user_id, committee_id) VALUES (?, ?)");
                    foreach ($validCommIds as $commId) {
                        $commStmt->execute([$userId, $commId]);
                    }
                }
            }
        }

        $pdo->commit();

        // Update session data
        $_SESSION['user']['fname'] = $fname;
        $_SESSION['user']['lname'] = $lname;
        $_SESSION['user']['email'] = $email;
        
        // Fetch fresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user'] = $updatedUser;

        $_SESSION['success_message'] = "Profile updated successfully!";
        header('Location: profile.php'); 
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Failed to update profile: " . $e->getMessage();
        error_log("Edit profile error: " . $e->getMessage());
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?= BASE_URL ?>/assets/css/editprofile.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/profile.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/uploads/images/armmc-logo.png?v=1">
    <style>
        .cascading-dropdown {
            transition: all 0.3s ease;
        }
        .assignment-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        .assignment-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #495057;
        }
        .committee-container {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            background: white;
        }
        .committee-item {
            margin-bottom: 8px;
        }
        .search-box {
            margin-bottom: 15px;
        }
        .current-selection {
            margin-top: 15px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }
        .current-selection i {
            color: #fd7e14;
        }
    </style>
</head>
<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="profile-wrapper">
        <div class="profile-header">
            <h1>Edit Profile</h1>
            <p>Update your personal information below</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="profile-card">
            <div class="mb-3">
                <label for="fname" class="form-label">First Name</label>
                <input type="text" name="fname" id="fname" class="form-control" required
                       value="<?= htmlspecialchars($user['fname']) ?>">
            </div>

            <div class="mb-3">
                <label for="lname" class="form-label">Last Name</label>
                <input type="text" name="lname" id="lname" class="form-control" required
                       value="<?= htmlspecialchars($user['lname']) ?>">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required
                       value="<?= htmlspecialchars($user['email']) ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control">
                <div class="password-note">Leave blank if unchanged</div>
            </div>

            <?php if ($user['role'] === 'user'): ?>
                <!-- Student Section: Division and Department Selection (Single Selection) -->
                <div class="assignment-section">
                    <h5 class="assignment-title">
                        <i class="fas fa-building me-2"></i>Division and Department Assignment
                    </h5>
                    
                    <!-- Division Dropdown -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Division</label>
                        <select class="form-control" id="divisionSelect" name="division_id">
                            <option value="">-- Choose a Division --</option>
                            <?php foreach($divisions as $division): ?>
                                <option value="<?= $division['id'] ?>" <?= ($userDivision == $division['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($division['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select a division to see its departments</small>
                    </div>
                    
                    <!-- Department Dropdown -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Department</label>
                        <select class="form-control" id="departmentSelect" name="department" <?= $userDivision ? '' : 'disabled' ?>>
                            <option value="">-- Select a Department --</option>
                        </select>
                        <small class="text-muted">Choose the department for your profile</small>
                    </div>
                    
                    <!-- Display current selection -->
                    <?php if ($userDepartment): ?>
                        <div class="current-selection">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Current Assignment:</strong> 
                            <?php
                            $deptStmt = $pdo->prepare("SELECT name FROM depts WHERE id = ?");
                            $deptStmt->execute([$userDepartment]);
                            $deptName = $deptStmt->fetchColumn();
                            echo htmlspecialchars($deptName);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Proponent/Admin Section: Committee Selection -->
                <div class="assignment-section">
                    <h5 class="assignment-title">
                        <i class="fas fa-users me-2"></i>Committee Assignment
                    </h5>
                    
                    <!-- Search Bar -->
                    <div class="search-box">
                        <input type="text" id="committeeSearch" class="form-control" placeholder="Search committees...">
                    </div>
                    
                    <div class="committee-container" id="committeeContainer">
                        <?php if (empty($committees)): ?>
                            <p class="text-muted text-center">No committees available.</p>
                        <?php else: ?>
                            <?php foreach($committees as $committee): ?>
                            <div class="committee-item" data-committee-name="<?= strtolower(htmlspecialchars($committee['name'])) ?>">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="committees[]" 
                                           value="<?= $committee['id'] ?>" id="committee_<?= $committee['id'] ?>"
                                           <?= in_array($committee['id'], $userCommittees) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="committee_<?= $committee['id'] ?>">
                                        <?= htmlspecialchars($committee['name']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Select all committees you belong to</small>
                </div>
            <?php endif; ?>

            <div class="button-container">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Division change handler
            const divisionSelect = document.getElementById('divisionSelect');
            const departmentSelect = document.getElementById('departmentSelect');
            const currentDepartmentId = <?= json_encode($userDepartment) ?>;
            
            if (divisionSelect && departmentSelect) {
                // Function to load departments
                function loadDepartments(divisionId, selectedDeptId = null) {
                    if (divisionId) {
                        departmentSelect.disabled = false;
                        departmentSelect.innerHTML = '<option value="">Loading...</option>';
                        
                        fetch(`get_departments.php?division_id=${divisionId}`)
                            .then(response => response.json())
                            .then(data => {
                                departmentSelect.innerHTML = '<option value="">-- Select a Department --</option>';
                                data.forEach(dept => {
                                    const selected = (selectedDeptId == dept.id) ? 'selected' : '';
                                    departmentSelect.innerHTML += `<option value="${dept.id}" ${selected}>${dept.name}</option>`;
                                });
                                
                                // If no department is selected and there's a current selection, set it
                                if (selectedDeptId && !departmentSelect.value) {
                                    departmentSelect.value = selectedDeptId;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                departmentSelect.innerHTML = '<option value="">Error loading departments</option>';
                            });
                    } else {
                        departmentSelect.disabled = true;
                        departmentSelect.innerHTML = '<option value="">-- First select a division --</option>';
                    }
                }
                
                // Load departments on page load if division is selected
                if (divisionSelect.value) {
                    loadDepartments(divisionSelect.value, currentDepartmentId);
                }
                
                // Load departments when division changes
                divisionSelect.addEventListener('change', function() {
                    loadDepartments(this.value);
                });
            }

            // Committee search
            const committeeSearch = document.getElementById('committeeSearch');
            if (committeeSearch) {
                const committeeItems = document.querySelectorAll('.committee-item');
                
                committeeSearch.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    committeeItems.forEach(item => {
                        const commName = item.getAttribute('data-committee-name');
                        item.style.display = (searchTerm === '' || commName.includes(searchTerm)) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>