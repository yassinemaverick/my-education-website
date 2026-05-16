<?php
/**
 * api_activity.php — Persistent admin activity log
 * ─────────────────────────────────────────────────
 * Auth : admin only
 *
 * GET  ?action=list&limit=50&offset=0&type=all  → paginated log entries
 * POST action=log   (internal use — log any admin action)
 *
 * Also called by login.php, dashboard-admin.php etc. to auto-log events.
 * External callers include this file and call logActivity() directly.
 */

require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$role = $_SESSION['role'] ?? '';
$uid  = (int)($_SESSION['user_id'] ?? 0);

if (!$uid || $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── Schema bootstrap ─────────────────────────────────────────────────────────
function ensureActivityLog(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            type        VARCHAR(60)  NOT NULL,
            description TEXT         NOT NULL,
            ip          VARCHAR(45)  DEFAULT NULL,
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user    (user_id),
            INDEX idx_type    (type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

try {
    $pdo    = db();
    $action = trim($_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list')));

    ensureActivityLog($pdo);

    // ════════════════════════════════════════════════════════
    // GET list
    // ════════════════════════════════════════════════════════
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit  = min((int)($_GET['limit']  ?? 50), 200);
        $offset = max((int)($_GET['offset'] ?? 0),  0);
        $type   = trim($_GET['type'] ?? 'all');
        $search = trim($_GET['search'] ?? '');

        $where  = [];
        $params = [];

        if ($type !== 'all') {
            $where[]  = 'l.type = ?';
            $params[] = $type;
        }
        if ($search !== '') {
            $where[]  = '(l.description LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)';
            $like     = "%{$search}%";
            array_push($params, $like, $like, $like);
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $pdo->prepare("
            SELECT COUNT(*) FROM activity_log l
            JOIN users u ON u.id = l.user_id
            {$whereSQL}
        ");
        $total->execute($params);
        $totalCount = (int)$total->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT l.id, l.type, l.description, l.ip, l.created_at,
                   u.full_name, u.username, u.role
            FROM   activity_log l
            JOIN   users u ON u.id = l.user_id
            {$whereSQL}
            ORDER  BY l.created_at DESC
            LIMIT  ? OFFSET ?
        ");
        array_push($params, $limit, $offset);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Distinct types for filter UI
        $types = $pdo->query("SELECT DISTINCT type FROM activity_log ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'ok'    => true,
            'total' => $totalCount,
            'rows'  => $rows,
            'types' => $types,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // POST log — write an entry (admin-initiated action)
    // ════════════════════════════════════════════════════════
    if ($action === 'log') {
        $raw  = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = trim($raw['type']        ?? '');
        $desc = trim($raw['description'] ?? '');
        if (!$type || !$desc) { echo json_encode(['ok'=>false,'error'=>'type and description required']); exit; }
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) ?: ($_SERVER['REMOTE_ADDR'] ?? null);
        $pdo->prepare("INSERT INTO activity_log (user_id, type, description, ip) VALUES (?,?,?,?)")
            ->execute([$uid, $type, $desc, $ip]);
        echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);

} catch (Throwable $e) {
    error_log('api_activity.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
