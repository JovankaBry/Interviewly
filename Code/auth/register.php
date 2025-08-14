<?php
// /auth/register.php — standalone (no base.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php'; // brings session + $pdo + login()

$err = '';
$ok  = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');

    // basic validation
    if ($username === '' || $email === '' || $password === '' || $confirm === '') {
        $err = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email looks invalid.';
    } elseif ($password !== $confirm) {
        $err = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } else {
        try {
            // check duplicates
            $st = $pdo->prepare("SELECT 1 FROM users WHERE username = :u OR email = :e LIMIT 1");
            $st->execute([':u' => $username, ':e' => $email]);
            if ($st->fetch()) {
                $err = 'Username or email already exists.';
            } else {
                // create user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, first_name, last_name)
                    VALUES (:u, :e, :h, :fn, :ln)
                ");
                $ins->execute([
                    ':u'  => $username,
                    ':e'  => $email,
                    ':h'  => $hash,
                    ':fn' => $first_name ?: null,
                    ':ln' => $last_name  ?: null,
                ]);

                // auto login then redirect
                if (login($email, $password) || login($username, $password)) {
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    $urlHost = parse_url($next, PHP_URL_HOST);
                    if ($urlHost && $urlHost !== $host) $next = '/index.php';
                    header('Location: ' . $next, true, 303);
                    exit;
                } else {
                    $ok = 'Account created. Please log in.';
                }
            }
        } catch (Throwable $e) {
            $err = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register · Interviewly</title>
  <link rel="stylesheet" href="/static/css/style.css">
  <style>
    body{background:#0f172a;color:#e5e7eb;margin:0;font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
    .wrap{min-height:100dvh;display:grid;place-items:center;padding:24px}
    .card{width:100%;max-width:520px;background:#0b1220;border:1px solid #1e293b;border-radius:12px;padding:22px}
    h1{margin:0 0 16px;font-size:22px}
    .two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .label{display:block;margin:10px 0 6px;color:#f8fafc;font-weight:600}
    .input{width:100%;color:#f8fafc;background:transparent;border:1px solid #1e293b;border-radius:10px;padding:10px;outline:none}
    .pill-btn{width:100%;padding:12px;border-radius:999px;font-weight:700;cursor:pointer;background:#2563eb;color:#000;border:0;margin-top:12px}
    .muted{color:#9ca3af}
    .error{background:#2b2b2b;color:#ffb4b4;padding:10px;border-radius:8px;margin:0 0 12px}
    .ok{background:#1f2b1f;color:#9ff29f;padding:10px;border-radius:8px;margin:0 0 12px}
    .brand{position:fixed;left:16px;top:14px;font-weight:800;letter-spacing:.2px}
    .footer{position:fixed;left:0;right:0;bottom:8px;text-align:center;color:#64748b;font-size:12px}
  </style>
</head>
<body>
  <div class="brand">Interviewly</div>

  <div class="wrap">
    <div class="card">
      <h1>Create Account</h1>

      <?php if ($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <?php if ($ok):  ?><div class="ok"><?= htmlspecialchars($ok)  ?></div><?php endif; ?>

      <form method="post" action="/auth/register.php">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

        <label class="label">Username *</label>
        <input class="input" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

        <label class="label">Email *</label>
        <input class="input" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <div class="two">
          <div>
            <label class="label">First name</label>
            <input class="input" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
          </div>
          <div>
            <label class="label">Last name</label>
            <input class="input" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
          </div>
        </div>

        <label class="label">Password *</label>
        <input class="input" type="password" name="password" minlength="8" placeholder="min. 8 characters" required>

        <label class="label">Confirm Password *</label>
        <input class="input" type="password" name="confirm" minlength="8" required>

        <button class="pill-btn" type="submit">Create account</button>
      </form>

      <p class="muted" style="margin-top:12px">
        Already have an account? <a class="muted" href="/auth/login.php?next=<?= urlencode($next) ?>" style="text-decoration:none">Log in</a>
      </p>
    </div>
  </div>

  <div class="footer">© <?= date('Y') ?> Interviewly</div>
</body>
</html>
