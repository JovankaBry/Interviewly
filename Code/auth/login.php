<?php
// /auth/login.php  — standalone (no base.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php'; // brings session + $pdo + login(), etc.

// handle POST
$err  = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');

    if ($u === '' || $p === '') {
        $err = 'Please fill username/email and password.';
    } elseif (login($u, $p)) {
        // prevent open redirect
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $urlHost = parse_url($next, PHP_URL_HOST);
        if ($urlHost && $urlHost !== $host) $next = '/index.php';

        header('Location: ' . $next, true, 303);
        exit;
    } else {
        $err = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · Interviewly</title>
  <link rel="stylesheet" href="/static/css/style.css">
  <style>
    /* Minimal layout so we don't need base.php */
    body{background:#0f172a;color:#e5e7eb;margin:0;font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
    .wrap{min-height:100dvh;display:grid;place-items:center;padding:24px}
    .card{width:100%;max-width:480px;background:#0b1220;border:1px solid #1e293b;border-radius:12px;padding:22px}
    h1{margin:0 0 16px;font-size:22px}
    .label{display:block;margin:10px 0 6px;color:#f8fafc;font-weight:600}
    .input{width:100%;color:#f8fafc;background:transparent;border:1px solid #1e293b;border-radius:10px;padding:10px;outline:none}
    .pill-btn{width:100%;padding:12px;border-radius:999px;font-weight:700;cursor:pointer;background:#2563eb;color:#000;border:0;margin-top:12px}
    .muted{color:#9ca3af}
    .error{background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:8px;margin:0 0 12px}
    .brand{position:fixed;left:16px;top:14px;font-weight:800;letter-spacing:.2px}
    .footer{position:fixed;left:0;right:0;bottom:8px;text-align:center;color:#64748b;font-size:12px}
  </style>
</head>
<body>
  <div class="brand">Interviewly</div>

  <div class="wrap">
    <div class="card">
      <h1>Login</h1>

      <?php if ($err): ?>
        <div class="error"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post" action="/auth/login.php">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

        <label class="label">Username or Email</label>
        <input class="input" name="username" placeholder="yourname or you@mail.com" required>

        <label class="label">Password</label>
        <input class="input" type="password" name="password" placeholder="••••••••" required>

        <button class="pill-btn" type="submit">Login</button>
      </form>
      <p class="muted" style="margin-top:12px">
        Don’t have an account?
        <a class="muted" href="/auth/register.php?next=<?= urlencode($next) ?>" style="text-decoration:none">Create one</a>
</p>
    </div>
  </div>

  <div class="footer">© <?= date('Y') ?> Interviewly</div>
</body>
</html>
