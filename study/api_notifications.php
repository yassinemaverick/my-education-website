<?php
/**
 * api_notifications.php
 * Returns notifications for the logged-in user (student or teacher).
 * Also handles marking notifications as read.
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$uid    = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    // ── Ensure notifications table exists ─────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            type       VARCHAR(40) NOT NULL DEFAULT 'info',
            title_fr   VARCHAR(200) NOT NULL DEFAULT '',
            title_ar   VARCHAR(200) NOT NULL DEFAULT '',
            title_en   VARCHAR(200) NOT NULL DEFAULT '',
            body_fr    VARCHAR(400) NOT NULL DEFAULT '',
            body_ar    VARCHAR(400) NOT NULL DEFAULT '',
            body_en    VARCHAR(400) NOT NULL DEFAULT '',
            is_read    TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try { $pdo->exec("ALTER TABLE notifications ADD COLUMN title_en VARCHAR(200) NOT NULL DEFAULT ''"); } catch(Throwable $e) {}
    try { $pdo->exec("ALTER TABLE notifications ADD COLUMN body_en  VARCHAR(400) NOT NULL DEFAULT ''"); } catch(Throwable $e) {}

    // ── CSRF check for all mutating POST requests ────────────────────────────
    if ($method === 'POST') csrf_verify();

    // ── Send message (teacher → student) ─────────────────────────────────────
    if ($method === 'POST' && $action === 'send_message') {
        if (!in_array($role, ['teacher','admin'])) {
            http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
        }
        $mb       = json_decode(file_get_contents('php://input'), true) ?? [];
        $targetId = (int)($mb['user_id'] ?? 0);
        $msgText  = trim($mb['message'] ?? '');
        $sender   = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Professeur';
        if (!$targetId || !$msgText) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'user_id and message required']); exit;
        }
        if (mb_strlen($msgText) > 500) {
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Message too long (max 500 chars)']); exit;
        }
        $pdo->prepare("INSERT INTO notifications (user_id,type,title_fr,title_ar,title_en,body_fr,body_ar,body_en)
                       VALUES (?,'message',?,?,?,?,?,?)")
            ->execute([$targetId, '💬 Message de '.$sender, '💬 رسالة من '.$sender, '💬 Message from '.$sender, $msgText, $msgText, $msgText]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Mark all as read ─────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'mark_read') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Mark single as read ──────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'mark_one') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($nid) $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $uid]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Generate notifications from DB events (student) ──────────────────────
    if ($role === 'student') {

        // New assignments in student's course (posted in last 7 days, not yet notified)
        try {
            $rows = $pdo->prepare("
                SELECT DISTINCT a.id, a.title_fr, a.title_ar, a.due_date, a.created_at
                FROM assignments a
                JOIN student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND NOT EXISTS (
                    SELECT 1 FROM notifications n
                    WHERE n.user_id = ? AND n.type = 'new_assignment'
                    AND n.body_fr LIKE CONCAT('%#', a.id, '%')
                  )
                ORDER BY a.created_at DESC LIMIT 5
            ");
            $rows->execute([$uid, $uid]);
            foreach ($rows->fetchAll() as $r) {
                $due = $r['due_date'] ? date('d/m/Y', strtotime($r['due_date'])) : '';
                $pdo->prepare("
                    INSERT IGNORE INTO notifications (user_id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en)
                    VALUES (?, 'new_assignment', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $uid,
                    'Nouveau devoir',
                    'واجب جديد',
                    'New assignment',
                    ($r['title_fr'] ?: $r['title_ar']) . ($due ? " · Dû le {$due}" : '') . " #" . $r['id'],
                    ($r['title_ar'] ?: $r['title_fr']) . ($due ? " · تسليم {$due}" : '') . " #" . $r['id'],
                    ($r['title_fr'] ?: $r['title_ar']) . ($due ? " · Due on {$due}" : '') . " #" . $r['id'],
                ]);
            }
        } catch (Throwable $e) {}

        // Overdue assignments
        try {
            $rows = $pdo->prepare("
                SELECT DISTINCT a.id, a.title_fr, a.title_ar, a.due_date
                FROM assignments a
                JOIN student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
                JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
                WHERE sub.status = 'overdue'
                  AND NOT EXISTS (
                    SELECT 1 FROM notifications n
                    WHERE n.user_id = ? AND n.type = 'overdue'
                    AND n.body_fr LIKE CONCAT('%#', a.id, '%')
                  )
                ORDER BY a.due_date DESC LIMIT 3
            ");
            $rows->execute([$uid, $uid, $uid]);
            foreach ($rows->fetchAll() as $r) {
                $due = $r['due_date'] ? date('d/m/Y', strtotime($r['due_date'])) : '';
                $pdo->prepare("
                    INSERT IGNORE INTO notifications (user_id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en)
                    VALUES (?, 'overdue', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $uid,
                    '⚠️ Devoir en retard',
                    '⚠️ واجب متأخر',
                    '⚠️ Overdue assignment',
                    ($r['title_fr'] ?: $r['title_ar']) . ($due ? " · était dû le {$due}" : '') . " #" . $r['id'],
                    ($r['title_ar'] ?: $r['title_fr']) . ($due ? " · كان موعده {$due}" : '') . " #" . $r['id'],
                    ($r['title_fr'] ?: $r['title_ar']) . ($due ? " · was due on {$due}" : '') . " #" . $r['id'],
                ]);
            }
        } catch (Throwable $e) {}
    }

    // ── Generate notifications from DB events (teacher) ───────────────────────
    if ($role === 'teacher') {
        try {
            // Get teacher's course IDs
            $cRows = $pdo->prepare("SELECT DISTINCT course_id FROM teacher_courses WHERE teacher_id = ?");
            $cRows->execute([$uid]);
            $courseIds = array_column($cRows->fetchAll(), 'course_id');

            if ($courseIds) {
                $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
                $subs = $pdo->prepare("
                    SELECT sub.id, sub.student_id, sub.assignment_id, sub.submitted_at,
                           u.full_name, a.title_fr, a.title_ar
                    FROM assignment_submissions sub
                    JOIN users u ON u.id = sub.student_id
                    JOIN assignments a ON a.id = sub.assignment_id
                    WHERE sub.status = 'submitted'
                      AND a.course_id IN ($placeholders)
                      AND sub.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      AND NOT EXISTS (
                        SELECT 1 FROM notifications n
                        WHERE n.user_id = ? AND n.type = 'submission'
                        AND n.body_fr LIKE CONCAT('%#', sub.id, '%')
                      )
                    ORDER BY sub.submitted_at DESC LIMIT 10
                ");
                $params = array_merge($courseIds, [$uid]);
                $subs->execute($params);
                foreach ($subs->fetchAll() as $r) {
                    $pdo->prepare("
                        INSERT IGNORE INTO notifications (user_id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en)
                        VALUES (?, 'submission', ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $uid,
                        'Devoir soumis',
                        'تم تسليم واجب',
                        'Assignment submitted',
                        $r['full_name'] . ' a soumis : ' . ($r['title_fr'] ?: $r['title_ar']) . " #" . $r['id'],
                        $r['full_name'] . ' سلّم : ' . ($r['title_ar'] ?: $r['title_fr']) . " #" . $r['id'],
                        $r['full_name'] . ' submitted: ' . ($r['title_fr'] ?: $r['title_ar']) . " #" . $r['id'],
                    ]);
                }
            }
        } catch (Throwable $e) {}
    }

    // ── Generate notifications from DB events (admin) ────────────────────────
    if ($role === 'admin') {
        // New enrollment requests (status = 'new', last 7 days)
        try {
            $rows = $pdo->prepare("
                SELECT e.id, e.name, e.course
                FROM enrollments e
                WHERE e.status = 'new'
                  AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND NOT EXISTS (
                    SELECT 1 FROM notifications n
                    WHERE n.user_id = ? AND n.type = 'new_enrollment'
                    AND n.body_fr LIKE CONCAT('%#', e.id, '%')
                  )
                ORDER BY e.created_at DESC LIMIT 10
            ");
            $rows->execute([$uid]);
            foreach ($rows->fetchAll() as $r) {
                $course = $r['course'] ? ' · ' . $r['course'] : '';
                $body   = $r['name'] . $course . ' #' . $r['id'];
                $pdo->prepare("
                    INSERT IGNORE INTO notifications (user_id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en)
                    VALUES (?, 'new_enrollment', ?, ?, ?, ?, ?, ?)
                ")->execute([$uid, 'Nouvelle inscription', 'تسجيل جديد', 'New enrollment', $body, $body, $body]);
            }
        } catch (Throwable $e) {}

        // New placement test results (last 7 days)
        try {
            $rows = $pdo->prepare("
                SELECT pr.id, pr.name, pr.placement
                FROM placement_results pr
                WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND NOT EXISTS (
                    SELECT 1 FROM notifications n
                    WHERE n.user_id = ? AND n.type = 'new_placement'
                    AND n.body_fr LIKE CONCAT('%#', pr.id, '%')
                  )
                ORDER BY pr.created_at DESC LIMIT 10
            ");
            $rows->execute([$uid]);
            foreach ($rows->fetchAll() as $r) {
                $body = $r['name'] . ' → ' . $r['placement'] . ' #' . $r['id'];
                $pdo->prepare("
                    INSERT IGNORE INTO notifications (user_id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en)
                    VALUES (?, 'new_placement', ?, ?, ?, ?, ?, ?)
                ")->execute([$uid, 'Test de niveau', 'اختبار المستوى', 'Placement test', $body, $body, $body]);
            }
        } catch (Throwable $e) {}
    }

    // ── Remove exact duplicates (same user + type + body_fr), keep oldest ────
    $pdo->prepare("
        DELETE n1 FROM notifications n1
        INNER JOIN notifications n2
          ON n1.user_id = n2.user_id
         AND n1.type    = n2.type
         AND n1.body_fr = n2.body_fr
         AND n1.id      > n2.id
        WHERE n1.user_id = ?
    ")->execute([$uid]);

    // ── Fetch notifications ───────────────────────────────────────────────────
    $rows = $pdo->prepare("
        SELECT id, type, title_fr, title_ar, title_en, body_fr, body_ar, body_en, is_read,
               created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_min
        FROM notifications
        WHERE user_id = ?
        ORDER BY is_read ASC, created_at DESC
        LIMIT 15
    ");
    $rows->execute([$uid]);
    $notifs = $rows->fetchAll();

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $cntStmt->execute([$uid]);
    $unread = (int) $cntStmt->fetchColumn();

    echo json_encode(['ok' => true, 'notifications' => $notifs, 'unread' => $unread], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_notifications.php error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error', 'notifications' => [], 'unread' => 0]);
}
