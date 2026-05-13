<?php
/**
 * csrf.php — API request verification
 *
 * POST/PUT/PATCH/DELETE requests must pass the session CSRF token via either:
 *   - HTTP header:  X-CSRF-Token: <token>
 *   - POST body:    csrf_token=<token>
 *
 * Origin/Referer are checked as a secondary defence but the token is authoritative.
 * Same-origin GET requests are allowed through without a token check.
 */
function csrf_verify(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    $expected = $_SESSION['csrf_token'] ?? '';

    // Check header first (set by the JS api() helper via X-CSRF-Token)
    $received = $_SERVER['HTTP_X_CSRF_TOKEN']
             ?? $_SERVER['HTTP_X_REQUEST_SOURCE'] // legacy fallback for old callers
             ?? $_POST['csrf_token']
             ?? '';

    // If we have a real token, do a constant-time comparison
    if ($expected !== '' && $received !== '') {
        if (hash_equals($expected, (string)$received)) return;
        // Token present but wrong — hard reject
        _csrf_fail();
    }

    // No token at all — fall back to Origin/Referer check (for same-origin AJAX
    // that hasn't been updated to send the token yet)
    $allowed   = 'study.upskill-edu.com';
    $origin    = parse_url($_SERVER['HTTP_ORIGIN']  ?? '', PHP_URL_HOST) ?? '';
    $referer   = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';
    if ($origin === $allowed || $referer === $allowed) return;

    _csrf_fail();
}

function _csrf_fail(): void {
    if (function_exists('json_err')) {
        json_err('Requête invalide.', 'طلب غير صالح.', 403);
    }
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}
