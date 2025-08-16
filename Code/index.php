<?php
// /index.php — Tracklly landing w/ Feedback + sticky header + animated "saasy-dark" reveals

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Auth (optional CTAs)
require_once __DIR__ . '/auth/auth.php';
$is_logged_in = function_exists('is_logged_in') ? is_logged_in() : false;

/* ---------- DB: try api/db.php then fallback to api/config.php ---------- */
$pdo = $pdo ?? null;
if (file_exists(__DIR__ . '/api/db.php')) {
  require_once __DIR__ . '/api/db.php'; // should define $pdo
}
if (!($pdo instanceof PDO) && file_exists(__DIR__ . '/api/config.php')) {
  require_once __DIR__ . '/api/config.php'; // DB_HOST, DB_NAME, DB_USER, DB_PASS
  if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
      $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) { /* leave $pdo null */ }
  }
}

// CSRF for feedback
if (empty($_SESSION['fb_csrf'])) {
  $_SESSION['fb_csrf'] = bin2hex(random_bytes(16));
}

function url_for(string $name): string {
  $map = [
    'auth.login'    => '/auth/login.php',
    'auth.register' => '/auth/register.php',
    'app.home'      => '/pages/applications.php',
  ];
  return $map[$name] ?? '#';
}

/* ---- Image paths ---- */
$prev_dir  = __DIR__ . '/static/images/previews';
$hero_src  = file_exists($prev_dir.'/preview.png')      ? '/static/images/previews/preview.png'      : '';
$app_src   = file_exists($prev_dir.'/applications.png') ? '/static/images/previews/applications.png' : '';
$dash_src  = file_exists($prev_dir.'/dashboard.png')    ? '/static/images/previews/dashboard.png'    : '';
$stats_src = file_exists($prev_dir.'/stats.png')        ? '/static/images/previews/stats.png'        : '';

/* ---- Handle feedback POST ---- */
$flash_ok = null;
$flash_err = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['fb_submit'])) {
  $honeypot = trim($_POST['website'] ?? '');
  if ($honeypot !== '') {
    $flash_ok = "Thanks for your feedback!";
  } else {
    $csrf_ok = isset($_POST['csrf']) && hash_equals($_SESSION['fb_csrf'] ?? '', $_POST['csrf']);
    if (!$csrf_ok) {
      $flash_err = "Something went wrong. Please refresh and try again.";
    } elseif (!($pdo instanceof PDO)) {
      $flash_err = "Could not save your feedback. Please try again later.";
    } else {
      $name    = trim($_POST['name'] ?? '');
      $message = trim($_POST['message'] ?? '');
      if ($message === '' || mb_strlen($message) < 5) {
        $flash_err = "Please write at least 5 characters.";
      } elseif (mb_strlen($message) > 2000) {
        $flash_err = "Your message is too long (max 2000 characters).";
      } else {
        $name = $name !== '' ? mb_substr(strip_tags($name), 0, 120) : null;
        try {
          $stmt = $pdo->prepare("INSERT INTO site_feedback (name, message, ip, ua) VALUES (:name, :message, :ip, :ua)");
          $stmt->execute([
            ':name'    => $name,
            ':message' => $message,
            ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'      => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
          ]);
          $flash_ok = "Thanks for your feedback!";
          $_SESSION['fb_csrf'] = bin2hex(random_bytes(16));
          $_POST = [];
        } catch (Throwable $e) {
          $flash_err = "Could not save your feedback. Please try again later.";
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tracklly · Job Application & Interview Tracker</title>
  <meta name="description" content="Tracklly helps you track applications, interviews, offers, notes and follow-ups. Fast search, status filters, and clear stats in one place. Free forever." />
  <link rel="icon" type="image/png" href="/static/images/icon2.png" />

  <style>
    :root{
      --bg:#0b0f1a; --bg2:#0a1220; --panel:#0f1626; --muted:#9aa4b2; --text:#eaf2ff;
      --border:#1e2a3b; --primary:#3b82f6; --primary-2:#2563eb;
      --radius:16px; --radius-sm:12px; --nav-h:64px;
      --e1: cubic-bezier(.22,.61,.36,1); /* snappy ease */
      --e2: cubic-bezier(.16,1,.3,1);    /* over-shoot ease */
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; color:var(--text);
      font:16px/1.6 ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.12), transparent 60%),
        linear-gradient(180deg,var(--bg),var(--bg2));
    }
    a{color:inherit;text-decoration:none}
    img{max-width:100%;display:block}

    /* --- Header (sticky) --- */
    .nav{
      position:sticky; top:0; z-index:100;
      display:flex; align-items:center; justify-content:space-between; gap:16px;
      height:var(--nav-h); padding:12px 20px;
      background:rgba(10,18,32,.72); backdrop-filter:blur(10px);
      border-bottom:1px solid var(--border);
      transition:background .25s var(--e1), box-shadow .25s var(--e1);
    }
    .nav.scrolled{background:rgba(10,18,32,.82); box-shadow:0 2px 20px rgba(0,0,0,.35)}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.2px}
    .brand img{width:42px;height:42px;border-radius:10px}
    .links{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
    .link{
      padding:8px 12px;border-radius:12px;color:var(--muted);font-weight:700;
      border:1px solid transparent; transition:.2s var(--e1);
    }
    .link:hover{background:rgba(255,255,255,.06);color:#fff}
    .link.active{
      color:#fff; background:#0b1222; border-color:var(--border);
      box-shadow: inset 0 0 0 1px rgba(59,130,246,.18), 0 2px 8px rgba(0,0,0,.25);
    }
    .actions{display:flex;gap:10px}
    .btn,.btn-outline{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 16px;border-radius:12px;font-weight:800;border:1px solid rgba(255,255,255,.06);
      transition: transform .18s var(--e2), filter .18s var(--e2)
    }
    .btn{background:linear-gradient(180deg,var(--primary),var(--primary-2));color:#fff}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
    .btn:active{transform:translateY(0)}
    .btn-outline{background:rgba(255,255,255,.04);border-color:var(--border);color:#fff}
    .btn-outline:hover{transform:translateY(-1px);filter:brightness(1.08)}

    /* --- Sections --- */
    .section{padding:64px 20px; scroll-margin-top:calc(var(--nav-h) + 12px);}
    .limiter{max-width:1320px;margin:0 auto}

    /* --- Hero --- */
    .hero{display:grid;grid-template-columns:1fr 1.2fr;gap:28px;align-items:center}
    @media (max-width:960px){ .hero{grid-template-columns:1fr} }

    .kicker{display:inline-block;color:#cfe1ff;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);padding:6px 10px;border-radius:999px;font-weight:800;font-size:.9rem}
    .title{margin:12px 0 8px 0;font-size:46px;line-height:1.08;font-weight:900}
    @media (max-width:600px){ .title{font-size:34px} }
    .subtitle{color:#c9d3e1;max-width:680px}
    .cta-row{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap}

    .decor{position:relative;border:1px solid var(--border);border-radius:var(--radius);padding:14px;background:var(--panel);box-shadow:0 22px 50px rgba(0,0,0,.35);overflow:hidden;transform-style:preserve-3d}
    .decor::before,.decor::after{content:"";position:absolute;border-radius:50%;filter:blur(30px);opacity:.55;pointer-events:none;z-index:0}
    .decor::before{width:380px;height:220px;left:-80px;top:-40px;background:radial-gradient(closest-side, rgba(59,130,246,.30), transparent 65%)}
    .decor::after{width:420px;height:260px;right:-120px;bottom:-60px;background:radial-gradient(closest-side, rgba(37,99,235,.22), transparent 65%)}
    .canvas{position:relative;z-index:1;border-radius:12px;background:linear-gradient(135deg,#15223a,#0f1f37);overflow:hidden}

    .hero-canvas{aspect-ratio:16/9;width:100%;will-change:transform}
    .hero-canvas img{width:100%;height:100%;object-fit:contain;object-position:center;display:block;transform:translateZ(0)}

    /* --- Previews & Cards --- */
    .shot{height:clamp(420px,45vw,560px);will-change:transform}
    .shot img{width:100%;height:100%;object-fit:contain}
    .ph{position:absolute;inset:0;display:grid;place-items:center;color:#cfe1ff;font-weight:800;letter-spacing:.2px}

    .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
    @media (max-width:1100px){ .grid3{grid-template-columns:1fr 1fr} }
    @media (max-width:720px){ .grid3{grid-template-columns:1fr} }
    .card{background:transparent;border:0;padding:0;transition:transform .25s var(--e2), box-shadow .25s var(--e2)}
    .card:hover .decor{transform:translateY(-3px) rotateX(.5deg) rotateY(-.5deg)}
    .label{margin:12px 2px 0 2px;font-weight:800}
    .muted{color:var(--muted)}

    /* Pricing */
    .price-wrap{display:grid;grid-template-columns:1fr;gap:16px}
    .price{display:block;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:transform .25s var(--e2)}
    .price:hover{transform:translateY(-3px)}

    .tag{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);color:#cfe1ff;font-weight:800;font-size:.85rem}
    .big{font-size:42px;font-weight:900}
    .bullets{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    @media (max-width:600px){ .bullets{grid-template-columns:1fr} }
    .bullet{display:flex;gap:8px;align-items:flex-start}
    .check{color:#8bffb1}

    /* FAQ + Feedback */
    .faq .qa{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px;transition:transform .25s var(--e2)}
    .faq .qa + .qa{margin-top:10px}
    .faq .qa:hover{transform:translateY(-2px)}

    .flash{margin:12px 0 0 0; padding:10px 12px; border-radius:10px; font-weight:700; border:1px solid var(--border)}
    .flash.ok{background:rgba(16,185,129,.08); border-color:rgba(16,185,129,.35)}
    .flash.err{background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.35)}

    .input, .textarea{
      width:100%; background:#0b1222; color:var(--text);
      border:1px solid var(--border); border-radius:12px; padding:10px 12px; font:inherit;
    }
    .textarea{min-height:120px;resize:vertical}

    /* --- Reveal Animations --- */
    @media (prefers-reduced-motion:no-preference){
      .reveal{opacity:0; transform:translateY(26px); transition:opacity .7s var(--e1), transform .7s var(--e1)}
      .reveal.is-inview{opacity:1; transform:none}
      .reveal-left{opacity:0; transform:translateX(-26px); transition:opacity .7s var(--e1), transform .7s var(--e1)}
      .reveal-left.is-inview{opacity:1; transform:none}
      .reveal-right{opacity:0; transform:translateX(26px); transition:opacity .7s var(--e1), transform .7s var(--e1)}
      .reveal-right.is-inview{opacity:1; transform:none}
      .delay-1{transition-delay:.08s} .delay-2{transition-delay:.16s} .delay-3{transition-delay:.24s}
      .floaty{transform:translateY(0); animation:floaty 8s var(--e1) infinite}
      @keyframes floaty{0%{transform:translateY(0)}50%{transform:translateY(-8px)}100%{transform:translateY(0)}}
      .parallax{will-change:transform}
    }

    /* Footer */
    footer{padding:24px 10px 36px;border-top:1px solid var(--border);margin-top:26px;text-align:center}
    .social{display:flex;gap:10px;justify-content:center;margin-top:8px}
    .icon-btn{width:40px;height:40px;border-radius:50%;display:inline-grid;place-items:center;background:#0b1222;border:1px solid var(--border);transition:.15s transform,.15s box-shadow,.15s filter}
    .icon-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(0,0,0,.35);filter:brightness(1.05)}
    .icon-btn img{width:22px;height:22px;display:block}
  </style>
</head>
<body>

  <!-- Header -->
  <header class="nav" id="site-nav">
    <a class="brand reveal-left is-inview" href="/">
      <img src="/static/images/icon2.png" alt="Tracklly">
      <span>Tracklly</span>
    </a>
    <nav class="links" aria-label="Main">
      <a class="link" href="#about"    data-target="about">About</a>
      <a class="link" href="#previews" data-target="previews">Previews</a>
      <a class="link" href="#features" data-target="features">Features</a>
      <a class="link" href="#pricing"  data-target="pricing">Pricing</a>
      <a class="link" href="#faq"      data-target="faq">FAQ</a>
      <a class="link" href="#feedback" data-target="feedback">Feedback</a>
    </nav>
    <div class="actions reveal-right is-inview">
      <?php if ($is_logged_in): ?>
        <a class="btn" href="<?= url_for('app.home') ?>">Open App</a>
      <?php else: ?>
        <a class="btn-outline" href="<?= url_for('auth.login') ?>">Login</a>
        <a class="btn" href="<?= url_for('auth.register') ?>">Get Started</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- HERO -->
  <section class="section" id="about">
    <div class="limiter hero">
      <div class="reveal">
        <span class="kicker">INTERVIEW TRACKER · FREE FOREVER</span>
        <h1 class="title">Track your job hunt.<br>From apply to offer.</h1>
        <p class="subtitle">
          Add jobs, set status (Pending · Interview · Accepted · Rejected · No Answer), save the job link and notes,
          plan follow-ups with a Next Action Date, and see your totals and success rate at a glance.
        </p>
        <div class="cta-row">
          <?php if ($is_logged_in): ?>
            <a class="btn" href="<?= url_for('app.home') ?>">Go to Dashboard</a>
          <?php else: ?>
            <a class="btn delay-1" href="<?= url_for('auth.register') ?>">Create Free Account</a>
            <a class="btn-outline delay-2" href="<?= url_for('auth.login') ?>">I already have an account</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="decor reveal-right delay-1">
        <div class="canvas hero-canvas parallax" data-parallax="8">
          <?php if ($hero_src): ?>
            <img class="floaty" src="<?= htmlspecialchars($hero_src) ?>" alt="Tracklly preview">
          <?php else: ?><div class="ph">Tracklly preview</div><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- PREVIEWS -->
  <section class="section" id="previews">
    <div class="limiter">
      <h2 class="title reveal" style="font-size:28px;margin:0 0 10px 0;">See Tracklly in action</h2>
      <p class="muted reveal delay-1" style="margin:0 0 16px 0;">
        Real screens from the app—Applications, Dashboard, and Stats—built to keep your search organized and measurable.
      </p>

      <div class="grid3">
        <!-- Applications -->
        <div class="card reveal">
          <div class="decor">
            <div class="canvas shot parallax" data-parallax="6">
              <?php if ($app_src): ?>
                <img src="<?= htmlspecialchars($app_src) ?>" alt="Applications preview">
              <?php else: ?><div class="ph">Applications preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Applications</div>
          <p class="muted">
            Add new roles, search instantly, and switch filters for Pending, Interview, Accepted, Rejected or No Answer.
            Save job link, source, location, salary range and notes for each application.
          </p>
        </div>

        <!-- Dashboard -->
        <div class="card reveal delay-1">
          <div class="decor">
            <div class="canvas shot parallax" data-parallax="6">
              <?php if ($dash_src): ?>
                <img src="<?= htmlspecialchars($dash_src) ?>" alt="Dashboard preview">
              <?php else: ?><div class="ph">Dashboard preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Dashboard</div>
          <p class="muted">
            See totals (offers, interviews, all applications), success rate, and recent activity—so you always know
            what moved last and where to focus next.
          </p>
        </div>

        <!-- Stats -->
        <div class="card reveal delay-2">
          <div class="decor">
            <div class="canvas shot parallax" data-parallax="6">
              <?php if ($stats_src): ?>
                <img src="<?= htmlspecialchars($stats_src) ?>" alt="Stats preview">
              <?php else: ?><div class="ph">Stats preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Stats</div>
          <p class="muted">
            Bar and pie charts by status help you spot trends—how many interviews you’re landing and how your pipeline
            is distributed across stages.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="section" id="features">
    <div class="limiter">
      <h2 class="title reveal" style="font-size:28px;margin:0 0 10px 0;">Why Tracklly?</h2>
      <div class="grid3">
        <div class="decor reveal" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>🧭 Organized pipeline</h3>
          <p class="muted">Statuses: Pending, Interview, Accepted, Rejected, No Answer. Update in one click and stay on top of every role.</p>
        </div></div>

        <div class="decor reveal delay-1" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📝 Rich application details</h3>
          <p class="muted">Store job link, source, location, salary range and personal notes so everything is in one place.</p>
        </div></div>

        <div class="decor reveal delay-2" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📅 Plan your next step</h3>
          <p class="muted">Use the Next Action Date to remember callbacks, interviews or follow-ups—no more missed opportunities.</p>
        </div></div>

        <div class="decor reveal" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📊 Clear insights</h3>
          <p class="muted">Counts and success rate on the dashboard plus charts on the Stats page show progress at a glance.</p>
        </div></div>

        <div class="decor reveal delay-1" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>⚡ Fast & simple</h3>
          <p class="muted">Zero clutter UI with quick search and filters to update dozens of applications without friction.</p>
        </div></div>

        <div class="decor reveal delay-2" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>✅ Always with you</h3>
          <p class="muted">Works in your browser on desktop and mobile—no installs required.</p>
        </div></div>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="section" id="pricing">
    <div class="limiter">
      <h2 class="title reveal" style="font-size:28px;margin:0 0 10px 0;">Simple pricing</h2>
      <p class="muted reveal delay-1" style="margin:0 0 16px 0;">Tracklly is free — no credit card required.</p>

      <div class="price-wrap">
        <div class="price reveal">
          <div>
            <span class="tag">Free forever</span>
            <div class="big" style="margin:8px 0 6px 0">$0 <span class="muted" style="font-size:18px">/ month</span></div>
            <p class="muted">Everything you need to manage your job hunt efficiently.</p>
            <div class="bullets" style="margin-top:10px">
              <div class="bullet"><span class="check">✔</span><span>Unlimited applications</span></div>
              <div class="bullet"><span class="check">✔</span><span>Fast search & status filters</span></div>
              <div class="bullet"><span class="check">✔</span><span>Job link, source, salary & notes</span></div>
              <div class="bullet"><span class="check">✔</span><span>Dashboard totals & success rate</span></div>
              <div class="bullet"><span class="check">✔</span><span>Charts on the Stats page</span></div>
              <div class="bullet"><span class="check">✔</span><span>Next Action Date for follow-ups</span></div>
            </div>
            <div style="margin-top:14px">
              <?php if ($is_logged_in): ?>
                <a class="btn" href="<?= url_for('app.home') ?>">Open my Tracklly</a>
              <?php else: ?>
                <a class="btn" href="<?= url_for('auth.register') ?>">Create free account</a>
                <a class="btn-outline" href="<?= url_for('auth.login') ?>" style="margin-left:8px">Login</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- FAQ -->
  <section class="section" id="faq">
    <div class="limiter faq">
      <h2 class="title reveal" style="font-size:28px;margin:0 0 10px 0;">FAQ</h2>

      <div class="qa reveal">
        <h4>Is Tracklly really free?</h4>
        <p class="muted">Yes. Track as many applications as you want for $0/month.</p>
      </div>

      <div class="qa reveal delay-1">
        <h4>What statuses can I use?</h4>
        <p class="muted">You can switch between Pending, Interview, Accepted, Rejected, and No Answer at any time.</p>
      </div>

      <div class="qa reveal delay-2">
        <h4>What information can I save for each application?</h4>
        <p class="muted">Company, position, job type, location, job link, source (e.g., LinkedIn), salary range, notes, and a Next Action Date for follow-ups.</p>
      </div>

      <div class="qa reveal">
        <h4>How do the stats work?</h4>
        <p class="muted">The Dashboard shows totals and success rate. The Stats page summarizes your pipeline with bar and pie charts by status.</p>
      </div>

      <div class="qa reveal delay-1">
        <h4>Do I need to install anything?</h4>
        <p class="muted">No. Tracklly runs in your browser on desktop and mobile—no downloads required.</p>
      </div>

      <div class="qa reveal delay-2">
        <h4>Can I export my data?</h4>
        <p class="muted">CSV export is on the roadmap. For now, copy/paste works well for lists and notes.</p>
      </div>

      <div class="qa reveal">
        <h4>Is my data private?</h4>
        <p class="muted">Your data stays in your account. Avoid sharing login credentials with others to keep it secure.</p>
      </div>
    </div>
  </section>

  <!-- FEEDBACK -->
  <section class="section" id="feedback">
    <div class="limiter faq">
      <h2 class="title reveal" style="font-size:28px;margin:0 0 10px 0;">Feedback</h2>

      <div class="qa reveal" style="margin-top:0">
        <p class="muted" style="margin:0 0 10px 0">Tell us what to improve. You can leave your name or submit anonymously.</p>

        <?php if ($flash_ok): ?>
          <div class="flash ok"><?= htmlspecialchars($flash_ok) ?></div>
        <?php elseif ($flash_err): ?>
          <div class="flash err"><?= htmlspecialchars($flash_err) ?></div>
        <?php endif; ?>

        <form method="post" action="#feedback" novalidate style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['fb_csrf']) ?>">
          <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" />

          <div style="margin-bottom:10px">
            <label>
              <span class="muted" style="display:block;margin:0 0 4px 2px">Name (optional)</span>
              <input class="input" type="text" name="name" placeholder="Your name (or leave empty)"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" maxlength="120" />
            </label>
          </div>

          <div style="margin-bottom:10px">
            <label>
              <span class="muted" style="display:block;margin:0 0 4px 2px">Message <span style="opacity:.85">(min 5, max 2000)</span></span>
              <textarea class="textarea" name="message" required
                        placeholder="Share your idea, a bug, or anything…"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </label>
          </div>

          <button class="btn" type="submit" name="fb_submit" value="1">Send Feedback</button>
        </form>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <img src="/static/images/icon2.png" alt="Tracklly" style="height:46px;margin:0 auto 6px;border-radius:10px">
    <div class="muted">&copy; <?= date('Y') ?> Tracklly. All rights reserved.</div>
    <div class="social" aria-label="Follow us">
      <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank" rel="noopener" aria-label="Follow us on TikTok">
        <img src="/static/images/tiktok.png" alt="TikTok">
      </a>
      <a class="icon-btn" href="https://www.instagram.com/tracklly/" target="_blank" rel="noopener" aria-label="Follow us on Instagram">
        <img src="/static/images/instagram.png" alt="Instagram">
      </a>
    </div>
  </footer>

  <script>
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click', e=>{
        const id=a.getAttribute('href').slice(1);
        const el=document.getElementById(id);
        if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'})}
      })
    });

    // Nav: active highlight + header shade
    const nav = document.getElementById('site-nav');
    const sectionIds = ['about','previews','features','pricing','faq','feedback'];
    const links = new Map([...document.querySelectorAll('.links .link')].map(l => [l.dataset.target, l]));

    function setActive(id){
      links.forEach(el=>el.classList.remove('active'));
      const el = links.get(id);
      if (el) el.classList.add('active');
    }
    const observer = new IntersectionObserver((entries)=>{
      let best = null;
      entries.forEach(en=>{ if(en.isIntersecting){ if(!best || en.intersectionRatio>best.intersectionRatio) best=en; }});
      if(best) setActive(best.target.id);
    }, { rootMargin: '0px 0px -45% 0px', threshold: [0.25,0.5,0.75,1] });
    sectionIds.forEach(id=>{ const el=document.getElementById(id); if(el) observer.observe(el); });
    setActive(location.hash ? location.hash.slice(1) : 'about');
    window.addEventListener('scroll', ()=>{ nav.classList.toggle('scrolled', window.scrollY>4); });

    // Reveal on scroll (adds .is-inview)
    const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    const revObs = new IntersectionObserver((ents)=>{
      ents.forEach(en=>{
        if(en.isIntersecting){
          en.target.classList.add('is-inview');
          revObs.unobserve(en.target);
        }
      });
    }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });
    revealEls.forEach(el=>revObs.observe(el));

    // Parallax: subtle mouse move on elements with data-parallax
    const parallaxEls = document.querySelectorAll('[data-parallax]');
    document.addEventListener('mousemove', (e)=>{
      const w=window.innerWidth, h=window.innerHeight;
      const x=(e.clientX - w/2)/(w/2), y=(e.clientY - h/2)/(h/2);
      parallaxEls.forEach(el=>{
        const amt=parseFloat(el.dataset.parallax||'8');
        el.style.transform = `translate3d(${x*amt}px, ${y*amt}px, 0)`;
      });
    }, {passive:true});

    // Keep focus in feedback after submit if errors
    if (location.hash === '#feedback') {
      const ta = document.querySelector('#feedback textarea');
      if (ta) ta.focus();
    }
  </script>
</body>
</html>