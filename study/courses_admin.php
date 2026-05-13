<?php
/**
 * courses_admin.php — Admin endpoint : gérer les cours (CRUD)
 * ─────────────────────────────────────────────────────────────
 * Auth   : session active avec role === 'admin'  |  Réponses JSON
 *
 * GET  ?action=list          → tous les cours avec prof assigné
 * POST ?action=create        { group_name_fr, group_name_ar, subject_fr, subject_ar, level, students_count, schedule_json }
 * POST ?action=update        { id, group_name_fr, group_name_ar, subject_fr, subject_ar, level, students_count, schedule_json }
 * POST ?action=delete        { id }
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

// Catch any accidental output (warnings, notices) that would break JSON
ob_start();

// Convert PHP errors/warnings into catchable exceptions
set_error_handler(function(int $errno, string $errstr) {
    throw new ErrorException($errstr, $errno);
});

function json_ok(array $data): void {
    ob_end_clean();
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $fr, string $ar, int $http = 400): void {
    ob_end_clean();
    http_response_code($http);
    echo json_encode(['ok' => false, 'error' => ['fr' => $fr, 'ar' => $ar]], JSON_UNESCAPED_UNICODE);
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
        json_err('Erreur de connexion DB.', 'خطأ في الاتصال بقاعدة البيانات.', 500);
    }
    return $pdo;
}

/* Auth guard — admin only */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    json_err('Accès refusé. Réservé aux administrateurs.', 'الوصول مرفوض. للمسؤولين فقط.', 403);
}

csrf_verify();

$action = $_GET['action'] ?? '';

try {
    $db = get_db();
} catch (Throwable $e) {
    json_err('Erreur de connexion DB: ' . $e->getMessage(), 'خطأ في الاتصال بقاعدة البيانات.', 500);
}

try {
switch ($action) {

    case 'list': {
        $stmt = $db->query("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level,
                   c.students_count, c.schedule_json, c.created_at,
                   u.id        AS teacher_id,
                   u.full_name AS teacher_name,
                   u.username  AS teacher_username
            FROM courses c
            LEFT JOIN teacher_courses tc ON tc.course_id = c.id
                AND tc.assigned_at = (
                    SELECT MAX(assigned_at) FROM teacher_courses WHERE course_id = c.id
                )
            LEFT JOIN users u ON u.id = tc.teacher_id
            GROUP BY c.id
            ORDER BY c.id DESC
        ");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['schedule'] = json_decode($r['schedule_json'] ?? '[]', true) ?: [];
            unset($r['schedule_json']);
        }
        json_ok(['courses' => $rows]);
    }

    case 'create': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 'الطريقة غير مسموح بها.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $gfr  = trim($body['group_name_fr'] ?? '');
        $gar  = trim($body['group_name_ar'] ?? '');
        if (!$gfr && !$gar)
            json_err('Le nom du groupe (FR ou AR) est requis.', 'اسم المجموعة مطلوب.');
        $sfr  = trim($body['subject_fr']     ?? '');
        $sar  = trim($body['subject_ar']     ?? '');
        $lvl  = trim($body['level']          ?? 'A1');
        $sc   = max(0, (int)($body['students_count'] ?? 0));
        $sj   = json_encode($body['schedule'] ?? [], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("
            INSERT INTO courses (group_name_fr, group_name_ar, subject_fr, subject_ar, level, students_count, schedule_json)
            VALUES (:gfr,:gar,:sfr,:sar,:lvl,:sc,:sj)
        ");
        $stmt->execute([':gfr'=>$gfr,':gar'=>$gar,':sfr'=>$sfr,':sar'=>$sar,':lvl'=>$lvl,':sc'=>$sc,':sj'=>$sj]);
        $newId = (int)$db->lastInsertId();
        json_ok(['message'=>['fr'=>'Cours créé avec succès.','ar'=>'تم إنشاء الدرس بنجاح.'], 'id'=>$newId]);
    }

    case 'update': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 'الطريقة غير مسموح بها.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) json_err('ID de cours invalide.', 'معرف الدرس غير صالح.');
        $chk  = $db->prepare("SELECT id FROM courses WHERE id=:id");
        $chk->execute([':id'=>$id]);
        if (!$chk->fetch()) json_err('Cours introuvable.', 'الدرس غير موجود.', 404);
        $gfr  = trim($body['group_name_fr']  ?? '');
        $gar  = trim($body['group_name_ar']  ?? '');
        if (!$gfr && !$gar)
            json_err('Le nom du groupe (FR ou AR) est requis.', 'اسم المجموعة مطلوب.');
        $sfr  = trim($body['subject_fr']     ?? '');
        $sar  = trim($body['subject_ar']     ?? '');
        $lvl  = trim($body['level']          ?? 'A1');
        $sc   = max(0, (int)($body['students_count'] ?? 0));
        $sj   = json_encode($body['schedule'] ?? [], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("
            UPDATE courses SET
                group_name_fr=:gfr, group_name_ar=:gar,
                subject_fr=:sfr,    subject_ar=:sar,
                level=:lvl,         students_count=:sc,
                schedule_json=:sj
            WHERE id=:id
        ");
        $stmt->execute([':gfr'=>$gfr,':gar'=>$gar,':sfr'=>$sfr,':sar'=>$sar,':lvl'=>$lvl,':sc'=>$sc,':sj'=>$sj,':id'=>$id]);
        json_ok(['message'=>['fr'=>'Cours mis à jour.','ar'=>'تم تحديث الدرس.'], 'id'=>$id]);
    }

    case 'delete': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            json_err('Méthode non autorisée.', 'الطريقة غير مسموح بها.', 405);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) json_err('ID de cours invalide.', 'معرف الدرس غير صالح.');
        $stmt = $db->prepare("DELETE FROM courses WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        if ($stmt->rowCount() === 0)
            json_err('Cours introuvable.', 'الدرس غير موجود.', 404);
        json_ok(['message'=>['fr'=>'Cours supprimé.','ar'=>'تم حذف الدرس.'], 'id'=>$id]);
    }

    default:
        json_err("Action inconnue: \"{$action}\". Disponibles: list, create, update, delete.",
                 "إجراء غير معروف: \"{$action}\".", 400);
}
} catch (Throwable $e) {
    json_err('Erreur serveur: ' . $e->getMessage(), 'خطأ في الخادم: ' . $e->getMessage(), 500);
}
