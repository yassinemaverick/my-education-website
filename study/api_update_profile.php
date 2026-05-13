<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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
        $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ? AND role = 'student'")
            ->execute([$name, $studentId]);
        // Keep session in sync
        $_SESSION['full_name'] = $name;
        echo json_encode(['ok'=>true]);

    } else {
        echo json_encode(['ok'=>false,'error'=>'Action inconnue']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Erreur serveur']);
}
