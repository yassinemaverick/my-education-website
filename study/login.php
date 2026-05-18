<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index2.php'); exit;
}

$lang  = trim($_POST['lang'] ?? 'en');
$role  = trim($_POST['role'] ?? 'student');
$pages = ['en' => 'index2.php', 'fr' => 'index2-fr.php', 'teacher' => 'login-teacher.php'];
$back  = $pages[$lang] ?? 'index2.php';

$redirect = function(string $err) use ($back, $role, $lang) {
    header("Location: {$back}?" . http_build_query(['error'=>$err,'role'=>$role,'lang'=>$lang]));
    exit;
};

// Honeypot
if (!empty($_POST['website'])) { header("Location: {$back}"); exit; }

$username = trim($_POST['username'] ?? '');
$password =      $_POST['password']  ?? '';

if ($username === '' || $password === '') { $redirect('empty'); }
if (mb_strlen($username) > 80 || mb_strlen($password) > 200) { $redirect('invalid'); }

// Rate-limiting: 5 attempts per IP per 15 minutes
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at)
    )");
    $chk = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > NOW() - INTERVAL 15 MINUTE");
    $chk->execute([$ip]);
    if ((int)$chk->fetchColumn() >= 5) { $redirect('locked'); }
} catch (PDOException $e) {}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

$hash  = $user['password'] ?? '$2y$10$invalidsaltpadding00000000000000000000000000000000000';
$valid = password_verify($password, $hash);

if (!$user || !$valid) {
    try { $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$ip]); } catch (PDOException $e) {}
    $redirect('invalid');
}
if ($user['role'] !== $role) { $redirect('role'); }

try { $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]); } catch (PDOException $e) {}

session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];
$_SESSION['lang']      = $lang;

$dash = ['admin'=>'dashboard-admin.php','teacher'=>'dashboard-teacher.php','student'=>'dashboard-student.php'];
header("Location: " . ($dash[$user['role']] ?? 'dashboard-student.php'));
exit;
