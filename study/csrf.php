<?php
/**
 * csrf.php — API request verification
 *
 * POST/PUT/PATCH/DELETE requests must pass the session CSRF token via either:
 *   - HTTP header:  X-CSRF-Token: <token>
 *   - POST body:    csrf_token=<token>
 *
 * The token is authoritative. No Origin/Referer fallback — those headers are
 * user-controlled and cannot be trusted as a CSRF defence.
 * Same-origin GET requests are allowed through without a token check.
 */
function csrf_verify(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    $expected = $_SESSION['csrf_token'] ?? '';
    $received = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

    // Both sides must be non-empty and match — any other state is a hard reject
    if ($expected === '' || $received === '' || !hash_equals($expected, (string)$received)) {
        _csrf_fail();
    }
}

function _csrf_fail(): void {
    if (function_exists('json_err')) {
        json_err('Requête invalide.', 'طلب غير صالح.', 403);
    }
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'session_expired']);
    exit;
}
