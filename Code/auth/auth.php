<?php
// /auth/auth.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../api/session.php';
require_once __DIR__ . '/../api/db.php'; // provides $pdo

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

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'firstName' => $user['first_name'],
        'lastName'  => $user['last_name'],
    ];
    session_regenerate_id(true);
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        $to = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /auth/login.php?next=' . urlencode($to));
        exit;
    }
}
