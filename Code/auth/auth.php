<?php
// /auth/auth.php
// Auth helpers + admin helpers (is_admin, require_admin) + CSRF for admin actions

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../api/session.php'; // starts the session safely
require_once __DIR__ . '/../api/db.php';      // provides $pdo (PDO)

/**
 * Attempt to log in with a username OR email and a password.
 * On success, stores user info in $_SESSION['user'], $_SESSION['user_id'], and $_SESSION['is_admin'].
 */
function login(string $usernameOrEmail, string $password): bool {
    global $pdo;

    // Use TWO distinct placeholders to avoid "Invalid parameter number" with PDO
    $sql = "SELECT id, username, email, password_hash, first_name, last_name, COALESCE(is_admin,0) AS is_admin
            FROM users
            WHERE username = :u1 OR email = :u2
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':u1' => $usernameOrEmail,
        ':u2' => $usernameOrEmail,
    ]);

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
    // Fast access to id + admin flag
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['is_admin'] = (int)$user['is_admin'];

    // Prevent session fixation
    session_regenerate_id(true);
    return true;
}

/** Log out and destroy the session. */
function logout(): void {
    // Clear all auth-related data
    unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['is_admin'], $_SESSION['admin_csrf']);

    // Destroy session cookie + session
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
 * Admin helpers
 *  - is_admin(): cached check using $_SESSION['is_admin']; refreshes from DB if missing
 *  - require_admin(): gate a page to admins only
 */
function is_admin(): bool {
    if (!is_logged_in()) return false;

    // Use cached value if present
    if (array_key_exists('is_admin', $_SESSION)) {
        return (bool)$_SESSION['is_admin'];
    }

    // Refresh from DB if not cached
    global $pdo;
    $st = $pdo->prepare("SELECT COALESCE(is_admin,0) FROM users WHERE id = ? LIMIT 1");
    $st->execute([current_user_id()]);
    $_SESSION['is_admin'] = (int)($st->fetchColumn() ?: 0);

    return (bool)$_SESSION['is_admin'];
}

/** Call on pages that require login (redirects to login if needed). */
function require_login(): void {
    if (is_logged_in()) return;

    $to = $_SERVER['REQUEST_URI'] ?? '/';
    // Safety: keep redirect target on same host only
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $urlHost = parse_url($to, PHP_URL_HOST);
    if ($urlHost && $urlHost !== $host) {
        $to = '/';
    }

    header('Location: /auth/login.php?next=' . urlencode($to), true, 302);
    exit;
}

/** Gate a page to admins only. Sends 403 if logged in but not admin. */
function require_admin(): void {
    require_login();
    if (is_admin()) return;

    http_response_code(403);
    echo "<!doctype html><meta charset='utf-8'><body style=\"margin:0;background:#0b0f1a;color:#eaf2ff;
          font:16px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial\">
          <div style='max-width:720px;margin:10vh auto;padding:24px;border:1px solid #1e2a3b;
          border-radius:12px;background:#0f1626'>
          <h1 style='margin:0 0 8px 0'>403 · Forbidden</h1>
          <p style='color:#9aa4b2;margin:0'>You don’t have access to this page.</p>
          </div></body>";
    exit;
}

/** Admin CSRF helpers (use in admin forms) */
function admin_csrf_token(): string {
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['admin_csrf'];
}
function admin_csrf_check(string $token): bool {
    return isset($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $token);
}

/** Refresh cached admin flag from the DB (e.g., after promote/demote). */
function refresh_admin_flag(): void {
    if (!is_logged_in()) { unset($_SESSION['is_admin']); return; }
    global $pdo;
    $st = $pdo->prepare("SELECT COALESCE(is_admin,0) FROM users WHERE id = ? LIMIT 1");
    $st->execute([current_user_id()]);
    $_SESSION['is_admin'] = (int)($st->fetchColumn() ?: 0);
}