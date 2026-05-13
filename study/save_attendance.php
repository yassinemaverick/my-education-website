<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}
csrf_verify();

$body = json_decode(file_get_contents('php://input'), true);
if (!isset($body['attendance']) || !is_array($body['attendance'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid payload']); exit;
}

require_once __DIR__ . '/db.php';
try {
    ensureAttendanceTable();
    $pdo  = db();
    $stmt = $pdo->prepare("
        INSERT INTO attendance (teacher_id, student_id, session_num, present)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE present = VALUES(present), updated_at = NOW()
    ");
    $pdo->beginTransaction();
    foreach ($body['attendance'] as $sid => $sessions) {
        foreach ($sessions as $sess => $present) {
            $stmt->execute([(int)$_SESSION['user_id'], (int)$sid, (int)$sess, $present ? 1 : 0]);
        }
    }
    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
