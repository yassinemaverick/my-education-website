<?php
/**
 * api_submit.php — Student assignment submission endpoint
 * ────────────────────────────────────────────────────────
 * POST ?action=submit   → submit / update an assignment
 * POST ?action=unsubmit → retract a submission (only if not yet reviewed)
 * GET  ?action=get&assignment_id=N → get submission details
 */

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Auth: students only
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF check for mutating actions
$action    = trim($_GET['action'] ?? $_POST['action'] ?? 'submit');
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (in_array($action, ['submit','unsubmit']) && !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo       = db();
$studentId = (int) $_SESSION['user_id'];

// Ensure submissions table has comment column
try {
    $pdo->exec("ALTER TABLE assignment_submissions
        ADD COLUMN IF NOT EXISTS comment TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ");
} catch (Throwable $e) {}

// ── GET: fetch submission details ────────────────────────────────────────────
if ($action === 'get') {
    $aid = (int)($_GET['assignment_id'] ?? 0);
    if (!$aid) { echo json_encode(['ok'=>false,'error'=>'Missing assignment_id']); exit; }

    $stmt = $pdo->prepare("
        SELECT sub.*, a.title_fr, a.title_ar, a.description_fr, a.description_ar,
               a.due_date, a.subject_fr, a.subject_ar
        FROM   assignment_submissions sub
        JOIN   assignments a ON a.id = sub.assignment_id
        WHERE  sub.assignment_id = ? AND sub.student_id = ?
    ");
    $stmt->execute([$aid, $studentId]);
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'submission' => $row ?: null]);
    exit;
}

// ── POST: submit ─────────────────────────────────────────────────────────────
if ($action === 'submit') {
    $raw     = json_decode(file_get_contents('php://input'), true) ?? [];
    $aid     = (int)($raw['assignment_id'] ?? 0);
    $comment = trim($raw['comment'] ?? '');

    if (!$aid) { echo json_encode(['ok'=>false,'error'=>'Missing assignment_id']); exit; }
    if (mb_strlen($comment) > 2000) { echo json_encode(['ok'=>false,'error'=>'Comment too long (max 2000 chars)']); exit; }

    // Verify student is enrolled in the course this assignment belongs to
    $check = $pdo->prepare("
        SELECT a.id FROM assignments a
        JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
        WHERE  a.id = ?
    ");
    $check->execute([$studentId, $aid]);
    if (!$check->fetch()) {
        echo json_encode(['ok'=>false,'error'=>'Assignment not found or not enrolled']);
        exit;
    }

    // Upsert submission
    $pdo->prepare("
        INSERT INTO assignment_submissions (assignment_id, student_id, status, comment, submitted_at)
        VALUES (?, ?, 'submitted', ?, NOW())
        ON DUPLICATE KEY UPDATE
            status       = 'submitted',
            comment      = VALUES(comment),
            submitted_at = NOW()
    ")->execute([$aid, $studentId, $comment ?: null]);

    echo json_encode(['ok' => true, 'submitted_at' => date('Y-m-d H:i:s')]);
    exit;
}

// ── POST: unsubmit (retract) ─────────────────────────────────────────────────
if ($action === 'unsubmit') {
    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    $aid = (int)($raw['assignment_id'] ?? 0);
    if (!$aid) { echo json_encode(['ok'=>false,'error'=>'Missing assignment_id']); exit; }

    // Only allow retraction if not yet reviewed (status = submitted)
    $pdo->prepare("
        UPDATE assignment_submissions
        SET    status = 'pending', comment = NULL, submitted_at = NULL
        WHERE  assignment_id = ? AND student_id = ? AND status = 'submitted'
    ")->execute([$aid, $studentId]);

    // Notify teacher that student retracted their submission
    try {
        $assign = $pdo->prepare("SELECT a.title_fr, a.teacher_id FROM assignments a WHERE a.id = ?");
        $assign->execute([$aid]);
        $a = $assign->fetch();
        if ($a) {
            $name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Étudiant';
            $pdo->prepare("INSERT INTO notifications (user_id,type,message) VALUES (?,?,?)")
                ->execute([$a['teacher_id'], 'submission_retracted',
                    "↩️ {$name} a rétracté / retracted «{$a['title_fr']}»"]);
        }
    } catch(Throwable $e) {}

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
