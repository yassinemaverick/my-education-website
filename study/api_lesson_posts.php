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
if ($_SERVER['REQUEST_METHOD'] === 'GET') { header('Cache-Control: private, max-age=60'); }
else { header('Cache-Control: no-store'); }

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
        group_id     INT UNSIGNED NULL DEFAULT NULL,
        title        VARCHAR(200) NOT NULL,
        session_date DATE NOT NULL,
        link         VARCHAR(500) DEFAULT NULL,
        notes        TEXT DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lp_course (course_id),
        INDEX idx_lp_group (group_id),
        INDEX idx_lp_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE lesson_posts ADD COLUMN group_id INT UNSIGNED NULL DEFAULT NULL"); } catch(Throwable $e) {}
    try { $pdo->exec("ALTER TABLE lesson_posts ADD INDEX idx_lp_group (group_id)"); } catch(Throwable $e) {}

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($action === 'list' && $isTeacher) {
            $stmt = $pdo->prepare("
                SELECT lp.id, lp.title, lp.session_date, lp.link, lp.notes, lp.created_at,
                       lp.course_id, lp.group_id,
                       c.group_name_fr, c.group_name_ar,
                       cg.type_key, cg.level_number, cg.group_letter
                FROM   lesson_posts lp
                LEFT JOIN courses c  ON c.id  = lp.course_id
                LEFT JOIN class_groups cg ON cg.id = lp.group_id
                WHERE  lp.teacher_id = ?
                ORDER  BY lp.session_date DESC, lp.created_at DESC
                LIMIT  100
            ");
            $stmt->execute([$uid]);
            $posts = $stmt->fetchAll();
            echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'feed' && $isStudent) {
            // Fetch posts linked via new group system OR old course system
            $stmt = $pdo->prepare("
                SELECT lp.id, lp.title, lp.session_date, lp.link, lp.notes, lp.created_at,
                       lp.course_id, lp.group_id,
                       c.group_name_fr, c.group_name_ar,
                       cg.type_key, cg.level_number, cg.group_letter
                FROM   lesson_posts lp
                LEFT JOIN courses c      ON c.id  = lp.course_id
                LEFT JOIN class_groups cg ON cg.id = lp.group_id
                WHERE (
                    (lp.group_id IS NOT NULL AND EXISTS (
                        SELECT 1 FROM class_group_members cgm
                        WHERE cgm.group_id = lp.group_id AND cgm.user_id = ?
                    ))
                    OR
                    (lp.group_id IS NULL AND lp.course_id IS NOT NULL AND EXISTS (
                        SELECT 1 FROM student_courses sc
                        WHERE sc.course_id = lp.course_id AND sc.student_id = ?
                    ))
                )
                ORDER  BY lp.session_date DESC, lp.created_at DESC
                LIMIT  50
            ");
            $stmt->execute([$uid, $uid]);
            echo json_encode(['ok' => true, 'posts' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);

        } else {
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        }

    } else {
        csrf_verify();

        if ($action === 'create' && $isTeacher) {
            $groupId  = isset($bodyData['group_id'])  ? (int)$bodyData['group_id']  : 0;
            $courseId = isset($bodyData['course_id']) ? (int)$bodyData['course_id'] : 0;
            $title    = trim($bodyData['title'] ?? '');
            $date     = trim($bodyData['session_date'] ?? '');
            $link     = trim($bodyData['link'] ?? '') ?: null;
            $notes    = trim($bodyData['notes'] ?? '') ?: null;

            if ((!$groupId && !$courseId) || $title === '' || $date === '') {
                echo json_encode(['ok' => false, 'error' => 'Champs requis manquants']); exit;
            }
            if (mb_strlen($title) > 200) {
                echo json_encode(['ok' => false, 'error' => 'Titre trop long']); exit;
            }
            if ($link && mb_strlen($link) > 500) {
                echo json_encode(['ok' => false, 'error' => 'Lien trop long']); exit;
            }
            if ($link && !preg_match('#^https?://#i', $link)) {
                echo json_encode(['ok' => false, 'error' => 'Le lien doit commencer par http:// ou https://']); exit;
            }
            if (strtotime($date) > strtotime('+1 year')) {
                echo json_encode(['ok' => false, 'error' => 'Date trop éloignée dans le futur']); exit;
            }

            if ($groupId) {
                // New system: verify teacher is a member of this class group
                $check = $pdo->prepare("SELECT g.id, g.course_id FROM class_groups g JOIN class_group_members m ON m.group_id = g.id WHERE g.id = ? AND m.user_id = ?");
                $check->execute([$groupId, $uid]);
                $grp = $check->fetch();
                if (!$grp) { echo json_encode(['ok' => false, 'error' => 'Accès refusé']); exit; }
                $courseId = $grp['course_id'] ? (int)$grp['course_id'] : 0;
            } else {
                // Legacy system: verify teacher is in teacher_courses
                $check = $pdo->prepare("SELECT id FROM teacher_courses WHERE teacher_id = ? AND course_id = ?");
                $check->execute([$uid, $courseId]);
                if (!$check->fetch()) { echo json_encode(['ok' => false, 'error' => 'Accès refusé']); exit; }
                $groupId = null;
            }

            $stmt = $pdo->prepare("
                INSERT INTO lesson_posts (teacher_id, course_id, group_id, title, session_date, link, notes)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([$uid, $courseId ?: 0, $groupId ?: null, $title, $date, $link, $notes]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'update' && $isTeacher) {
            $id    = (int)($bodyData['id'] ?? 0);
            $title = trim($bodyData['title'] ?? '');
            $date  = trim($bodyData['session_date'] ?? '');
            $link  = trim($bodyData['link'] ?? '') ?: null;
            $notes = trim($bodyData['notes'] ?? '') ?: null;

            if (!$id || $title === '' || $date === '') {
                echo json_encode(['ok' => false, 'error' => 'Champs requis manquants']); exit;
            }
            if (mb_strlen($title) > 200) {
                echo json_encode(['ok' => false, 'error' => 'Titre trop long']); exit;
            }
            if ($link && !preg_match('#^https?://#i', $link)) {
                echo json_encode(['ok' => false, 'error' => 'Le lien doit commencer par http:// ou https://']); exit;
            }
            // Upper-bound: no dates more than 1 year in the future
            if (strtotime($date) > strtotime('+1 year')) {
                echo json_encode(['ok' => false, 'error' => 'Date trop éloignée dans le futur']); exit;
            }

            $stmt = $pdo->prepare("UPDATE lesson_posts SET title=?, session_date=?, link=?, notes=? WHERE id=? AND teacher_id=?");
            $stmt->execute([$title, $date, $link, $notes, $id, $uid]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

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
