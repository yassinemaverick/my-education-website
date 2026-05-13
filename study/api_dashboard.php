<?php
/**
 * api_dashboard.php — Student dashboard data endpoint
 * ─────────────────────────────────────────────────────
 * Auth : student session required
 * GET  ?action=overview     → course info + attendance rate + stats
 * GET  ?action=assignments  → assignment list for this student
 *
 * Returns live data where tables exist; graceful stubs elsewhere.
 */

require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$studentId = (int) $_SESSION['user_id'];
$action    = trim($_GET['action'] ?? 'overview');

try {
    $pdo = db();

    if ($action === 'overview') {
        // ── Course info ──────────────────────────────────────────────────────
        $course = null;
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.group_name_fr, c.group_name_ar,
                       c.subject_fr, c.subject_ar, c.level,
                       c.students_count, c.schedule_json,
                       u.full_name AS teacher_name
                FROM   student_courses sc
                JOIN   courses c         ON c.id = sc.course_id
                LEFT   JOIN teacher_courses tc ON tc.course_id = c.id
                LEFT   JOIN users u      ON u.id = tc.teacher_id
                WHERE  sc.student_id = ?
                LIMIT  1
            ");
            $stmt->execute([$studentId]);
            $course = $stmt->fetch() ?: null;
        } catch (Throwable $e) { /* student_courses table may not exist yet */ }

        // ── Attendance ────────────────────────────────────────────────────────
        ensureAttendanceTable();
        $attStmt = $pdo->prepare("
            SELECT COUNT(*) AS total, SUM(present) AS present
            FROM   attendance
            WHERE  student_id = ?
        ");
        $attStmt->execute([$studentId]);
        $att     = $attStmt->fetch();
        $total   = (int)($att['total']   ?? 0);
        $present = (int)($att['present'] ?? 0);
        $attRate = $total > 0 ? round($present / $total * 100) : null;

        echo json_encode([
            'ok'       => true,
            'course'   => $course,
            'att_total'   => $total,
            'att_present' => $present,
            'att_rate'    => $attRate,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'assignments') {
        // Return assignments if table exists, else empty array
        $items = [];
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.title_fr, a.title_ar,
                       a.description_fr, a.description_ar,
                       a.due_date, a.subject_fr, a.subject_ar,
                       COALESCE(sub.status, 'pending') AS status
                FROM   assignments a
                LEFT   JOIN assignment_submissions sub
                         ON sub.assignment_id = a.id AND sub.student_id = ?
                JOIN   teacher_courses tc ON tc.course_id = a.course_id
                JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
                ORDER  BY a.due_date ASC
            ");
            $stmt->execute([$studentId, $studentId]);
            $items = $stmt->fetchAll();
        } catch (Throwable $e) { /* assignments table not yet created */ }

        echo json_encode(['ok' => true, 'assignments' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
