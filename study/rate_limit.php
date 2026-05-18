<?php
/**
 * rate_limit.php — Shared per-bucket rate limiter (DB-backed)
 *
 * api_rate_limit('bucket_key', $max, $window_seconds)
 *
 * Exits with HTTP 429 + Retry-After if the limit is exceeded.
 * Bucket key format:
 *   authenticated  → 'endpoint:{user_id}'
 *   public/IP      → 'endpoint:ip:{ip_address}'
 */

require_once __DIR__ . '/db.php';

function api_rate_limit(string $bucket, int $max, int $window_sec = 60): void {
    static $table_ready = false;
    $pdo = db();

    if (!$table_ready) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
            id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bucket  VARCHAR(150) NOT NULL,
            hit_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bucket_hit (bucket, hit_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $table_ready = true;
    }

    // 1-in-100 chance: prune hits older than 1 hour to keep the table small
    if (mt_rand(1, 100) === 1) {
        try { $pdo->exec("DELETE FROM api_rate_limits WHERE hit_at < NOW() - INTERVAL 1 HOUR"); }
        catch (Throwable $e) {}
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM api_rate_limits WHERE bucket = ? AND hit_at > NOW() - INTERVAL ? SECOND"
    );
    $stmt->execute([$bucket, $window_sec]);

    if ((int)$stmt->fetchColumn() >= $max) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: ' . $window_sec);
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many requests. Please try again later.']);
        exit;
    }

    $pdo->prepare("INSERT INTO api_rate_limits (bucket) VALUES (?)")->execute([$bucket]);
}
