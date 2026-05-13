<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure'   => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}
