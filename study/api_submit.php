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

// Ensure submissions table has all columns
try {
    $pdo->exec("ALTER TABLE assignment_submissions
        ADD COLUMN IF NOT EXISTS comment TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) DEFAULT NULL,
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
    $aid     = (int)($_POST['assignment_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

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

    // Handle optional file upload
    $filePath    = null;
    $uploadsDir  = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }
    if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok'=>false,'error'=>'Upload error (code '.$f['error'].').']); exit;
        }
        if ($f['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok'=>false,'error'=>'File too large (max 10 MB)']); exit;
        }
        $ext     = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','jpg','jpeg','png','gif','zip','mp3','mp4'];
        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['ok'=>false,'error'=>'File type not allowed']); exit;
        }
        if (!is_writable($uploadsDir)) {
            echo json_encode(['ok'=>false,'error'=>'Upload directory not writable — contact admin']); exit;
        }
        $filename = uniqid('sub_', true) . '.' . $ext;
        $dest     = $uploadsDir . '/' . $filename;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            echo json_encode(['ok'=>false,'error'=>'Failed to save uploaded file']); exit;
        }
        $filePath = 'uploads/' . $filename;
    }

    // Upsert submission (COALESCE keeps existing file if student resubmits without a new file)
    $pdo->prepare("
        INSERT INTO assignment_submissions (assignment_id, student_id, status, comment, file_path, submitted_at)
        VALUES (?, ?, 'submitted', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status       = 'submitted',
            comment      = VALUES(comment),
            file_path    = COALESCE(VALUES(file_path), file_path),
            submitted_at = NOW()
    ")->execute([$aid, $studentId, $comment ?: null, $filePath]);

    echo json_encode(['ok' => true, 'submitted_at' => date('Y-m-d H:i:s'), 'file_path' => $filePath]);
    exit;
}

// ── POST: unsubmit (retract) ─────────────────────────────────────────────────
if ($action === 'unsubmit') {
    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    $aid = (int)($raw['assignment_id'] ?? 0);
    if (!$aid) { echo json_encode(['ok'=>false,'error'=>'Missing assignment_id']); exit; }

    // Only allow retraction if not yet graded (score must be NULL)
    $stmt = $pdo->prepare("
        UPDATE assignment_submissions
        SET    status = 'pending', comment = NULL, submitted_at = NULL
        WHERE  assignment_id = ? AND student_id = ? AND status = 'submitted' AND score IS NULL
    ");
    $stmt->execute([$aid, $studentId]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok'=>false,'error'=>'Cannot retract a graded submission']);
        exit;
    }

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
