<?php
/**
 * enroll.php — Enrollment form handler
 * Accepts POST from the landing page (EN / FR / AR).
 * Validates input, verifies CSRF, saves to DB, returns JSON.
 */

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── DB connection — reads .env from the study subfolder ──────────────────────
if (empty($_ENV['DB_NAME'])) {
    $envFile = __DIR__ . '/study/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if (strlen($v) >= 2 && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'"))) {
                $v = substr($v, 1, -1);
            }
            if (!array_key_exists($k, $_ENV)) { $_ENV[$k] = $v; putenv("{$k}={$v}"); }
        }
    }
}

function enroll_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost')
             . ';dbname=' . ($_ENV['DB_NAME'] ?? '')
             . ';charset=' . ($_ENV['DB_CHARSET'] ?? 'utf8mb4');
        try {
            $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Enroll DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Database connection failed.']);
            exit;
        }
    }
    return $pdo;
}

header('Content-Type: application/json; charset=UTF-8');

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── CSRF check ───────────────────────────────────────────────────────────────
$received_token = $_POST['csrf_token'] ?? '';
$expected_token = $_SESSION['csrf_token'] ?? '';
if ($expected_token === '' || !hash_equals($expected_token, (string)$received_token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request. Please refresh and try again.']);
    exit;
}

// ── Collect & sanitise input ─────────────────────────────────────────────────
$name   = trim($_POST['name']   ?? '');
$email  = trim($_POST['email']  ?? '');
$phone  = trim($_POST['phone']  ?? '');
$course = trim($_POST['course'] ?? '');
$lang   = trim($_POST['lang']   ?? 'en'); // 'en' | 'fr' | 'ar'

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

if ($name === '' || strlen($name) > 120) {
    $errors[] = match($lang) {
        'fr' => 'Veuillez entrer votre nom complet.',
        'ar' => 'يرجى إدخال اسمك الكامل.',
        default => 'Please enter your full name.',
    };
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
    $errors[] = match($lang) {
        'fr' => 'Adresse e-mail invalide.',
        'ar' => 'البريد الإلكتروني غير صالح.',
        default => 'Please enter a valid email address.',
    };
}
if ($phone === '' || strlen($phone) > 30) {
    $errors[] = match($lang) {
        'fr' => 'Veuillez entrer votre numéro de téléphone.',
        'ar' => 'يرجى إدخال رقم هاتفك.',
        default => 'Please enter your phone number.',
    };
}
if ($course === '' || strlen($course) > 120) {
    $errors[] = match($lang) {
        'fr' => 'Veuillez sélectionner un cours.',
        'ar' => 'يرجى اختيار دورة.',
        default => 'Please select a course.',
    };
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Create enrollments table if needed & insert ──────────────────────────────
try {
    enroll_db()->exec("
        CREATE TABLE IF NOT EXISTS enrollments (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(120)  NOT NULL,
            email      VARCHAR(180)  NOT NULL,
            phone      VARCHAR(30)   NOT NULL,
            course     VARCHAR(120)  NOT NULL,
            lang       VARCHAR(5)    NOT NULL DEFAULT 'en',
            status     VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Duplicate guard: block a second 'new' request from same email within 24h
    $dup = enroll_db()->prepare("SELECT id FROM enrollments WHERE email = :email AND status = 'new' AND created_at > NOW() - INTERVAL 24 HOUR");
    $dup->execute([':email' => $email]);
    if ($dup->fetch()) {
        echo json_encode(['ok' => true]); // silently succeed to prevent enumeration
        exit;
    }

    $stmt = enroll_db()->prepare("
        INSERT INTO enrollments (name, email, phone, course, lang)
        VALUES (:name, :email, :phone, :course, :lang)
    ");
    $stmt->execute([
        ':name'   => $name,
        ':email'  => $email,
        ':phone'  => $phone,
        ':course' => $course,
        ':lang'   => $lang,
    ]);

    // Rotate CSRF token after successful submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $success = match($lang) {
        'fr' => 'Demande reçue ! Notre équipe vous contactera dans les 24 heures.',
        'ar' => 'تم استلام طلبك! سيتواصل معك فريقنا خلال 24 ساعة.',
        default => 'Request received! Our team will contact you within 24 hours.',
    };

    echo json_encode(['ok' => true, 'message' => $success]);

} catch (PDOException $e) {
    error_log('Enrollment insert failed: ' . $e->getMessage());
    http_response_code(500);
    $err = match($lang) {
        'fr' => 'Une erreur est survenue. Veuillez réessayer.',
        'ar' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
        default => 'Something went wrong. Please try again.',
    };
    echo json_encode(['ok' => false, 'error' => $err]);
}
