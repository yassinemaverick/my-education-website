<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student','teacher'])) {
    echo json_encode(['ok'=>false,'error'=>'Non autorisé']);
    exit;
}

// CSRF check
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['ok'=>false,'error'=>'Token invalide']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['ok'=>false,'error'=>'Requête invalide']);
    exit;
}

$action    = $body['action'] ?? '';
$studentId = (int) $_SESSION['user_id'];

require_once __DIR__ . '/db.php';

try {
    $pdo = db();

    if ($action === 'update_name') {
        $name = trim($body['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            echo json_encode(['ok'=>false,'error'=>'Nom invalide']);
            exit;
        }
        $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")
            ->execute([$name, $studentId]);
        // Keep session in sync
        $_SESSION['full_name'] = $name;
        echo json_encode(['ok'=>true]);

    } elseif ($action === 'set_zoom_url') {
        if ($_SESSION['role'] !== 'teacher') {
            echo json_encode(['ok'=>false,'error'=>'Accès refusé']);
            exit;
        }
        $url = trim($body['zoom_url'] ?? '');
        // Allow empty (to clear the link), otherwise validate
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['ok'=>false,'error'=>'URL invalide']);
            exit;
        }
        if (strlen($url) > 500) {
            echo json_encode(['ok'=>false,'error'=>'URL trop longue']);
            exit;
        }
        // Update all courses this teacher is assigned to
        $pdo->prepare("
            UPDATE courses c
            JOIN teacher_courses tc ON tc.course_id = c.id
            SET c.zoom_url = ?
            WHERE tc.teacher_id = ?
        ")->execute([$url ?: null, $studentId]);
        echo json_encode(['ok'=>true]);

    } elseif ($action === 'change_password') {
        $current  = $body['current_password'] ?? '';
        $newPwd   = $body['new_password'] ?? '';
        $confirm  = $body['confirm_password'] ?? '';

        if ($current === '' || $newPwd === '' || $confirm === '') {
            echo json_encode(['ok'=>false,'error'=>'Tous les champs sont requis']);
            exit;
        }
        if ($newPwd !== $confirm) {
            echo json_encode(['ok'=>false,'error'=>'Les mots de passe ne correspondent pas']);
            exit;
        }
        if (mb_strlen($newPwd) < 8) {
            echo json_encode(['ok'=>false,'error'=>'Le mot de passe doit contenir au moins 8 caractères']);
            exit;
        }

        $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([$studentId]);
        $user = $row->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($current, $user['password'])) {
            echo json_encode(['ok'=>false,'error'=>'Mot de passe actuel incorrect']);
            exit;
        }

        $hash = password_hash($newPwd, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$hash, $studentId]);
        echo json_encode(['ok'=>true]);

    } else {
        echo json_encode(['ok'=>false,'error'=>'Action inconnue']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Erreur serveur']);
}
