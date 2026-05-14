<?php
/**
 * api_assignments.php — Assignment management + submission review
 * Works with the real DB schema (title_fr/title_ar, no teacher_id initially).
 * Missing columns are added via ALTER TABLE on first run.
 *
 * GET  ?action=my_courses            → courses assigned to this teacher
 * GET  ?action=list                  → assignments for this teacher + submission counts
 * GET  ?action=submissions&id=N      → all submissions for one assignment
 * POST action=create                 → create assignment
 * POST action=delete                 → delete assignment (owner only)
 * POST action=grade                  → save score + comment on a submission
 * POST action=submit                 → student submits
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');

$role      = $_SESSION['role'] ?? '';
$uid       = (int)($_SESSION['user_id'] ?? 0);
$isTeacher = $role === 'teacher';
$isStudent = $role === 'student';
$isAdmin   = $role === 'admin';

if (!$uid || (!$isTeacher && !$isStudent && !$isAdmin)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

// ── Read request body ONCE ───────────────────────────────────────────────────
$bodyData = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = trim($_GET['action'] ?? $_POST['action'] ?? $bodyData['action'] ?? 'list');

// CSRF verification uses the app-standard csrf.php (checks X-Request-Source / Origin / Referer)
// Called only on mutating POST actions below.

// ── Schema migrations ────────────────────────────────────────────────────────
function migrateSchema(PDO $pdo): void {
    // assignments table should already exist (created by dashboard-student.php)
    // Add columns that the teacher workflow needs but weren't in the original schema
    $assignmentMigrations = [
        "ALTER TABLE assignments ADD COLUMN teacher_id  INT UNSIGNED DEFAULT NULL",
        "ALTER TABLE assignments ADD COLUMN comment     TEXT DEFAULT NULL",  // generic fallback
        // title / description / subject columns already exist as _fr/_ar variants — no migration needed
    ];
    foreach ($assignmentMigrations as $sql) {
        try { $pdo->exec($sql); } catch(Throwable $e) {}
    }

    // assignment_submissions: add teacher feedback columns
    $submissionMigrations = [
        "ALTER TABLE assignment_submissions ADD COLUMN comment         TEXT DEFAULT NULL",
        "ALTER TABLE assignment_submissions ADD COLUMN score           TINYINT UNSIGNED DEFAULT NULL",
        "ALTER TABLE assignment_submissions ADD COLUMN teacher_comment TEXT DEFAULT NULL",
        "ALTER TABLE assignment_submissions ADD COLUMN graded_at       TIMESTAMP NULL DEFAULT NULL",
    ];
    foreach ($submissionMigrations as $sql) {
        try { $pdo->exec($sql); } catch(Throwable $e) {}
    }

    // activity_log
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL,
            description TEXT NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user(user_id), INDEX idx_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(Throwable $e) {}

    // notifications
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(Throwable $e) {}
}

function logActivity(PDO $pdo, int $userId, string $type, string $desc): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare("INSERT INTO activity_log (user_id,type,description,ip) VALUES (?,?,?,?)")
            ->execute([$userId, $type, $desc, $ip]);
    } catch(Throwable $e) {}
}

// Helper: get display title from a row (prefer fr, fallback ar)
function assignTitle(array $row, string $lang = 'fr'): string {
    if ($lang === 'ar') return $row['title_ar'] ?: $row['title_fr'] ?: '';
    return $row['title_fr'] ?: $row['title_ar'] ?: '';
}

try {
    $pdo = db();
    if (empty($_SESSION['assignments_schema_ok'])) {
        migrateSchema($pdo);
        $_SESSION['assignments_schema_ok'] = true;
    }

    /* ══════════════════════════════════════
       GET my_courses — teacher's classes
    ══════════════════════════════════════ */
    if ($action === 'my_courses') {
        if (!$isTeacher) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        $stmt = $pdo->prepare("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level, c.students_count
            FROM   teacher_courses tc
            JOIN   courses c ON c.id = tc.course_id
            WHERE  tc.teacher_id = ?
            ORDER  BY c.group_name_fr
        ");
        $stmt->execute([$uid]);
        echo json_encode(['ok'=>true,'courses'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       GET list
    ══════════════════════════════════════ */
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($isStudent) {
            // Student sees assignments for their enrolled courses
            $stmt = $pdo->prepare("
                SELECT a.id, a.title_fr, a.title_ar,
                       a.description_fr, a.description_ar,
                       a.subject_fr, a.subject_ar, a.due_date, a.course_id,
                       COALESCE(s.status,'pending') AS sub_status,
                       s.comment AS my_comment, s.score, s.teacher_comment,
                       s.submitted_at, s.graded_at
                FROM   assignments a
                JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
                LEFT JOIN assignment_submissions s
                       ON s.assignment_id = a.id AND s.student_id = ?
                ORDER  BY a.due_date ASC, a.id DESC
            ");
            $stmt->execute([$uid, $uid]);
            echo json_encode(['ok'=>true,'assignments'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Teacher: assignments for courses they teach
        $stmt = $pdo->prepare("
            SELECT a.id, a.title_fr, a.title_ar,
                   a.description_fr, a.description_ar,
                   a.subject_fr, a.subject_ar, a.due_date, a.created_at,
                   a.teacher_id, a.course_id,
                   c.group_name_fr, c.group_name_ar,
                   COUNT(DISTINCT s.id)          AS submitted_count,
                   COUNT(DISTINCT sc.student_id) AS total_students,
                   SUM(s.score IS NOT NULL)       AS graded_count
            FROM   assignments a
            LEFT JOIN courses c          ON c.id = a.course_id
            LEFT JOIN assignment_submissions s ON s.assignment_id = a.id
            LEFT JOIN student_courses sc ON sc.course_id = a.course_id
            WHERE  a.teacher_id = ?
            GROUP  BY a.id
            ORDER  BY a.created_at DESC, a.id DESC
        ");
        $stmt->execute([$uid]);
        echo json_encode(['ok'=>true,'assignments'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       GET submissions
    ══════════════════════════════════════ */
    if ($action === 'submissions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$isTeacher && !$isAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        $aId = (int)($_GET['id'] ?? 0);
        if (!$aId) { echo json_encode(['ok'=>false,'error'=>'Missing id']); exit; }

        if ($isTeacher) {
            $chk = $pdo->prepare("SELECT id FROM assignments WHERE id=? AND teacher_id=?");
            $chk->execute([$aId, $uid]);
            if (!$chk->fetch()) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not your assignment']); exit; }
        }

        $meta = $pdo->prepare("
            SELECT a.*, c.group_name_fr, c.group_name_ar
            FROM assignments a LEFT JOIN courses c ON c.id=a.course_id
            WHERE a.id=?
        ");
        $meta->execute([$aId]);
        $assignment = $meta->fetch();

        $stmt = $pdo->prepare("
            SELECT s.id, s.student_id, s.comment, s.score, s.teacher_comment,
                   s.submitted_at, s.graded_at, s.status,
                   u.full_name AS student_name, u.username
            FROM assignment_submissions s
            JOIN users u ON u.id = s.student_id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->execute([$aId]);
        $submissions = $stmt->fetchAll();

        foreach ($submissions as &$sub) {
            $parts = preg_split('/\s+/', trim($sub['student_name'] ?: $sub['username']));
            $init = '';
            foreach (array_slice($parts,0,2) as $p) $init .= mb_strtoupper(mb_substr($p,0,1));
            $sub['initials'] = $init ?: '?';
        } unset($sub);

        echo json_encode(['ok'=>true,'assignment'=>$assignment,'submissions'=>$submissions], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       POST create
    ══════════════════════════════════════ */
    if ($action === 'create') {
        if (!$isTeacher) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        csrf_verify();

        $titleFr  = trim($bodyData['title']       ?? '');  // frontend sends 'title' → stored as title_fr
        $titleAr  = trim($bodyData['title_ar']    ?? $titleFr);
        $descFr   = trim($bodyData['description'] ?? '');
        $descAr   = trim($bodyData['description_ar'] ?? $descFr);
        $subjectFr= trim($bodyData['subject']     ?? '');
        $subjectAr= trim($bodyData['subject_ar']  ?? $subjectFr);
        $due      = trim($bodyData['due_date']    ?? '');
        $courseId = (int)($bodyData['course_id']  ?? 0) ?: null;

        if (!$titleFr) { echo json_encode(['ok'=>false,'error'=>'Titre requis']); exit; }
        if ($due && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = null;

        if ($courseId) {
            $chk = $pdo->prepare("SELECT course_id FROM teacher_courses WHERE teacher_id=? AND course_id=?");
            $chk->execute([$uid, $courseId]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'Cours non autorisé.']); exit; }
        }

        $pdo->prepare("
            INSERT INTO assignments
                (teacher_id, course_id, title_fr, title_ar,
                 description_fr, description_ar, subject_fr, subject_ar, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $uid, $courseId,
            $titleFr, $titleAr,
            $descFr ?: null, $descAr ?: null,
            $subjectFr ?: null, $subjectAr ?: null,
            $due ?: null
        ]);

        logActivity($pdo, $uid, 'assignment_created', "Nouveau devoir: «{$titleFr}»");
        echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       POST delete
    ══════════════════════════════════════ */
    if ($action === 'delete') {
        if (!$isTeacher) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        csrf_verify();

        $aId = (int)($bodyData['id'] ?? 0);
        if (!$aId) { echo json_encode(['ok'=>false,'error'=>'Missing id']); exit; }

        $chk = $pdo->prepare("SELECT id FROM assignments WHERE id=? AND teacher_id=?");
        $chk->execute([$aId, $uid]);
        if (!$chk->fetch()) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not your assignment']); exit; }

        $pdo->prepare("DELETE FROM assignment_submissions WHERE assignment_id=?")->execute([$aId]);
        $pdo->prepare("DELETE FROM assignments WHERE id=?")->execute([$aId]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       POST grade
    ══════════════════════════════════════ */
    if ($action === 'grade') {
        if (!$isTeacher) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        csrf_verify();

        $subId   = (int)($bodyData['submission_id']  ?? 0);
        $comment = trim($bodyData['teacher_comment'] ?? '');
        $score   = isset($bodyData['score']) && $bodyData['score'] !== '' ? (int)$bodyData['score'] : null;

        if (!$subId) { echo json_encode(['ok'=>false,'error'=>'Missing submission_id']); exit; }
        if ($score !== null && ($score < 0 || $score > 100)) { echo json_encode(['ok'=>false,'error'=>'Score entre 0 et 100']); exit; }

        // Verify ownership
        $chk = $pdo->prepare("
            SELECT s.id, s.student_id, a.title_fr, u.full_name
            FROM assignment_submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN users u ON u.id = s.student_id
            WHERE s.id=? AND a.teacher_id=?
        ");
        $chk->execute([$subId, $uid]);
        $sub = $chk->fetch();
        if (!$sub) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not your submission']); exit; }

        $pdo->prepare("
            UPDATE assignment_submissions
            SET teacher_comment=?, score=?, graded_at=NOW(), status='submitted'
            WHERE id=?
        ")->execute([$comment ?: null, $score, $subId]);

        // Notify student
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL, type VARCHAR(60) NOT NULL,
                message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user(user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $msg = "✅ Devoir «{$sub['title_fr']}» corrigé" . ($score !== null ? " — {$score}/100" : '');
            $pdo->prepare("INSERT INTO notifications (user_id,type,message) VALUES (?,?,?)")
                ->execute([$sub['student_id'], 'assignment_graded', $msg]);
        } catch(Throwable $e) {}

        logActivity($pdo, $uid, 'submission_graded', "Correction: {$sub['full_name']}" . ($score !== null ? " — {$score}/100" : ''));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       POST submit (student)
    ══════════════════════════════════════ */
    if ($action === 'submit') {
        if (!$isStudent) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        csrf_verify();

        $aId     = (int)($bodyData['assignment_id'] ?? 0);
        $comment = trim($bodyData['comment'] ?? '');
        if (!$aId) { echo json_encode(['ok'=>false,'error'=>'Missing assignment_id']); exit; }

        $chk = $pdo->prepare("SELECT id, title_fr, teacher_id FROM assignments WHERE id=?");
        $chk->execute([$aId]);
        $assign = $chk->fetch();
        if (!$assign) { echo json_encode(['ok'=>false,'error'=>'Assignment not found']); exit; }

        $pdo->prepare("
            INSERT INTO assignment_submissions (assignment_id, student_id, comment, status, submitted_at)
            VALUES (?, ?, ?, 'submitted', NOW())
            ON DUPLICATE KEY UPDATE comment=VALUES(comment), status='submitted', submitted_at=NOW()
        ")->execute([$aId, $uid, $comment ?: null]);

        // Notify teacher
        try {
            $name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Étudiant';
            $pdo->prepare("INSERT INTO notifications (user_id,type,message) VALUES (?,?,?)")
                ->execute([$assign['teacher_id'], 'assignment_submitted', "📝 {$name} a rendu «{$assign['title_fr']}»"]);
        } catch(Throwable $e) {}

        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       GET activity — teacher's recent actions
    ══════════════════════════════════════ */
    if ($action === 'activity' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$isTeacher) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        $stmt = $pdo->prepare("
            SELECT id, type, description, created_at
            FROM   activity_log
            WHERE  user_id = ?
            ORDER  BY created_at DESC
            LIMIT  8
        ");
        $stmt->execute([$uid]);
        echo json_encode(['ok'=>true,'activity'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       GET notifications
    ══════════════════════════════════════ */
    if ($action === 'notifications' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, type, message, is_read, created_at
            FROM   notifications
            WHERE  user_id = ?
            ORDER  BY created_at DESC
            LIMIT  20
        ");
        $stmt->execute([$uid]);
        $notifs = $stmt->fetchAll();
        $unread = (int) array_sum(array_map(fn($n) => $n['is_read'] ? 0 : 1, $notifs));
        echo json_encode(['ok'=>true,'notifications'=>$notifs,'unread_count'=>$unread], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       POST mark_notifications_read
    ══════════════════════════════════════ */
    if ($action === 'mark_notifications_read') {
        try {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        } catch(Throwable $e) {}
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ══════════════════════════════════════
       GET grades_overview — graded submissions for this teacher
    ══════════════════════════════════════ */
    if ($action === 'grades_overview' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$isTeacher && !$isAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }
        $stmt = $pdo->prepare("
            SELECT s.id, s.score, s.graded_at,
                   u.full_name AS student_name, u.username,
                   a.title_fr, a.title_ar, a.course_id,
                   c.group_name_fr, c.group_name_ar
            FROM   assignment_submissions s
            JOIN   assignments a ON a.id = s.assignment_id
            JOIN   users u ON u.id = s.student_id
            LEFT JOIN courses c ON c.id = a.course_id
            WHERE  a.teacher_id = ? AND s.score IS NOT NULL
            ORDER  BY s.graded_at DESC
            LIMIT  100
        ");
        $stmt->execute([$uid]);
        echo json_encode(['ok'=>true,'grades'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
