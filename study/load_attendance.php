<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}

require_once __DIR__ . '/db.php';
try {
    ensureAttendanceTable();
    $rows = db()->prepare("SELECT student_id, session_num, present FROM attendance WHERE teacher_id = ?");
    $rows->execute([$_SESSION['user_id']]);
    $result = [];
    foreach ($rows->fetchAll() as $r) {
        $result[$r['student_id']][$r['session_num']] = (bool)$r['present'];
    }
    echo json_encode(['ok'=>true,'attendance'=>$result]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
