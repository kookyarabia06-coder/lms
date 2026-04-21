<?php
// update_role_display.php
session_start();
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$displayType = $input['display_type'] ?? null;

// Validate input
if (!$userId || !$displayType) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Check if user ID matches session
if ($userId != $_SESSION['user']['id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate display type
if (!in_array($displayType, ['role', 'alt'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid display type']);
    exit;
}

// Store display preference in session
$_SESSION['role_display_mode'] = $displayType;

echo json_encode(['success' => true, 'message' => 'Display mode updated']);
?>