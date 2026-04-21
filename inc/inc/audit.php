<?php
//done na dakshdashdaksdhaskd

function logAudit($pdo, $userId, $username, $userRole, $action, $tableName, $recordId = null, $oldData = null, $newData = null, $description = null) {
try {

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';


$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';


$oldDataJson = $oldData ? json_encode($oldData, JSON_PRETTY_PRINT) : null;
$newDataJson = $newData ? json_encode($newData, JSON_PRETTY_PRINT) : null;

// no descript edi provide
if (!$description) {
$description = generateAuditDescription($action, $tableName, $recordId, $newData);
}

$stmt = $pdo->prepare("
INSERT INTO audit_logs 
(user_id, username, user_role, action, table_name, record_id, old_data, new_data, description, ip_address, user_agent, created_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

return $stmt->execute([
$userId, $username, $userRole, $action, $tableName, $recordId, 
$oldDataJson, $newDataJson, $description, $ipAddress, $userAgent
]);

} catch (Exception $e) {
error_log("Audit log error: " . $e->getMessage());
return false;
}
}

/**
 * readbol
 */
function generateAuditDescription($action, $tableName, $recordId, $data = null) {
$actionText = [
'ADD' => 'added',
'EDIT' => 'edited',
'DELETE' => 'deleted',
'VIEW' => 'viewed',
'ENROLL' => 'enrolled in',
'COMPLETE' => 'completed',
'LOGIN' => 'logged in',
'LOGOUT' => 'logged out'
];

$actionWord = $actionText[$action] ?? strtolower($action);
$tableName = str_replace('_', ' ', $tableName);

if ($recordId) {
return "User {$actionWord} {$tableName} with ID: {$recordId}";
} else {
return "User {$actionWord} {$tableName}";
}
}

/**
 * new = old 
 */
function getChanges($oldData, $newData) {
$changes = [];
foreach ($newData as $key => $value) {
if (!isset($oldData[$key]) || $oldData[$key] != $value) {
$oldValue = $oldData[$key] ?? '[empty]';
$newValue = $value ?? '[empty]';
$changes[$key] = [
'old' => $oldValue,
'new' => $newValue
];
}
}
return $changes;
}

/**
 * changes for displayss
 */
function formatChanges($changes) {
if (empty($changes)) return 'No changes detected';

$html = '<ul style="margin:0; padding-left:20px;">';
foreach ($changes as $field => $change) {
$html .= '<li><strong>' . ucfirst($field) . ':</strong> ';
$html .= '"' . htmlspecialchars($change['old']) . '" → ';
$html .= '"' . htmlspecialchars($change['new']) . '"</li>';
}
$html .= '</ul>';
return $html;
}