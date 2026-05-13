<?php
/**
 * csrf_token.php — Returns the session CSRF token as JSON.
 * Called by the static login pages before form submission.
 *
 * GET /csrf_token.php → { "token": "…" }
 */

// Use a fixed session name so login.php resumes the exact same session
require_once __DIR__ . '/session.php';
require 'csrf.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo json_encode(['token' => csrf_token()]);
