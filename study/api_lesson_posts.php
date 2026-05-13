<?php
/**
 * api_lesson_posts.php — Lesson posts (post-session notes from teacher)
 *
 * GET  ?action=list  → teacher: their own posts with course name
 * GET  ?action=feed  → student: posts from enrolled courses
 * POST action=create → teacher creates a post
 * POST action=delete → teacher deletes own post
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');

$role      = $_SESSION['role'] ?? '';
$uid       = (int)($_SESSION['user_id'] ?? 0);
$isTeacher = $role === 'teacher';
$isStudent = $role === 'student';

if (!$uid || (!$isTeacher && !$isStudent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$bodyData = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = trim($_GET['action'] ?? $bodyData['action'] ?? '');

try {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_posts (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id   INT UNSIGNED NOT NULL,
        course_id    INT UNSIGNED NOT NULL,
        title        VARCHAR(200) NOT NULL,
        session_date DATE NOT NULL,
        link         VARCHAR(500) DEFAULT NULL,
        notes        TEXT DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lp_course (course_id),
        INDEX idx_lp_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($action === 'list' && $isTeacher) {
            $stmt = $pdo->prepare("
                SELECT lp.id, lp.title, lp.session_date, lp.link, lp.notes, lp.created_at,
                       c.group_name_fr, c.group_name_ar, lp.course_id
                FROM   lesson_posts lp
                JOIN   courses c ON c.id = lp.course_id
                WHERE  lp.teacher_id = ?
                ORDER  BY lp.session_date DESC, lp.created_at DESC
                LIMIT  100
            ");
            $stmt->execute([$uid]);
            echo json_encode(['ok' => true, 'posts' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'feed' && $isStudent) {
            $stmt = $pdo->prepare("
                SELECT lp.id, lp.title, lp.session_date, lp.link, lp.notes, lp.created_at,
                       c.group_name_fr, c.group_name_ar, lp.course_id
                FROM   lesson_posts lp
                JOIN   courses c ON c.id = lp.course_id
                JOIN   student_courses sc ON sc.course_id = lp.course_id AND sc.student_id = ?
                ORDER  BY lp.session_date DESC, lp.created_at DESC
                LIMIT  50
            ");
            $stmt->execute([$uid]);
            echo json_encode(['ok' => true, 'posts' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);

        } else {
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        }

    } else {
        csrf_verify();

        if ($action === 'create' && $isTeacher) {
            $courseId = (int)($bodyData['course_id'] ?? 0);
            $title    = trim($bodyData['title'] ?? '');
            $date     = trim($bodyData['session_date'] ?? '');
            $link     = trim($bodyData['link'] ?? '') ?: null;
            $notes    = trim($bodyData['notes'] ?? '') ?: null;

            if (!$courseId || $title === '' || $date === '') {
                echo json_encode(['ok' => false, 'error' => 'Champs requis manquants']); exit;
            }
            if (mb_strlen($title) > 200) {
                echo json_encode(['ok' => false, 'error' => 'Titre trop long']); exit;
            }
            if ($link && mb_strlen($link) > 500) {
                echo json_encode(['ok' => false, 'error' => 'Lien trop long']); exit;
            }

            $check = $pdo->prepare("SELECT id FROM teacher_courses WHERE teacher_id = ? AND course_id = ?");
            $check->execute([$uid, $courseId]);
            if (!$check->fetch()) {
                echo json_encode(['ok' => false, 'error' => 'Accès refusé']); exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO lesson_posts (teacher_id, course_id, title, session_date, link, notes)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->execute([$uid, $courseId, $title, $date, $link, $notes]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'delete' && $isTeacher) {
            $id = (int)($bodyData['id'] ?? 0);
            if (!$id) { echo json_encode(['ok' => false, 'error' => 'ID manquant']); exit; }
            $stmt = $pdo->prepare("DELETE FROM lesson_posts WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$id, $uid]);
            echo json_encode(['ok' => true]);

        } else {
            echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
        }
    }

} catch (Throwable $e) {
    error_log('api_lesson_posts: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur']);
}
