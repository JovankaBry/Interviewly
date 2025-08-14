<?php
// /auth/auth.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../api/session.php'; // starts the session safely
require_once __DIR__ . '/../api/db.php';      // provides $pdo (PDO)

/**
 * Attempt to log in with a username OR email and a password.
 * On success, stores user info in $_SESSION['user'] and $_SESSION['user_id'].
 */
function login(string $usernameOrEmail, string $password): bool {
    global $pdo;

    $sql = "SELECT id, username, email, password_hash, first_name, last_name
            FROM users
            WHERE username = :u1 OR email = :u2
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':u1' => $usernameOrEmail, ':u2' => $usernameOrEmail]);

    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;

    if (!password_verify($password, $user['password_hash'])) return false;

    // Store minimal + convenient fields in the session
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'firstName' => $user['first_name'],
        'lastName'  => $user['last_name'],
    ];
    // Fast access to the id
    $_SESSION['user_id'] = (int)$user['id'];

    // Prevent session fixation
    session_regenerate_id(true);
    return true;
}

/** Log out and destroy the session. */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'] ?? false,
            $p['httponly'] ?? true
        );
    }
    session_destroy();
}

/** Return true if a user is logged in. */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/** Return the current user's id (or 0 if not logged in). */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/** Return the current user array (or null). */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Require login for a page.
 * If not logged in, redirect to /auth/login.php?next=<current url>
 * Uses a safe same-host redirect target.
 */
function require_login(): void {
    if (is_logged_in()) return;

    $to = $_SERVER['REQUEST_URI'] ?? '/';
    // Safety: keep redirect target on same host only
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $urlHost = parse_url($to, PHP_URL_HOST);
    if ($urlHost && $urlHost !== $host) {
        $to = '/';
    }

    header('Location: /auth/login.php?next=' . urlencode($to), true, 302);
    exit;
}
