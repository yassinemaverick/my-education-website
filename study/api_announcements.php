<?php
/**
 * api_announcements.php — Admin announcements
 * Actions: list, create (admin), delete (admin)
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = trim($_GET['action'] ?? ($body['action'] ?? 'list'));

if ($method === 'POST') csrf_verify();

require_once __DIR__ . '/db.php';

try {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        author_id  INT NOT NULL,
        title      VARCHAR(200) NOT NULL,
        body       TEXT NOT NULL,
        target     ENUM('all','students','teachers') NOT NULL DEFAULT 'all',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── list ──────────────────────────────────────────────────────────────────
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT a.id, a.title, a.body, a.target, a.created_at,
                   u.full_name AS author_name
            FROM announcements a
            JOIN users u ON u.id=a.author_id
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        echo json_encode(['ok'=>true,'announcements'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ── create ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        if ($role !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Admin only']); exit; }
        $title  = trim($body['title'] ?? '');
        $text   = trim($body['body']  ?? '');
        $target = in_array($body['target'] ?? 'all', ['all','students','teachers']) ? ($body['target'] ?? 'all') : 'all';
        if (!$title || !$text) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'title and body required']); exit; }
        if (mb_strlen($title) > 200 || mb_strlen($text) > 2000) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Too long']); exit; }
        $pdo->prepare("INSERT INTO announcements (author_id,title,body,target) VALUES (?,?,?,?)")
            ->execute([$uid, $title, $text, $target]);
        $annId = (int)$pdo->lastInsertId();
        // Push notifications
        $users = match($target) {
            'students' => $pdo->query("SELECT id FROM users WHERE role='student'"),
            'teachers' => $pdo->query("SELECT id FROM users WHERE role='teacher'"),
            default    => $pdo->query("SELECT id FROM users WHERE role IN ('student','teacher')"),
        }->fetchAll(PDO::FETCH_COLUMN);
        $ni = $pdo->prepare("INSERT IGNORE INTO notifications (user_id,type,title_fr,title_ar,body_fr,body_ar) VALUES (?,'announcement',?,?,?,?)");
        foreach ($users as $userId) {
            $ni->execute([$userId, '📢 ' . $title, '📢 ' . $title, $text, $text]);
        }
        echo json_encode(['ok'=>true,'id'=>$annId]);
        exit;
    }

    // ── delete ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        if ($role !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Admin only']); exit; }
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'id required']); exit; }
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Unknown action']);

} catch (Throwable $e) {
    error_log('api_announcements.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
