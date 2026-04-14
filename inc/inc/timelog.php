<?php
// inc/timelog.php - accepts JSON {enrollment_id, seconds}
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if(!$data || !isset($data['enrollment_id']) || !isset($data['seconds'])) {
    echo json_encode(['ok'=>false,'msg'=>'invalid']);
    exit;
}
$enrollment_id = (int)$data['enrollment_id'];
$seconds = (int)$data['seconds'];
if($seconds <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'no time']);
    exit;
}
// validate enrollment exists and is ongoing
$stmt = $pdo->prepare('SELECT user_id, status FROM enrollments WHERE id = ?');
$stmt->execute([$enrollment_id]);
$en = $stmt->fetch();
if(!$en) { echo json_encode(['ok'=>false,'msg'=>'no enroll']); exit; }
if($en['status'] !== 'ongoing') { echo json_encode(['ok'=>false,'msg'=>'not ongoing']); exit; }
// optionally check session user matches enrollment (basic)
if(isset($_SESSION['user']) && $_SESSION['user']['id'] != $en['user_id']) { echo json_encode(['ok'=>false,'msg'=>'not owner']); exit; }
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$start = date('Y-m-d H:i:s', time() - $seconds);
$end = date('Y-m-d H:i:s');
$stmt = $pdo->prepare('INSERT INTO time_logs (enrollment_id, start_ts, end_ts, seconds, user_agent) VALUES (?,?,?,?,?)');
$stmt->execute([$enrollment_id, $start, $end, $seconds, $user_agent]);
$stmt = $pdo->prepare('UPDATE enrollments SET total_time_seconds = total_time_seconds + ? WHERE id = ?');
$stmt->execute([$seconds, $enrollment_id]);
echo json_encode(['ok'=>true]);
?>
