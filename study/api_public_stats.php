<?php
/**
 * api_public_stats.php — Public statistics endpoint (no auth required)
 * Returns aggregate counts safe to display publicly on the landing page.
 */
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // cache 1 hour

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limit.php';

$rl_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
api_rate_limit('public_stats:ip:' . $rl_ip, 30, 60);

try {
    $pdo = db();

    // Count all enrollments that weren't refused (new + accepted)
    $students = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status != 'refused'")->fetchColumn();

    // Round down to nearest 5 so the number looks intentional rather than precise
    $displayCount = max(1, (int)(floor($students / 5) * 5));

    echo json_encode([
        'ok'             => true,
        'student_count'  => $students,
        'display_count'  => $displayCount,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
