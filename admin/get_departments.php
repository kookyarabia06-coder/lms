<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Only admin can access
if (!is_admin() && !is_superadmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$division_id = isset($_GET['division_id']) ? (int)$_GET['division_id'] : 0;

if ($division_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM depts WHERE department_id = ? ORDER BY name ASC");
$stmt->execute([$division_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($departments);