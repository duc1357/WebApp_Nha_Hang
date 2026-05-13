<?php
// config/session_config.php

// Secure Session Params
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Enable if HTTPS is available (often problematic on local XAMPP without cert, so checks header)
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    if ($secure) {
        ini_set('session.cookie_secure', 1);
    }
    
    ini_set('session.cookie_samesite', 'Lax');

    // Set lifetime (e.g., 2 hours) while preserving secure cookie flags.
    ini_set('session.gc_maxlifetime', 7200);
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    
    session_start();
}
