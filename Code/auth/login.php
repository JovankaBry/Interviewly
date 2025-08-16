<?php
// /auth/login.php — standalone
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';

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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login · Tracklly</title>

  <link rel="icon" type="image/png" href="/static/images/icon2.png" />
  <link rel="stylesheet" href="/static/css/style.css" />

  <style>
    :root{ --card-bg: rgba(255,255,255,.03); }

    body{
      margin:0; background: var(--bg); color: var(--text);
      font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;
    }

    .wrap{ min-height:100dvh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px 16px; gap:16px; }

    /* Brand header */
    .brand{
      width:100%; max-width:520px;
      display:flex; align-items:center; gap:12px;
      text-decoration:none; color:inherit;
    }
    .brand img{ width:44px; height:44px; border-radius:10px; object-fit:cover; }
    .brand .name{ font-weight:900; font-size:24px; letter-spacing:.3px; }

    .card{
      width:100%; max-width:520px;
      background: var(--card-bg); border:1px solid var(--border);
      border-radius:14px; box-shadow:0 16px 40px rgba(0,0,0,.35);
      padding:22px;
    }

    h1{ margin:0 0 6px; font-size:22px; font-weight:800; }
    .sub{ margin:0 0 16px; color:var(--muted); font-size:13px }

    .field{ margin-bottom:12px; }
    .label{ display:block; margin:0 0 6px; font-weight:650; font-size:14px; }
    .input{
      width:100%; color:var(--text);
      background:rgba(255,255,255,.02); border:1px solid var(--border);
      border-radius:10px; padding:10px 12px;
    }

    .pill-btn{
      width:100%; padding:12px; border-radius:999px; border:0; cursor:pointer;
      background:linear-gradient(180deg, var(--primary), var(--primary-dark));
      color:#fff; font-weight:800;
      box-shadow:0 12px 24px rgba(37,99,235,.25);
    }

    .btn-outline{
      display:inline-flex; align-items:center; justify-content:center;
      height:40px; padding:0 14px; border-radius:10px;
      color:#fff; background:rgba(255,255,255,.04); border:1px solid var(--border);
      font-weight:800; text-decoration:none;
      margin-top:14px; width:100%;
    }

    .links{ margin-top:10px; display:flex; gap:10px; justify-content:space-between; align-items:center; }
    .muted{ color:var(--muted); }
    .a{ color:#cfe1ff; text-decoration:none; font-weight:650; }

    .error{ background:rgba(239,68,68,.12); color:#fecaca;
      border:1px solid rgba(239,68,68,.35); padding:10px; border-radius:10px; margin:0 0 12px; font-weight:600; }

    .main-footer{ text-align:center; color:var(--muted); padding:18px 10px 8px; font-size:.9rem; }
    .social-wrap{ margin-top:8px; display:flex; flex-direction:column; align-items:center; gap:8px; }
    .social-title{ margin:0; font-weight:700; color:#e5e7eb; font-size:.95rem; }
    .social-icons{ display:flex; align-items:center; gap:14px; }
    .icon-btn{
      display:inline-flex; align-items:center; justify-content:center;
      width:40px; height:40px; border-radius:50%;
      background:#0b1222; border:1px solid var(--border);
    }
    .icon-btn img{ width:22px; height:22px; }
  </style>
</head>
<body>
  <div class="wrap">

    <!-- Brand -->
    <a class="brand" href="/index.php" title="Go to home">
      <img src="/static/images/icon2.png" alt="Tracklly logo">
      <span class="name">Tracklly</span>
    </a>

    <!-- Card -->
    <div class="card">
      <h1>Sign in</h1>
      <p class="sub">Welcome back — log into your Tracklly account.</p>

      <?php if ($err): ?>
        <div class="error"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post" action="/auth/login.php" novalidate>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

        <div class="field">
          <label class="label" for="username">Username or Email</label>
          <input class="input" id="username" name="username" placeholder="yourname or you@mail.com" value="<?= isset($u) ? htmlspecialchars($u) : '' ?>" required>
        </div>

        <div class="field">
          <label class="label" for="password">Password</label>
          <input class="input" id="password" type="password" name="password" placeholder="••••••••" required>
        </div>

        <button class="pill-btn" type="submit">Login</button>

        <div class="links">
          <span class="muted">Don’t have an account?</span>
          <a class="a" href="/auth/register.php?next=<?= urlencode($next) ?>">Create one</a>
        </div>

        <!-- Back to site -->
        <a class="btn-outline" href="/index.php">Back to site</a>
      </form>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Tracklly. All rights reserved.</p>
      <div class="social-wrap">
        <p class="social-title">Follow us</p>
        <div class="social-icons">
          <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank"><img src="/static/images/tiktok.png" alt="TikTok"></a>
          <a class="icon-btn" href="https://www.instagram.com/tracklly/#" target="_blank"><img src="/static/images/instagram.png" alt="Instagram"></a>
        </div>
      </div>
    </footer>

  </div>
</body>
</html>