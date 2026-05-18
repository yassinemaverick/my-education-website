<?php
/**
 * api_placement.php — Save / fetch placement test results
 * POST  → save a new result
 * GET   → return all results (admin, auth required)
 */
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limit.php';

// ── Ensure table exists ────────────────────────────────────────────────────
function ensurePlacementTable(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS placement_results (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(120) NOT NULL,
            email       VARCHAR(180) DEFAULT NULL,
            phone       VARCHAR(40)  DEFAULT NULL,
            score       TINYINT UNSIGNED NOT NULL,
            placement   VARCHAR(60)  NOT NULL,
            ip          VARCHAR(100) DEFAULT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ── GET — list results (admin only) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Forbidden']);
        exit;
    }
    api_rate_limit('placement:' . (int)$_SESSION['user_id'], 30, 60);
    try {
        ensurePlacementTable();
        $rows = db()->query("SELECT id, name, email, phone, score, placement, created_at FROM placement_results ORDER BY created_at DESC LIMIT 500")->fetchAll();
        echo json_encode(['ok'=>true,'results'=>$rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── POST — save result (public) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (!$raw) { parse_str(file_get_contents('php://input'), $raw); }

    $name      = trim($raw['name']      ?? '');
    $email     = trim($raw['email']     ?? '');
    $phone     = trim($raw['phone']     ?? '');
    $score     = (int)($raw['score']    ?? -1);
    $placement = trim($raw['placement'] ?? '');

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Name is required']);
        exit;
    }
    if ($email === '' && $phone === '') {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Email or phone is required']);
        exit;
    }
    if ($score < 0 || $score > 60) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Invalid score']);
        exit;
    }
    $allowed = ['Beginner 1','Beginner 2','Beginner 3','Pre-Intermediate 1','Pre-Intermediate 2','Pre-Intermediate 3','Intermediate 1','Intermediate 2','Intermediate 3','Upper-Intermediate 1','Upper-Intermediate 2','Upper-Intermediate 3','Advanced 1','Advanced 2'];
    if (!in_array($placement, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Invalid placement']);
        exit;
    }

    // Trim X-Forwarded-For to first IP
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $ip  = $xff !== '' ? trim(explode(',', $xff)[0]) : ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip  = substr($ip, 0, 100);

    api_rate_limit('placement:ip:' . $ip, 5, 3600);

    try {
        ensurePlacementTable();
        $st = db()->prepare("INSERT INTO placement_results (name, email, phone, score, placement, ip) VALUES (?,?,?,?,?,?)");
        $st->execute([$name, $email ?: null, $phone ?: null, $score, $placement, $ip]);
        echo json_encode(['ok'=>true,'placement'=>$placement,'score'=>$score], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── DELETE — remove a result (admin only) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
    }
    api_rate_limit('placement:' . (int)$_SESSION['user_id'], 30, 60);
    $raw = json_decode(file_get_contents('php://input'), true);
    $id  = (int)($raw['id'] ?? 0);
    if ($id < 1) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid id']); exit; }
    try {
        ensurePlacementTable();
        db()->prepare("DELETE FROM placement_results WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
