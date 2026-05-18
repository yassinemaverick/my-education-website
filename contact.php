<?php
/**
 * contact.php — Contact form handler using PHPMailer (standalone)
 * Upload PHPMailer files to public_html/phpmailer/ (see instructions below)
 */
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=UTF-8');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$received = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['csrf_token'] ?? '';
if ($expected === '' || !hash_equals($expected, (string)$received)) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['ok' => false, 'error' => 'Invalid request. Please refresh and try again.']);
    exit;
}

// ── Load .env ─────────────────────────────────────────────────────────────────
if (empty($_ENV['MAIL_PASS'])) {
    $envFile = __DIR__ . '/study/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if (strlen($v) >= 2 && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'")))
                $v = substr($v, 1, -1);
            if (!array_key_exists($k, $_ENV)) { $_ENV[$k] = $v; putenv("{$k}={$v}"); }
        }
    }
}

// ── Validate input ────────────────────────────────────────────────────────────
$lang    = trim($_POST['lang'] ?? 'fr');
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$phone   = trim($_POST['phone']   ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = match($lang) {
        'ar' => 'يرجى إدخال اسمك الكامل (120 حرفاً كحد أقصى).',
        'en' => 'Please enter your full name (max 120 characters).',
        default => 'Veuillez entrer votre nom complet (120 caractères max).',
    };
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    $errors[] = match($lang) {
        'ar' => 'يرجى إدخال بريد إلكتروني صالح.',
        'en' => 'Please enter a valid email address.',
        default => 'Veuillez entrer une adresse e-mail valide.',
    };
}
if ($phone !== '' && (!preg_match('/^[+\d\s\-(). ]{1,30}$/', $phone))) {
    $errors[] = match($lang) {
        'ar' => 'رقم الهاتف غير صالح.',
        'en' => 'Invalid phone number.',
        default => 'Numéro de téléphone invalide.',
    };
}
if ($message === '' || mb_strlen($message) > 2000) {
    $errors[] = match($lang) {
        'ar' => 'يرجى كتابة رسالتك (2000 حرف كحد أقصى).',
        'en' => 'Please enter your message (max 2000 characters).',
        default => 'Veuillez entrer votre message (2000 caractères max).',
    };
}
if ($errors) {
    http_response_code(422);
    ob_clean();
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── PHPMailer ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'] ?? '';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USER'] ?? '';
    $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 465);
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($_ENV['MAIL_USER'] ?? '', 'Upskill Education');
    $mail->addAddress($_ENV['MAIL_TO'] ?? '', 'Upskill Admin');
    $mail->addReplyTo($email, $name);

    $mail->Subject = "[Upskill Contact] Message de {$name}";
    $mail->Body    = "Nom : {$name}\nEmail : {$email}\nTéléphone : {$phone}\n\nMessage :\n{$message}\n\n---\nEnvoyé depuis upskill-edu.com";

    $mail->send();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $msg = match($lang) {
        'ar'    => 'تم إرسال رسالتك! سنرد عليك قريباً.',
        'en'    => "Message sent! We'll get back to you soon.",
        default => 'Message envoyé ! Nous vous répondrons bientôt.',
    };
    ob_clean();
    echo json_encode(['ok' => true, 'message' => $msg]);

} catch (Exception $e) {
    error_log('contact.php PHPMailer error: ' . $mail->ErrorInfo);
    http_response_code(500);
    $err = match($lang) {
        'ar'    => 'فشل الإرسال. يرجى المحاولة مرة أخرى.',
        'en'    => 'Failed to send. Please try again.',
        default => "Échec de l'envoi. Veuillez réessayer.",
    };
    ob_clean();
    echo json_encode(['ok' => false, 'error' => $err]);
}
