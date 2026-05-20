<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}

require_once __DIR__ . '/db.php';
ensureSessionDateOverridesTable();
$teacherId = (int)$_SESSION['user_id'];

// GET — return overrides for a given group
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $groupId = (int)($_GET['group_id'] ?? 0);
    if (!$groupId) { echo json_encode(['ok'=>true,'overrides'=>[]]); exit; }
    $stmt = db()->prepare("SELECT session_num, actual_date FROM session_date_overrides WHERE teacher_id = ? AND group_id = ?");
    $stmt->execute([$teacherId, $groupId]);
    $overrides = [];
    foreach ($stmt->fetchAll() as $r) {
        $overrides[(int)$r['session_num']] = $r['actual_date'];
    }
    echo json_encode(['ok'=>true,'overrides'=>$overrides]);
    exit;
}

// POST — upsert or delete an override
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/csrf.php';
    csrf_verify();
    $body    = json_decode(file_get_contents('php://input'), true);
    $groupId = (int)($body['group_id']   ?? 0);
    $sessNum = (int)($body['session_num'] ?? 0);
    $date    = trim($body['date'] ?? '');
    if (!$groupId || $sessNum < 1 || $sessNum > 20) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Invalid payload']); exit;
    }
    if ($date === '') {
        db()->prepare("DELETE FROM session_date_overrides WHERE teacher_id = ? AND group_id = ? AND session_num = ?")
           ->execute([$teacherId, $groupId, $sessNum]);
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Invalid date format']); exit;
        }
        db()->prepare("
            INSERT INTO session_date_overrides (teacher_id, group_id, session_num, actual_date)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE actual_date = VALUES(actual_date), updated_at = NOW()
        ")->execute([$teacherId, $groupId, $sessNum, $date]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
