<?php
/**
 * config.php — Database connection (credentials read from .env)
 * ──────────────────────────────────────────────────────────────
 * Never hardcode credentials here. Put them in .env instead.
 * Copy .env.example → .env and fill in your values.
 */

// ── Load .env (no Composer required) ────────────────────────────────────────
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    die('Missing .env file. Copy .env.example to .env and fill in your credentials.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value);
    // Strip optional surrounding quotes  "value" or 'value'
    if (strlen($value) >= 2 && (
        ($value[0] === '"'  && $value[-1] === '"') ||
        ($value[0] === "'"  && $value[-1] === "'")
    )) {
        $value = substr($value, 1, -1);
    }
    if (!array_key_exists($key, $_ENV)) {
        $_ENV[$key]    = $value;
        putenv("{$key}={$value}");
    }
}

// ── Build PDO connection ─────────────────────────────────────────────────────
$host    = $_ENV['DB_HOST']    ?? 'localhost';
$db      = $_ENV['DB_NAME']    ?? '';
$user    = $_ENV['DB_USER']    ?? '';
$pass    = $_ENV['DB_PASS']    ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if (!$db || !$user) {
    die('DB_NAME and DB_USER must be set in .env');
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Never expose raw PDO messages in production — they can leak credentials/paths.
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database connection failed. Please contact the administrator.');
}