<?php
// /auth/register.php — standalone (no base.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php'; // brings session + $pdo + login()

$err  = '';
$ok   = '';
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

                // auto login then redirect (try email then username)
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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create Account · Tracklly</title>

  <!-- Favicon / tab icon -->
  <link rel="icon" type="image/png" href="/static/images/icon2.png" />

  <!-- Global theme -->
  <link rel="stylesheet" href="/static/css/style.css" />

  <style>
    :root{ --card-bg: rgba(255,255,255,.03); }

    *{ box-sizing: border-box; }
    html, body{ height:100%; }
    body{
      margin:0;
      background: var(--bg);
      color: var(--text);
      font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;
      -webkit-font-smoothing:antialiased;
    }

    .wrap{
      min-height:100dvh;
      display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      padding:24px 16px; gap:16px;
    }

    /* Brand (same as login, slightly bigger) */
    .brand{
      width:100%; max-width:640px;
      display:flex; align-items:center; gap:12px;
      text-decoration:none; color:inherit;
    }
    .brand img{ width:44px; height:44px; border-radius:10px; object-fit:cover; }
    .brand .name{ font-weight:900; font-size:24px; letter-spacing:.3px; }

    .card{
      width:100%; max-width:640px;
      background: var(--card-bg);
      border:1px solid var(--border);
      border-radius:14px;
      box-shadow:0 16px 40px rgba(0,0,0,.35);
      padding:22px;
    }

    h1{ margin:0 0 6px; font-size:22px; font-weight:800; }
    .sub{ margin:0 0 16px; color:var(--muted); font-size:13px }

    .field{ margin-bottom:12px; }
    .label{ display:block; margin:0 0 6px; font-weight:650; font-size:14px; }
    .control{ position:relative; }
    .input{
      width:100%;
      color:var(--text);
      background:rgba(255,255,255,.02);
      border:1px solid var(--border);
      border-radius:10px;
      padding:10px 44px 10px 12px;
      outline:none;
    }
    .input:focus{ box-shadow:0 0 0 3px rgba(37,99,235,.25); border-color:rgba(37,99,235,.45); }

    /* two-column for names on wider screens */
    .two{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:520px){ .two{ grid-template-columns:1fr; } }

    /* Eye toggles */
    .pass-toggle{
      position:absolute; right:6px; top:50%; transform:translateY(-50%);
      width:30px; height:30px; border-radius:8px;
      border:1px solid var(--border);
      background:rgba(255,255,255,.04);
      display:inline-flex; align-items:center; justify-content:center;
      cursor:pointer;
    }
    .pass-toggle img{ width:18px; height:18px; display:block; }
    .pass-toggle:hover{ filter:brightness(1.08); }

    .pill-btn{
      width:100%; padding:12px; border-radius:999px; border:0; cursor:pointer;
      background:linear-gradient(180deg, var(--primary), var(--primary-dark));
      color:#fff; font-weight:800; letter-spacing:.2px;
      box-shadow:0 12px 24px rgba(37,99,235,.25);
      transition:transform .15s ease, filter .15s ease;
      margin-top:6px;
    }
    .pill-btn:hover{ transform:translateY(-1px); filter:brightness(1.05); }

    .btn-outline{
      display:inline-flex; align-items:center; justify-content:center;
      height:40px; padding:0 14px; border-radius:10px;
      color:#fff; background:rgba(255,255,255,.04); border:1px solid var(--border);
      font-weight:800; text-decoration:none;
      margin-top:14px; width:100%;
      transition:transform .15s ease, filter .15s ease;
    }
    .btn-outline:hover{ transform:translateY(-1px); filter:brightness(1.08); }

    .links{ margin-top:10px; display:flex; gap:10px; justify-content:space-between; align-items:center; }
    .muted{ color:var(--muted); }
    .a{ color:#cfe1ff; text-decoration:none; font-weight:650; }
    .a:hover{ text-decoration:underline; }

    .error{
      background:rgba(239,68,68,.12); color:#fecaca;
      border:1px solid rgba(239,68,68,.35);
      padding:10px; border-radius:10px; margin:0 0 12px; font-weight:600;
    }
    .ok{
      background:rgba(16,185,129,.12); color:#bbf7d0;
      border:1px solid rgba(16,185,129,.35);
      padding:10px; border-radius:10px; margin:0 0 12px; font-weight:600;
    }

    /* Footer (same as login) */
    .main-footer{ text-align:center; color:var(--muted); padding:18px 10px 8px; font-size:.9rem; }
    .social-wrap{ margin-top:8px; display:flex; flex-direction:column; align-items:center; gap:8px; }
    .social-title{ margin:0; font-weight:700; color:#e5e7eb; font-size:.95rem; }
    .social-icons{ display:flex; align-items:center; gap:14px; }
    .icon-btn{
      display:inline-flex; align-items:center; justify-content:center;
      width:40px; height:40px; border-radius:50%;
      background:#0b1222; border:1px solid var(--border);
      text-decoration:none;
      transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .icon-btn:hover{
      transform:translateY(-1px);
      box-shadow:0 6px 14px rgba(0,0,0,.35);
      background:linear-gradient(180deg,#25f4ee,#fe2c55);
    }
    .icon-btn img{ width:22px; height:22px; display:block; }
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
    <div class="card" role="main" aria-labelledby="registerTitle">
      <h1 id="registerTitle">Create Account</h1>
      <p class="sub">Join Tracklly to organize your job hunt.</p>

      <?php if ($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <?php if ($ok):  ?><div class="ok"><?= htmlspecialchars($ok)  ?></div><?php endif; ?>

      <form method="post" action="/auth/register.php" novalidate>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

        <div class="field">
          <label class="label" for="username">Username *</label>
          <input class="input" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
        </div>

        <div class="field">
          <label class="label" for="email">Email *</label>
          <input class="input" id="email" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
        </div>

        <div class="two">
          <div class="field">
            <label class="label" for="first_name">First name</label>
            <input class="input" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" autocomplete="given-name">
          </div>
          <div class="field">
            <label class="label" for="last_name">Last name</label>
            <input class="input" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" autocomplete="family-name">
          </div>
        </div>

        <div class="field">
          <label class="label" for="password">Password *</label>
          <div class="control">
            <input class="input" id="password" type="password" name="password" minlength="8" placeholder="min. 8 characters" required autocomplete="new-password">
            <button type="button" class="pass-toggle" aria-label="Show password" title="Show password" onclick="togglePass('password','eye1')">
              <img id="eye1" src="/static/images/eye.png" alt="">
            </button>
          </div>
        </div>

        <div class="field">
          <label class="label" for="confirm">Confirm Password *</label>
          <div class="control">
            <input class="input" id="confirm" type="password" name="confirm" minlength="8" required autocomplete="new-password">
            <button type="button" class="pass-toggle" aria-label="Show password" title="Show password" onclick="togglePass('confirm','eye2')">
              <img id="eye2" src="/static/images/eye.png" alt="">
            </button>
          </div>
        </div>

        <button class="pill-btn" type="submit">Create account</button>

        <div class="links">
          <span class="muted">Already have an account?</span>
          <a class="a" href="/auth/login.php?next=<?= urlencode($next) ?>">Log in</a>
        </div>

        <!-- Back to site -->
        <a class="btn-outline" href="/index.php">Back to site</a>
      </form>
    </div>

    <!-- Footer (same as login) -->
    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Tracklly. All rights reserved.</p>
      <div class="social-wrap" aria-label="Follow us">
        <p class="social-title">Follow us</p>
        <div class="social-icons">
          <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank" rel="noopener" aria-label="Follow us on TikTok">
            <img src="/static/images/tiktok.png" alt="TikTok">
          </a>
          <a class="icon-btn" href="https://www.instagram.com/tracklly/#" target="_blank" rel="noopener" aria-label="Follow us on Instagram">
            <img src="/static/images/instagram.png" alt="Instagram">
          </a>
        </div>
      </div>
    </footer>

  </div>

  <script>
    // Show/Hide password toggles using your PNG icons
    function togglePass(inputId, eyeId){
      const ipt = document.getElementById(inputId);
      const img = document.getElementById(eyeId);
      const showing = ipt.type === 'text';
      ipt.type = showing ? 'password' : 'text';
      img.src = showing ? '/static/images/eye.png' : '/static/images/no%20eye.png'; // note the space in filename
      const btn = img.parentElement;
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      btn.title = showing ? 'Show password' : 'Hide password';
    }
  </script>
</body>
</html>