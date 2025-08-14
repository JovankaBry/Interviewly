<?php
// Start a secure session (include this before output)
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Set strict cookies
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    // If you're on HTTPS, also do:
    // ini_set('session.cookie_secure', 1);

    session_start();
}