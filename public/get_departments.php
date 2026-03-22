<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

// This endpoint is public for registration
header('Content-Type: application/json');

// Get division ID from query string
$divisionId = isset($_GET['division_id']) ? (int)$_GET['division_id'] : 0;

if (!$divisionId) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch departments under this division from depts table
    $stmt = $pdo->prepare("SELECT id, name FROM depts WHERE department_id = ? ORDER BY name ASC");
    $stmt->execute([$divisionId]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departments);
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load departments']);
}
?>