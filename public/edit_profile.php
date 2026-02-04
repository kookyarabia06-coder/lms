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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname    = trim($_POST['fname']);
    $lname    = trim($_POST['lname']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users
                SET fname=?, lname=?, email=?, password=?
                WHERE id=?";
        $params = [$fname, $lname, $email, $hash, $userId];
    } else {
        $sql = "UPDATE users
                SET fname=?, lname=?, email=?
                WHERE id=?";
        $params = [$fname, $lname, $email, $userId];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // CRITICAL: UPDATE THE SESSION DATA
    $_SESSION['user']['fname'] = $fname;
    $_SESSION['user']['lname'] = $lname;
    $_SESSION['user']['email'] = $email;
    
    // Optional: Fetch fresh data to ensure everything is updated
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update entire session user array
    $_SESSION['user'] = $updatedUser;

    // Add success message to session
    $_SESSION['success_message'] = "Profile updated successfully!";

    header('Location: profile.php'); 
    exit;
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
</body>
</html>