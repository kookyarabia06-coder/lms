<?php
// inc/functions.php
require_once __DIR__ . '/config.php';

/* ----------------------
   Upload helper
------------------------*/
function safe_upload($file_input_name, $target_dir, $allowed_ext = ['pdf','mp4','webm','ogg']) {
    if(!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$file_input_name];
    $orig = $f['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if(!in_array($ext, $allowed_ext)) return null;
    if($f['size'] > 200 * 1024 * 1024) return null; // 200MB cap
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $dest = rtrim($target_dir, '/') . '/' . $name;
    if(!move_uploaded_file($f['tmp_name'], $dest)) return null;
    return $name;
}

/* ----------------------
   Enrollment helpers
------------------------*/
function count_active_enrollments($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status = 'ongoing' AND (expired_at IS NULL OR expired_at >= CURDATE())");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function enroll_course($user_id, $course_id, $expired_at = null) {
    global $pdo;
    // check duplicate
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$user_id, $course_id]);
    if($stmt->fetch()) return ['ok'=>false,'msg'=>'Already enrolled'];

    // check active count
    if(count_active_enrollments($user_id) >= 5) {
        return ['ok'=>false,'msg'=>'You must finish or let one course expire before enrolling in a new course.'];
    }

    $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_id, enrolled_at, expired_at, progress, status, total_time_seconds) VALUES (?, ?, NOW(), ?, 0, "ongoing", 0)');
    $stmt->execute([$user_id, $course_id, $expired_at]);
    return ['ok'=>true,'id'=>$pdo->lastInsertId()];
}

/* ----------------------
   User / Role helpers
------------------------*/
if (!function_exists('current_user')) {
    function current_user() {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        $u = current_user();
        return $u && isset($u['role']) && $u['role'] === 'admin';
    }
}

if (!function_exists('is_proponent')) {
    function is_proponent() {
        $u = current_user();
        return $u && isset($u['role']) && $u['role'] === 'proponent';
    }
}
if (!function_exists('is_student')) { 
    function is_student() {
        $u = current_user();
        return $u && isset($u['role']) && $u['role'] === 'user';
    }
}

if (!function_exists('destroy_session')) {
    function destroy_session() {
        session_start();
        session_unset();
        session_destroy();
        return true;
    }
}

// inc/functions.php

function getCourseStatus($expiresAt, $isActive = true) {
    // Check if course is inactive
    if (!$isActive) {
        return 'Deactivated';
    }
    
    // Check if course has no expiration
    if ($expiresAt === null || $expiresAt === '') {
        return 'Active (No Expiry)';
    }
    
    $currentDate = new DateTime();
    $expiryDate = new DateTime($expiresAt);
    
    // Check if expired
    if ($expiryDate < $currentDate) {
        return 'Expired';
    }
    
    // Check if expiring soon (within 7 days)
    $interval = $currentDate->diff($expiryDate);
    $daysRemaining = (int)$interval->format('%r%a');
    
    if ($daysRemaining === 0) {
        return 'Expires Today';
    } elseif ($daysRemaining <= 7) {
        return 'Expiring Soon';
    }
    
    return 'Active';
}

function getCourseStatusBadge($expiresAt, $isActive = true) {
    $status = getCourseStatus($expiresAt, $isActive);
    
    $badgeClasses = [
        'Active' => 'badge-success',
        'Active (No Expiry)' => 'badge-info',
        'Expired' => 'badge-danger',
        'Expiring Soon' => 'badge-warning',
        'Expires Today' => 'badge-warning',
        'Deactivated' => 'badge-secondary'
    ];
    
    $badgeClass = $badgeClasses[$status] ?? 'badge-light';
    
    return '<span class="badge ' . $badgeClass . '">' . $status . '</span>';
}

function isCourseExpired($expiresAt) {
    if (!$expiresAt) return false;
    return (new DateTime($expiresAt)) < new DateTime();
}


?> 