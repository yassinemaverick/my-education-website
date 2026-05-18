<?php
/**
 * assign_courses.php — Admin endpoint : assigner des cours aux professeurs
 * ─────────────────────────────────────────────────────────────────────────
 * Auth   : session active avec role === 'admin'  |  Réponses JSON
 *
 * GET  ?action=list_courses                   → cours + prof assigné
 * GET  ?action=list_teachers                  → liste des profs
 * GET  ?action=teacher_courses&teacher_id=X   → cours du prof X
 * POST ?action=assign      { teacher_id, course_id }
 * POST ?action=unassign    { teacher_id, course_id }
 * POST ?action=bulk_assign { teacher_id, course_ids:[...] }
 *
 * DDL (MySQL/MariaDB)
 * ────────────────────
 *   CREATE TABLE IF NOT EXISTS courses (
 *     id             INT AUTO_INCREMENT PRIMARY KEY,
 *     group_name_fr  VARCHAR(120) NOT NULL,
 *     group_name_ar  VARCHAR(120) NOT NULL,
 *     subject_fr     VARCHAR(100) NOT NULL,
 *     subject_ar     VARCHAR(100) NOT NULL,
 *     level          VARCHAR(20) NOT NULL DEFAULT 'A1',
 *     students_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 *     schedule_json  TEXT COMMENT 'JSON [{day_fr,day_ar,time,room}]',
 *     created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 *   CREATE TABLE IF NOT EXISTS teacher_courses (
 *     id          INT AUTO_INCREMENT PRIMARY KEY,
 *     teacher_id  INT NOT NULL,
 *     course_id   INT NOT NULL,
 *     assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     assigned_by INT NOT NULL,
 *     UNIQUE KEY uq_tc (teacher_id, course_id),
 *     FOREIGN KEY (teacher_id)  REFERENCES users(id)   ON DELETE CASCADE,
 *     FOREIGN KEY (course_id)   REFERENCES courses(id) ON DELETE CASCADE,
 *     FOREIGN KEY (assigned_by) REFERENCES users(id)
 *   );
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=UTF-8');

ob_start();

set_error_handler(function(int $errno, string $errstr) {
    throw new ErrorException($errstr, $errno);
});

function json_ok(array $data): void {
    ob_end_clean();
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $fr, int $http = 400): void {
    ob_end_clean();
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => $fr], JSON_UNESCAPED_UNICODE);
    exit;
}
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $h = defined('DB_HOST') ? DB_HOST : 'localhost';
    $n = defined('DB_NAME') ? DB_NAME : 'upskill';
    $u = defined('DB_USER') ? DB_USER : 'root';
    $p = defined('DB_PASS') ? DB_PASS : '';
    try {
        $pdo = new PDO("mysql:host={$h};dbname={$n};charset=utf8mb4", $u, $p, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        json_err('Erreur de connexion DB.', 500);
    }
    return $pdo;
}

/* Auth guard */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    json_err('Accès refusé. Réservé aux administrateurs.', 403);
}
csrf_verify();
$admin_id = (int)$_SESSION['user_id'];
$action   = $_GET['action'] ?? '';

try {
    $db = get_db();
} catch (Throwable $e) {
    json_err('Erreur de connexion DB: ' . $e->getMessage(), 500);
}

try {
switch ($action) {

    case 'list_courses': {
        $stmt = $db->query("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level, c.students_count,
                   c.schedule_json,
                   u.id        AS teacher_id,
                   u.full_name AS teacher_name,
                   u.username  AS teacher_username,
                   tc.assigned_at
            FROM courses c
            LEFT JOIN teacher_courses tc ON tc.course_id = c.id
                AND tc.assigned_at = (
                    SELECT MAX(assigned_at) FROM teacher_courses WHERE course_id = c.id
                )
            LEFT JOIN users u ON u.id = tc.teacher_id
            GROUP BY c.id
            ORDER BY c.id");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['schedule'] = json_decode($r['schedule_json'] ?? '[]', true) ?: [];
            unset($r['schedule_json']);
        }
        json_ok(['courses' => $rows]);
    }

    case 'list_teachers': {
        $stmt = $db->query(
            "SELECT id, full_name, username FROM users WHERE role='teacher' ORDER BY full_name");
        json_ok(['teachers' => $stmt->fetchAll()]);
    }

    case 'teacher_courses': {
        $tid = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
        if (!$tid) json_err('teacher_id invalide.');
        $stmt = $db->prepare("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level, c.students_count,
                   c.schedule_json, tc.assigned_at
            FROM teacher_courses tc
            JOIN courses c ON c.id = tc.course_id
            WHERE tc.teacher_id = :tid ORDER BY c.id");
        $stmt->execute([':tid' => $tid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['schedule'] = json_decode($r['schedule_json'] ?? '[]', true) ?: [];
            unset($r['schedule_json']);
        }
        json_ok(['teacher_id' => $tid, 'courses' => $rows]);
    }

    case 'assign': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $tid  = filter_var($body['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        $cid  = filter_var($body['course_id']  ?? null, FILTER_VALIDATE_INT);
        if (!$tid || !$cid)
            json_err('teacher_id et course_id sont requis.');
        $c = $db->prepare("SELECT id FROM users WHERE id=:id AND role='teacher'");
        $c->execute([':id' => $tid]);
        if (!$c->fetch()) json_err('Professeur introuvable.', 404);
        $c2 = $db->prepare("SELECT id FROM courses WHERE id=:id");
        $c2->execute([':id' => $cid]);
        if (!$c2->fetch()) json_err('Cours introuvable.', 404);
        $c3 = $db->prepare("SELECT teacher_id FROM teacher_courses WHERE course_id=:cid");
        $c3->execute([':cid' => $cid]);
        $ex = $c3->fetch();
        if ($ex && (int)$ex['teacher_id'] !== $tid) {
            // Remove existing teacher assignment so the new one replaces it
            $db->prepare("DELETE FROM teacher_courses WHERE course_id=:cid")
               ->execute([':cid' => $cid]);
        }
        $ins = $db->prepare("INSERT INTO teacher_courses(teacher_id,course_id,assigned_by)
            VALUES(:tid,:cid,:adm) ON DUPLICATE KEY UPDATE assigned_by=VALUES(assigned_by),assigned_at=CURRENT_TIMESTAMP");
        $ins->execute([':tid'=>$tid,':cid'=>$cid,':adm'=>$admin_id]);
        json_ok(['message'=>['fr'=>'Cours assigné avec succès.'],
                 'teacher_id'=>$tid,'course_id'=>$cid]);
    }

    case 'unassign': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $tid  = filter_var($body['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        $cid  = filter_var($body['course_id']  ?? null, FILTER_VALIDATE_INT);
        if (!$tid || !$cid)
            json_err('teacher_id et course_id sont requis.');
        $del = $db->prepare("DELETE FROM teacher_courses WHERE teacher_id=:tid AND course_id=:cid");
        $del->execute([':tid'=>$tid,':cid'=>$cid]);
        if ($del->rowCount() === 0)
            json_err('Aucune assignation trouvée.', 404);
        json_ok(['message'=>['fr'=>'Cours retiré avec succès.'],
                 'teacher_id'=>$tid,'course_id'=>$cid]);
    }

    case 'bulk_assign': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $tid  = filter_var($body['teacher_id'] ?? null, FILTER_VALIDATE_INT);
        $cids = $body['course_ids'] ?? [];
        if (!$tid || !is_array($cids) || empty($cids))
            json_err('teacher_id et course_ids[] sont requis.');
        $cids = array_values(array_filter(array_map('intval', $cids)));
        if (empty($cids)) json_err('Aucun course_id valide.');
        $c = $db->prepare("SELECT id FROM users WHERE id=:id AND role='teacher'");
        $c->execute([':id' => $tid]);
        if (!$c->fetch()) json_err('Professeur introuvable.', 404);
        $assigned = []; $skipped = [];
        $db->beginTransaction();
        try {
            $ins  = $db->prepare("INSERT INTO teacher_courses(teacher_id,course_id,assigned_by)
                VALUES(:tid,:cid,:adm) ON DUPLICATE KEY UPDATE assigned_by=VALUES(assigned_by),assigned_at=CURRENT_TIMESTAMP");
            $chkC = $db->prepare("SELECT id FROM courses WHERE id=:id");
            $chkX = $db->prepare("SELECT teacher_id FROM teacher_courses WHERE course_id=:cid");
            foreach ($cids as $cid) {
                $chkC->execute([':id' => $cid]);
                if (!$chkC->fetch()) { $skipped[] = $cid; continue; }
                // Remove any existing teacher for this course so the new one replaces it
                $chkX->execute([':cid' => $cid]);
                $ex = $chkX->fetch();
                if ($ex && (int)$ex['teacher_id'] !== $tid) {
                    $db->prepare("DELETE FROM teacher_courses WHERE course_id=:cid")
                       ->execute([':cid' => $cid]);
                }
                $ins->execute([':tid'=>$tid,':cid'=>$cid,':adm'=>$admin_id]);
                $assigned[] = $cid;
            }
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            json_err("Erreur lors de l'assignation en lot.", 500);
        }
        json_ok(['message'=>count($assigned).' cours assigné(s).',
                 'teacher_id'=>$tid,'assigned'=>$assigned,'skipped'=>$skipped]);
    }

    case 'list_students': {
        $stmt = $db->query(
            "SELECT id, full_name, username FROM users WHERE role='student' ORDER BY full_name");
        json_ok(['students' => $stmt->fetchAll()]);
    }

    case 'student_courses': {
        $sid = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        if (!$sid) json_err('student_id invalide.');
        $db->exec("CREATE TABLE IF NOT EXISTS student_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL, course_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sc (student_id, course_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $db->prepare("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level, c.students_count,
                   c.schedule_json, sc.enrolled_at
            FROM student_courses sc
            JOIN courses c ON c.id = sc.course_id
            WHERE sc.student_id = :sid ORDER BY c.id");
        $stmt->execute([':sid' => $sid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['schedule'] = json_decode($r['schedule_json'] ?? '[]', true) ?: [];
            unset($r['schedule_json']);
        }
        json_ok(['student_id' => $sid, 'courses' => $rows]);
    }

    case 'enroll_student': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $sid  = filter_var($body['student_id'] ?? null, FILTER_VALIDATE_INT);
        $cid  = filter_var($body['course_id']  ?? null, FILTER_VALIDATE_INT);
        if (!$sid || !$cid)
            json_err('student_id et course_id sont requis.');
        $c = $db->prepare("SELECT id FROM users WHERE id=:id AND role='student'");
        $c->execute([':id' => $sid]);
        if (!$c->fetch()) json_err('Étudiant introuvable.', 404);
        $c2 = $db->prepare("SELECT id FROM courses WHERE id=:id");
        $c2->execute([':id' => $cid]);
        if (!$c2->fetch()) json_err('Cours introuvable.', 404);
        $db->exec("CREATE TABLE IF NOT EXISTS student_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL, course_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sc (student_id, course_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->prepare("INSERT INTO student_courses (student_id, course_id)
            VALUES(:sid,:cid) ON DUPLICATE KEY UPDATE enrolled_at=CURRENT_TIMESTAMP")
           ->execute([':sid' => $sid, ':cid' => $cid]);
        json_ok(['message' => ['fr' => 'Étudiant inscrit avec succès.', 'ar' => 'تم تسجيل الطالب بنجاح.'],
                 'student_id' => $sid, 'course_id' => $cid]);
    }

    case 'unenroll_student': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $sid  = filter_var($body['student_id'] ?? null, FILTER_VALIDATE_INT);
        $cid  = filter_var($body['course_id']  ?? null, FILTER_VALIDATE_INT);
        if (!$sid || !$cid)
            json_err('student_id et course_id sont requis.');
        $del = $db->prepare("DELETE FROM student_courses WHERE student_id=:sid AND course_id=:cid");
        $del->execute([':sid' => $sid, ':cid' => $cid]);
        if ($del->rowCount() === 0)
            json_err('Aucune inscription trouvée.', 404);
        json_ok(['message' => ['fr' => 'Inscription retirée.', 'ar' => 'تم إلغاء التسجيل.'],
                 'student_id' => $sid, 'course_id' => $cid]);
    }

    case 'update_schedule': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 405);
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $cid     = filter_var($body['course_id'] ?? null, FILTER_VALIDATE_INT);
        $slots   = $body['schedule'] ?? [];
        if (!$cid)
            json_err('course_id invalide.');
        if (!is_array($slots))
            json_err('schedule doit être un tableau.');

        // Validate and sanitise each slot
        $clean = [];
        foreach ($slots as $s) {
            $dayFr = trim($s['day_fr'] ?? '');
            $time  = trim($s['time']   ?? '');
            $room  = trim($s['room']   ?? '');
            if ($dayFr === '') continue; // skip empty rows
            if (strlen($dayFr) > 40 || strlen($time) > 20 || strlen($room) > 80)
                json_err('Données de session trop longues.');
            $clean[] = ['day_fr' => $dayFr, 'time' => $time, 'room' => $room];
        }

        $json = count($clean) > 0 ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $db->prepare("UPDATE courses SET schedule_json = ? WHERE id = ?");
        $stmt->execute([$json, $cid]);
        if ($stmt->rowCount() === 0) {
            // rowCount can be 0 if value didn't change — verify course exists
            $chk = $db->prepare("SELECT id FROM courses WHERE id = ?");
            $chk->execute([$cid]);
            if (!$chk->fetch()) json_err('Cours introuvable.', 404);
        }
        json_ok(['course_id' => $cid, 'slots' => count($clean)]);
    }

    default:
        json_err("Action inconnue: \"{$action}\". Disponibles: list_courses, list_teachers, teacher_courses, assign, unassign, bulk_assign.",
                 "إجراء غير معروف: \"{$action}\".", 400);
}
} catch (Throwable $e) {
    json_err('Erreur serveur: ' . $e->getMessage(), 500);
}
