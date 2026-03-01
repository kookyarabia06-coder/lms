<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

// Check if user is superadmin
if (!is_superadmin()) {
echo 'Super Admin Only';
exit;
}

// Handle AJAX request for real-time data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_audit_data') {
header('Content-Type: application/json');

try {
// Get latest timestamp from client to only send new data
$last_timestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

// Get course activities with old and new values from audit_log
$sql = "
SELECT 
c.id as course_id,
c.title,
c.created_at,
c.updated_at,
u.id as user_id,
u.username,
u.fname,
u.lname,
u.role,
a.old_data,
a.new_data,
a.changed_fields,
a.action as audit_action,
a.created_at as audit_time,
CASE 
WHEN a.action = 'UPDATE' THEN 'EDITED'
WHEN a.action = 'INSERT' THEN 'ADDED'
WHEN a.action = 'DELETE' THEN 'DELETED'
ELSE 
CASE 
WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
ELSE 'ADDED'
END
END as action_type
FROM courses c
LEFT JOIN users u ON c.proponent_id = u.id
LEFT JOIN audit_log a ON a.table_name = 'courses' AND a.record_id = c.id
WHERE 1=1
";

$params = [];

// If last_timestamp provided, only get newer records
if ($last_timestamp) {
$sql .= " AND COALESCE(a.created_at, c.updated_at, c.created_at) > ?";
$params[] = $last_timestamp;
}

$sql .= " ORDER BY COALESCE(a.created_at, c.updated_at, c.created_at) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no audit_log data, use fallback
if (empty($course_actions) || !isset($course_actions[0]['old_data'])) {
$stmt = $pdo->prepare("
SELECT 
c.id as course_id,
c.title,
c.created_at,
c.updated_at,
u.id as user_id,
u.username,
u.fname,
u.lname,
u.role,
NULL as old_data,
NULL as new_data,
NULL as changed_fields,
NULL as audit_action,
NULL as audit_time,
CASE 
WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
ELSE 'ADDED'
END as action_type
FROM courses c
LEFT JOIN users u ON c.proponent_id = u.id
ORDER BY c.updated_at DESC, c.created_at DESC
");
$stmt->execute();
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Format data for JSON response
$formatted_data = [];
foreach ($course_actions as $course) {
$changes = parseFieldChanges(
$course['old_data'] ?? null, 
$course['new_data'] ?? null,
$course['changed_fields'] ?? null
);

$formatted_data[] = [
'course_id' => $course['course_id'],
'title' => htmlspecialchars($course['title'] ?? 'Untitled'),
'created_at' => date('M d, Y', strtotime($course['created_at'])),
'created_time' => date('h:i A', strtotime($course['created_at'])),
'updated_at' => !empty($course['updated_at']) ? date('M d, Y', strtotime($course['updated_at'])) : null,
'updated_time' => !empty($course['updated_at']) ? date('h:i A', strtotime($course['updated_at'])) : null,
'old_values' => $changes['old'],
'new_values' => $changes['new'],
'action_type' => $course['action_type'],
'username' => $course['username'] ?? null,
'fullname' => trim(($course['fname'] ?? '') . ' ' . ($course['lname'] ?? '')),
'role' => $course['role'] ?? null,
'audit_time' => !empty($course['audit_time']) ? date('h:i A', strtotime($course['audit_time'])) : null,
'timestamp' => strtotime($course['audit_time'] ?? $course['updated_at'] ?? $course['created_at'])
];
}

// Get the latest timestamp for next poll
$latest_timestamp = null;
if (!empty($course_actions)) {
$latest = $course_actions[0];
$latest_timestamp = date('Y-m-d H:i:s', strtotime($latest['audit_time'] ?? $latest['updated_at'] ?? $latest['created_at']));
}

echo json_encode([
'success' => true,
'data' => $formatted_data,
'count' => count($formatted_data),
'latest_timestamp' => $latest_timestamp
]);

} catch (Exception $e) {
echo json_encode([
'success' => false,
'error' => $e->getMessage()
]);
}
exit;
}

// Function to parse old/new values
function parseFieldChanges($old_data, $new_data, $changed_fields) {
$result = [
'old' => [],
'new' => []
];

if ($old_data && $new_data) {
$old = json_decode($old_data, true);
$new = json_decode($new_data, true);
$fields = json_decode($changed_fields, true);

if ($fields && is_array($fields)) {
foreach ($fields as $field) {
if (isset($old[$field]) || isset($new[$field])) {
$result['old'][$field] = $old[$field] ?? 'NULL';
$result['new'][$field] = $new[$field] ?? 'NULL';
}
}
}
}

return $result;
}

// Initial data load for page render
$stmt = $pdo->prepare("
SELECT 
c.id as course_id,
c.title,
c.created_at,
c.updated_at,
u.id as user_id,
u.username,
u.fname,
u.lname,
u.role,
a.old_data,
a.new_data,
a.changed_fields,
a.action as audit_action,
a.created_at as audit_time,
CASE 
WHEN a.action = 'UPDATE' THEN 'EDITED'
WHEN a.action = 'INSERT' THEN 'ADDED'
WHEN a.action = 'DELETE' THEN 'DELETED'
ELSE 
CASE 
WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
ELSE 'ADDED'
END
END as action_type
FROM courses c
LEFT JOIN users u ON c.proponent_id = u.id
LEFT JOIN audit_log a ON a.table_name = 'courses' AND a.record_id = c.id
ORDER BY COALESCE(a.created_at, c.updated_at, c.created_at) DESC
");
$stmt->execute();
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no audit_log data, use fallback
if (empty($course_actions) || !isset($course_actions[0]['old_data'])) {
$stmt = $pdo->prepare("
SELECT 
c.id as course_id,
c.title,
c.created_at,
c.updated_at,
u.id as user_id,
u.username,
u.fname,
u.lname,
u.role,
NULL as old_data,
NULL as new_data,
NULL as changed_fields,
NULL as audit_action,
NULL as audit_time,
CASE 
WHEN c.updated_at IS NOT NULL AND c.updated_at > c.created_at THEN 'EDITED'
ELSE 'ADDED'
END as action_type
FROM courses c
LEFT JOIN users u ON c.proponent_id = u.id
ORDER BY c.updated_at DESC, c.created_at DESC
");
$stmt->execute();
$course_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Activity - LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.action-badge {
padding: 4px 10px;
border-radius: 20px;
font-size: 11px;
font-weight: 600;
display: inline-block;
}

.action-added {
background: #28a73b;
color: white;
}

.action-deleted {
background: #e00000;
color: white;
}

.action-edited {
background: #ffc107;
color: black;
}

table {
table-layout: fixed;
width: 100%;
}

.table th {
background: #343a40;
color: white;
font-size: 14px;
white-space: nowrap;
height: 50px;           
max-height: 60px;       
overflow: hidden;       
}

.table td {
font-size: 12px;
vertical-align: middle;
height: auto;
min-height: 40px;
padding: 8px 5px;
word-wrap: break-word;
}

.stats-card {
background: white;
border-radius: 10px;
padding: 20px;
box-shadow: 0 2px 5px rgba(0,0,0,0.1);
text-align: center;
margin-bottom: 20px;
}

.stats-number {
font-size: 32px;
font-weight: bold;
color: #007bff;
}

.card {
max-height: 700px; 
display: flex;
flex-direction: column;
}

.card-body {
flex: 1;
overflow-y: auto;
min-height: 0; 
padding: 0;
}

.table-responsive {
max-height: 600px; 
overflow-y: auto;
overflow-x: auto;
}

.table thead th {
position: sticky;
top: 0;
z-index: 10;
background: #b8b9bb; 
}

/* Column width allocations */
th:nth-child(1), td:nth-child(1) { width: 5%; }  /* Course ID */
th:nth-child(2), td:nth-child(2) { width: 12%; } /* Course Title */
th:nth-child(3), td:nth-child(3) { width: 8%; }  /* Created At */
th:nth-child(4), td:nth-child(4) { width: 8%; }  /* Last Updated */
th:nth-child(5), td:nth-child(5) { width: 10%; } /* Old Value */
th:nth-child(6), td:nth-child(6) { width: 10%; } /* New Value */
th:nth-child(7), td:nth-child(7) { width: 6%; }  /* Action */
th:nth-child(8), td:nth-child(8) { width: 10%; } /* Done By */
th:nth-child(9), td:nth-child(9) { width: 6%; }  /* Role */

.value-changes {
font-size: 11px;
background: #f8f9fa;
border-radius: 4px;
padding: 4px;
margin: 2px 0;
}

.old-value {
color: #dc3545;
text-decoration: line-through;
background: #ffe6e6;
padding: 2px 4px;
border-radius: 3px;
display: inline-block;
margin: 2px 0;
}

.new-value {
color: #28a745;
background: #e6ffe6;
padding: 2px 4px;
border-radius: 3px;
display: inline-block;
margin: 2px 0;
}

.field-name {
font-weight: bold;
color: #495057;
font-size: 10px;
text-transform: uppercase;
margin-top: 2px;
}

.change-item {
border-bottom: 1px dashed #dee2e6;
padding: 3px 0;
}

.change-item:last-child {
border-bottom: none;
}

.tooltip-icon {
cursor: help;
color: #6c757d;
margin-left: 3px;
font-size: 10px;
}

/* Search Bar Styles */
.search-box {
position: relative;
width: 100%;
max-width: 400px;
}

.search-box i {
position: absolute;
left: 12px;
top: 50%;
transform: translateY(-50%);
color: #6c757d;
font-size: 14px;
}

.search-box input {
width: 100%;
padding: 10px 12px 10px 36px;
border: 1px solid #dee2e6;
border-radius: 24px;
font-size: 14px;
outline: none;
transition: all 0.2s;
}

.search-box input:focus {
border-color: #007bff;
box-shadow: 0 1px 4px rgba(0,123,255,0.2);
}

.search-box input::placeholder {
color: #6c757d;
}

/* Real-time update indicator */
.update-indicator {
display: inline-block;
width: 10px;
height: 10px;
border-radius: 50%;
background-color: #28a745;
margin-right: 5px;
animation: pulse 2s infinite;
}

@keyframes pulse {
0% {
opacity: 1;
}
50% {
opacity: 0.3;
}
100% {
opacity: 1;
}
}

.new-row {
animation: highlight 2s ease-out;
}

@keyframes highlight {
0% {
background-color: #fff3cd;
}
100% {
background-color: transparent;
}
}

.last-updated {
font-size: 12px;
color: #6c757d;
}

.control-panel {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 20px;
flex-wrap: wrap;
gap: 15px;
}

.refresh-control {
display: flex;
align-items: center;
gap: 15px;
}

.auto-refresh-toggle {
display: flex;
align-items: center;
gap: 8px;
}

.auto-refresh-toggle .form-check-input:checked {
background-color: #28a745;
border-color: #28a745;
}

.refresh-btn {
border-radius: 24px;
padding: 8px 20px;
}

.stats-updated {
font-size: 12px;
color: #6c757d;
}
</style>
</head>

<body>
<!-- Sidebar -->
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<!-- Main Content -->
<div class="main-content-wrapper">
<div class="container-fluid py-4">
<h3 class="mb-4">Audit Trail - Course Changes <span class="update-indicator" id="liveIndicator" title="Live updates active"></span></h3>

<!-- Control Panel -->
<div class="control-panel">
<!-- Search Bar -->
<div class="search-box">
<i class="fas fa-search"></i>
<input type="text" id="auditSearch" placeholder="Search by course title, username, or action...">
</div>

<!-- Refresh Controls -->
<div class="refresh-control">
<div class="auto-refresh-toggle">
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" id="autoRefresh" checked>
<label class="form-check-label" for="autoRefresh">Auto-refresh</label>
</div>
<select class="form-select form-select-sm" id="refreshInterval" style="width: auto;" <?= BASE_URL ?>>
<option value="5">5 sec</option>
<option value="10" selected>10 sec</option>
<option value="30">30 sec</option>
<option value="60">1 min</option>
</select>
</div>
<button class="btn btn-outline-primary refresh-btn" id="manualRefresh">
<i class="fas fa-sync-alt"></i> Refresh Now
</button>
<span class="last-updated" id="lastUpdated">
<i class="far fa-clock"></i> Last updated: just now
</span>
</div>
</div>

<!-- Statistics Cards (Dynamic) -->
<div class="row mb-4" id="statsContainer">
<!-- Will be populated by JavaScript -->
</div>

<!-- Course Activity Table -->
<div class="card">
<div class="card-header text-black d-flex justify-content-between align-items-center">
<h5 class="mb-0">Course Creation & Edit History</h5>
<span class="badge bg-success" id="recordCount"><?= count($course_actions) ?> records</span>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
<th>ID</th>
<th>Course Title</th>
<th>Created</th>
<th>Updated</th>
<th>Old Value <i class="fas fa-info-circle tooltip-icon" title="Previous values before change"></i></th>
<th>New Value <i class="fas fa-info-circle tooltip-icon" title="New values after change"></i></th>
<th>Action</th>
<th>Done By</th>
<th>Role</th>
</tr>
</thead>
<tbody id="auditTableBody">
<!-- Will be populated by JavaScript -->
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

<script>
// Store current data and state
let currentData = <?= json_encode($course_actions) ?>;
let lastTimestamp = null;
let refreshInterval = null;
let isAutoRefresh = true;
let searchTerm = '';

// Function to parse field changes (matches PHP function)
function parseFieldChanges(oldData, newData, changedFields) {
const result = { old: {}, new: {} };

if (oldData && newData) {
try {
const old = JSON.parse(oldData);
const new_ = JSON.parse(newData);
const fields = JSON.parse(changedFields);

if (Array.isArray(fields)) {
fields.forEach(field => {
if (old.hasOwnProperty(field) || new_.hasOwnProperty(field)) {
result.old[field] = old[field] !== undefined ? old[field] : 'NULL';
result.new[field] = new_[field] !== undefined ? new_[field] : 'NULL';
}
});
}
} catch (e) {
console.error('Error parsing field changes:', e);
}
}

return result;
}

// Function to format value display
function formatValue(value, type = 'old') {
if (value === null || value === undefined) return '';
const str = String(value);
const className = type === 'old' ? 'old-value' : 'new-value';
return `<span class="${className}">${escapeHtml(str.substring(0, 30))}${str.length > 30 ? '...' : ''}</span>`;
}

// Function to escape HTML
function escapeHtml(text) {
const div = document.createElement('div');
div.textContent = text;
return div.innerHTML;
}

// Function to render table rows
function renderTableRows(data) {
if (!data || data.length === 0) {
return `
<tr>
<td colspan="9" class="text-center py-5 text-muted">
<i class="fas fa-info-circle fa-2x mb-3"></i>
<p>No course activities found</p>
</td>
</tr>
`;
}

return data.map(item => {
const changes = parseFieldChanges(item.old_data, item.new_data, item.changed_fields);

// Build old values HTML
let oldValuesHtml = '';
if (Object.keys(changes.old).length > 0) {
oldValuesHtml = '<div class="value-changes">';
for (const [field, value] of Object.entries(changes.old)) {
oldValuesHtml += `
<div class="change-item">
<div class="field-name">${escapeHtml(field.charAt(0).toUpperCase() + field.slice(1))}:</div>
${formatValue(value, 'old')}
</div>
`;
}
oldValuesHtml += '</div>';
} else if (item.action_type === 'ADDED') {
oldValuesHtml = '<span class="text-muted"><em>New course</em></span>';
} else {
oldValuesHtml = '<span class="text-muted"><em>No old data</em></span>';
}

// Build new values HTML
let newValuesHtml = '';
if (Object.keys(changes.new).length > 0) {
newValuesHtml = '<div class="value-changes">';
for (const [field, value] of Object.entries(changes.new)) {
newValuesHtml += `
<div class="change-item">
<div class="field-name">${escapeHtml(field.charAt(0).toUpperCase() + field.slice(1))}:</div>
${formatValue(value, 'new')}
</div>
`;
}
newValuesHtml += '</div>';
} else if (item.action_type === 'ADDED') {
newValuesHtml = '<span class="text-success"><em>Initial values</em></span>';
} else {
newValuesHtml = '<span class="text-muted"><em>Current values</em></span>';
}

// Action badge
let actionBadge = '';
if (item.action_type === 'ADDED') {
actionBadge = '<span class="action-badge action-added"><i class="fas fa-plus-circle me-1"></i>Added</span>';
} else if (item.action_type === 'EDITED') {
actionBadge = '<span class="action-badge action-edited"><i class="fas fa-edit me-1"></i>Edited</span>';
} else if (item.action_type === 'DELETED') {
actionBadge = '<span class="action-badge action-deleted"><i class="fas fa-trash me-1"></i>Deleted</span>';
}

// Role badge
let roleBadge = '';
if (item.role === 'admin') {
roleBadge = '<span class="badge bg-primary">Admin</span>';
} else if (item.role === 'proponent') {
roleBadge = '<span class="badge bg-info">Proponent</span>';
} else if (item.role === 'superadmin') {
roleBadge = '<span class="badge bg-secondary">Super Admin</span>';
} else {
roleBadge = '<span class="badge bg-secondary">Unknown</span>';
}

// Done by HTML
let doneByHtml = '<span class="text-muted">Unknown</span>';
if (item.username) {
doneByHtml = `
<div class="d-flex align-items-center">
<div>
<strong>${escapeHtml(item.username)}</strong>
<br>
<small class="text-muted">${escapeHtml(item.fname || '')} ${escapeHtml(item.lname || '')}</small>
${item.audit_time ? `<br><small class="text-muted"><i class="far fa-clock"></i> ${escapeHtml(item.audit_time)}</small>` : ''}
</div>
</div>
`;
}

return `
<tr data-timestamp="${item.timestamp || ''}">
<td><span class="fw-bold">#${escapeHtml(item.course_id)}</span></td>
<td>${escapeHtml(item.title || 'Untitled')}</td>
<td>
<small>
${escapeHtml(item.created_at)}<br>
<span class="text-muted">${escapeHtml(item.created_time)}</span>
</small>
</td>
<td>
${item.updated_at ? 
`<small>${escapeHtml(item.updated_at)}<br><span class="text-muted">${escapeHtml(item.updated_time)}</span></small>` : 
'<span class="text-muted">Never updated</span>'}
</td>
<td>${oldValuesHtml}</td>
<td>${newValuesHtml}</td>
<td>${actionBadge}</td>
<td>${doneByHtml}</td>
<td>${roleBadge}</td>
</tr>
`;
}).join('');
}

// debuging function to update statistics cards based on current data
function updateStatistics(data) {
const total = data.length;
const added = data.filter(item => item.action_type === 'ADDED').length;
const edited = data.filter(item => item.action_type === 'EDITED').length;
const withChanges = data.filter(item => item.changed_fields).length;

document.getElementById('statsContainer').innerHTML = `
<div class="col-md-3">
<div class="stats-card">
<div class="stats-number">${total}</div>
<div class="stats-label">Total Activities</div>
</div>
</div>
<div class="col-md-3">
<div class="stats-card">
<div class="stats-number">${added}</div>
<div class="stats-label">Courses Added</div>
</div>
</div>
<div class="col-md-3">
<div class="stats-card">
<div class="stats-number">${edited}</div>
<div class="stats-label">Courses Edited</div>
</div>
</div>
<div class="col-md-3">
<div class="stats-card">
<div class="stats-number">${withChanges}</div>
<div class="stats-label">With Field Changes</div>
</div>
</div>
`;

document.getElementById('recordCount').textContent = `${total} records`;
}

// searrch filter function
function filterData(data) {
if (!searchTerm) return data;

return data.filter(item => {
const title = (item.title || '').toLowerCase();
const username = (item.username || '').toLowerCase();
const action = (item.action_type || '').toLowerCase();
const term = searchTerm.toLowerCase();

return title.includes(term) || username.includes(term) || action.includes(term);
});
}

// lasttimestampzxc base on the most recent record's audit_time, updated_at, or created_at
function refreshData(showAnimation = true) {
const url = window.location.href + '?ajax=get_audit_data' + (lastTimestamp ? `&last_timestamp=${encodeURIComponent(lastTimestamp)}` : '');

fetch(url)
.then(response => response.json())
.then(result => {
if (result.success) {
if (result.data.length > 0) {
// Update last timestamp
if (result.latest_timestamp) {
lastTimestamp = result.latest_timestamp;
}

// Merge new data with existing data (avoid duplicates)
const existingIds = new Set(currentData.map(item => item.course_id + '_' + (item.audit_time || item.updated_at || item.created_at)));
const newItems = result.data.filter(item => 
!existingIds.has(item.course_id + '_' + (item.audit_time || item.updated_at || item.created_at))
);

if (newItems.length > 0) {
// Add new items to beginning of array
currentData = [...newItems, ...currentData];

// Re-render with animation for new rows
const filteredData = filterData(currentData);
document.getElementById('auditTableBody').innerHTML = renderTableRows(filteredData);

// Highlight new rows
if (showAnimation) {
setTimeout(() => {
document.querySelectorAll('#auditTableBody tr').forEach((row, index) => {
if (index < newItems.length) {
row.classList.add('new-row');
}
});
}, 10);
}

// Update statistics
updateStatistics(currentData);

// Show notification
showNotification(`${newItems.length} new update(s) received`);
}
}

// Update last updated time
document.getElementById('lastUpdated').innerHTML = `<i class="far fa-clock"></i> Last updated: just now`;
}
})
.catch(error => {
console.error('Error refreshing data:', error);
document.getElementById('lastUpdated').innerHTML = `<i class="far fa-clock"></i> Last updated: error - will retry`;
});
}

// Function to show notification
function showNotification(message) {
const notification = document.createElement('div');
notification.className = 'alert alert-info alert-dismissible fade show position-fixed bottom-0 end-0 m-3';
notification.style.zIndex = '9999';
notification.innerHTML = `
<i class="fas fa-info-circle me-2"></i>
${message}
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
`;
document.body.appendChild(notification);

setTimeout(() => {
notification.remove();
}, 3000);
}

// Function to start auto-refresh
function startAutoRefresh(interval) {
if (refreshInterval) {
clearInterval(refreshInterval);
}
refreshInterval = setInterval(() => refreshData(true), interval * 1000);
}

// Function to stop auto-refresh
function stopAutoRefresh() {
if (refreshInterval) {
clearInterval(refreshInterval);
refreshInterval = null;
}
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
// Initial render
const filteredData = filterData(currentData);
document.getElementById('auditTableBody').innerHTML = renderTableRows(filteredData);
updateStatistics(currentData);

// Set initial last timestamp
if (currentData.length > 0) {
const latest = currentData[0];
lastTimestamp = latest.audit_time || latest.updated_at || latest.created_at;
}

// Search functionality
const searchInput = document.getElementById('auditSearch');
searchInput.addEventListener('keyup', function() {
searchTerm = this.value;
const filteredData = filterData(currentData);
document.getElementById('auditTableBody').innerHTML = renderTableRows(filteredData);
});

// Auto-refresh toggle
const autoRefreshCheckbox = document.getElementById('autoRefresh');
const refreshIntervalSelect = document.getElementById('refreshInterval');

autoRefreshCheckbox.addEventListener('change', function() {
isAutoRefresh = this.checked;
if (isAutoRefresh) {
startAutoRefresh(parseInt(refreshIntervalSelect.value));
document.getElementById('liveIndicator').style.opacity = '1';
} else {
stopAutoRefresh();
document.getElementById('liveIndicator').style.opacity = '0.3';
}
});

// Refresh interval change
refreshIntervalSelect.addEventListener('change', function() {
if (isAutoRefresh) {
startAutoRefresh(parseInt(this.value));
}
});

// Manual refresh button
document.getElementById('manualRefresh').addEventListener('click', function() {
const icon = this.querySelector('i');
icon.classList.add('fa-spin');
refreshData(true).finally(() => {
setTimeout(() => icon.classList.remove('fa-spin'), 500);
});
});

// Start auto-refresh by default
startAutoRefresh(10);

// Update last updated time every second
setInterval(() => {
const lastUpdatedEl = document.getElementById('lastUpdated');
const currentText = lastUpdatedEl.innerHTML;
if (currentText.includes('just now')) {
// Already showing just now
} else if (currentText.includes('seconds ago')) {
// Update logic could be added here
}
}, 1000);

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function(tooltipTriggerEl) {
return new bootstrap.Tooltip(tooltipTriggerEl);
});
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>