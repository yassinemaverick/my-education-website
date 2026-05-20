<?php
/**
 * db.php — PDO singleton + schema helpers
 * ─────────────────────────────────────────
 * Credentials are read from .env — never hardcoded here.
 */

// ── Load .env if not already loaded ─────────────────────────────────────────
if (empty($_ENV['DB_NAME'])) {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        die('Missing .env file. Copy .env.example to .env and fill in your credentials.');
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (strlen($v) >= 2 && (
            ($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'")
        )) { $v = substr($v, 1, -1); }
        if (!array_key_exists($k, $_ENV)) { $_ENV[$k] = $v; putenv("{$k}={$v}"); }
    }
}

// Keep define() calls for backward-compat with assign_courses.php / courses_admin.php
// which reference DB_HOST, DB_NAME, etc. directly.
defined('DB_HOST')    || define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
defined('DB_NAME')    || define('DB_NAME',    $_ENV['DB_NAME']    ?? '');
defined('DB_USER')    || define('DB_USER',    $_ENV['DB_USER']    ?? '');
defined('DB_PASS')    || define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
defined('DB_CHARSET') || define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ── Get a PDO connection (singleton) ────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            die('Database connection failed. Please contact the administrator.');
        }
    }
    return $pdo;
}

// ── Create attendance table if it doesn't exist yet ─────────────────────────
function ensureAttendanceTable(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_id  INT UNSIGNED NOT NULL,
            student_id  INT UNSIGNED NOT NULL,
            session_num TINYINT UNSIGNED NOT NULL,
            present     TINYINT(1) NOT NULL DEFAULT 0,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_att (teacher_id, student_id, session_num)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// ── Per-teacher per-group session date overrides ─────────────────────────────
function ensureSessionDateOverridesTable(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS session_date_overrides (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_id  INT UNSIGNED NOT NULL,
            group_id    INT UNSIGNED NOT NULL,
            session_num TINYINT UNSIGNED NOT NULL,
            actual_date DATE NOT NULL,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sdo (teacher_id, group_id, session_num)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
