<?php
// /index.php — Public landing page for Tracklly
// Hero + three previews (applications / dashboard / stats)
// Single-line borders, larger previews, and matching background decorations.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Optional: adapt CTAs when logged in
require_once __DIR__ . '/auth/auth.php';
$is_logged_in = function_exists('is_logged_in') ? is_logged_in() : false;

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tracklly · Job Application & Interview Tracker</title>
  <meta name="description" content="Tracklly keeps your job applications, interviews and offers organized — from apply to offer. Free forever." />
  <link rel="icon" type="image/png" href="/static/images/icon2.png" />

  <style>
    :root{
      --bg:#0b0f1a; --bg2:#0a1220; --panel:#0f1626; --muted:#9aa4b2; --text:#eaf2ff;
      --border:#1e2a3b; --primary:#3b82f6; --primary-2:#2563eb;
      --radius:16px; --radius-sm:12px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--text);font:16px/1.6 ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
    a{color:inherit;text-decoration:none}
    img{max-width:100%;display:block}

    /* Header */
    .nav{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 20px;background:rgba(10,18,32,.6);backdrop-filter:blur(10px);border-bottom:1px solid var(--border)}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.2px}
    .brand img{width:42px;height:42px;border-radius:10px}
    .links{display:flex;gap:14px;flex-wrap:wrap}
    .link{padding:8px 10px;border-radius:10px;color:var(--muted);font-weight:700}
    .link:hover{background:rgba(255,255,255,.06);color:#fff}
    .actions{display:flex;gap:10px}
    .btn,.btn-outline{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:12px;font-weight:800;border:1px solid rgba(255,255,255,.06);transition:.15s transform,.15s filter}
    .btn{background:linear-gradient(180deg,var(--primary),var(--primary-2));color:#fff}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
    .btn-outline{background:rgba(255,255,255,.04);border-color:var(--border);color:#fff}
    .btn-outline:hover{transform:translateY(-1px);filter:brightness(1.08)}

    /* Sections */
    .section{padding:48px 20px}
    .limiter{max-width:1200px;margin:0 auto}

    /* Hero layout (make preview bigger) */
    .hero{
      display:grid;
      grid-template-columns: 1fr 1.2fr;   /* more space for the preview */
      gap:28px; align-items:center;
    }
    @media (max-width:960px){ .hero{grid-template-columns:1fr} }

    .kicker{display:inline-block;color:#cfe1ff;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);padding:6px 10px;border-radius:999px;font-weight:800;font-size:.9rem}
    .title{margin:12px 0 8px 0;font-size:46px;line-height:1.08;font-weight:900}
    @media (max-width:600px){ .title{font-size:34px} }
    .subtitle{color:#c9d3e1;max-width:620px}
    .cta-row{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap}

    /* === DECORATED FRAME (single border line) === */
    .decor{
      position:relative;
      border:1px solid var(--border);             /* single line border */
      border-radius:var(--radius);
      padding:14px;
      background:var(--panel);
      box-shadow:0 22px 50px rgba(0,0,0,.35);
      overflow:hidden;
    }
    /* Glow decorations (shared by hero & cards) */
    .decor::before,
    .decor::after{
      content:"";
      position:absolute;
      border-radius:50%;
      filter:blur(30px);
      opacity:.55;
      pointer-events:none;
      z-index:0;
    }
    .decor::before{
      width:380px;height:220px;
      left:-80px; top:-40px;
      background:radial-gradient(closest-side, rgba(59,130,246,.30), transparent 65%);
    }
    .decor::after{
      width:420px;height:260px;
      right:-120px; bottom:-60px;
      background:radial-gradient(closest-side, rgba(37,99,235,.22), transparent 65%);
    }

    /* Inner canvas that contains the image; no dashed line anymore */
    .canvas{
      position:relative; z-index:1;
      border-radius:12px;
      background:linear-gradient(135deg,#15223a,#0f1f37);
      overflow:hidden;
    }

    /* Hero image: keep full view, no cropping */
    .hero-canvas{aspect-ratio: 16/9; width:100%}
    .hero-canvas img{
      width:100%; height:100%;
      object-fit:contain; object-position:center;
      display:block;
    }

    /* Standard previews */
    .shot{
      height: clamp(320px, 38vw, 440px); /* bigger previews */
    }
    .shot img{width:100%;height:100%;object-fit:contain}  /* contain to avoid crop */
    .ph{position:absolute;inset:0;display:grid;place-items:center;color:#cfe1ff;font-weight:800;letter-spacing:.2px}

    /* Cards grid */
    .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    @media (max-width:1100px){ .grid3{grid-template-columns:1fr 1fr} }
    @media (max-width:720px){ .grid3{grid-template-columns:1fr} }
    .card{background:transparent;border:0;padding:0}
    .label{margin:12px 2px 0 2px;font-weight:800}
    .muted{color:var(--muted)}

    /* Pricing */
    .price-wrap{display:grid;grid-template-columns:1fr;gap:16px}
    .price{
      display:grid;grid-template-columns:1fr minmax(260px,460px);gap:18px;align-items:center;
      background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:18px
    }
    @media (max-width:900px){ .price{grid-template-columns:1fr} }
    .tag{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);color:#cfe1ff;font-weight:800;font-size:.85rem}
    .big{font-size:42px;font-weight:900}
    .bullets{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    @media (max-width:600px){ .bullets{grid-template-columns:1fr} }
    .bullet{display:flex;gap:8px;align-items:flex-start}
    .check{color:#8bffb1}

    /* FAQ + Footer */
    .faq .qa{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px}
    .faq .qa h4{margin:0 0 6px 0}
    footer{padding:24px 10px 36px;border-top:1px solid var(--border);margin-top:26px}
    .social{display:flex;gap:10px;justify-content:center;margin-top:8px}
    .icon-btn{width:40px;height:40px;border-radius:50%;display:inline-grid;place-items:center;background:#0b1222;border:1px solid var(--border);transition:.15s transform,.15s box-shadow,.15s filter}
    .icon-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(0,0,0,.35);filter:brightness(1.05)}
    .icon{width:22px;height:22px;display:block}
  </style>
</head>
<body>

  <!-- Header -->
  <header class="nav">
    <a class="brand" href="/">
      <img src="/static/images/icon2.png" alt="Tracklly">
      <span>Tracklly</span>
    </a>
    <nav class="links" aria-label="Main">
      <a class="link" href="#about">About</a>
      <a class="link" href="#previews">Previews</a>
      <a class="link" href="#features">Features</a>
      <a class="link" href="#pricing">Pricing</a>
      <a class="link" href="#faq">FAQ</a>
    </nav>
    <div class="actions">
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
      <div>
        <span class="kicker">INTERVIEW TRACKER · FREE FOREVER</span>
        <h1 class="title">Track your job hunt.<br>From apply to offer.</h1>
        <p class="subtitle">
          Tracklly keeps your applications, interviews, and offers organized in a clean, fast UI.
          No spreadsheets. No chaos. Just clarity.
        </p>
        <div class="cta-row">
          <?php if ($is_logged_in): ?>
            <a class="btn" href="<?= url_for('app.home') ?>">Go to Dashboard</a>
          <?php else: ?>
            <a class="btn" href="<?= url_for('auth.register') ?>">Create Free Account</a>
            <a class="btn-outline" href="<?= url_for('auth.login') ?>">I already have an account</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bigger hero preview, single border, with shared decorations -->
      <div class="decor">
        <div class="canvas hero-canvas">
          <?php if ($hero_src): ?>
            <img src="<?= htmlspecialchars($hero_src) ?>" alt="Tracklly preview">
          <?php else: ?>
            <div class="ph">Tracklly preview</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- PREVIEWS (Applications / Dashboard / Stats) -->
  <section class="section" id="previews">
    <div class="limiter">
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">See Tracklly in action</h2>
      <p class="muted" style="margin:0 0 16px 0;">
        Place screenshots in <code>/static/images/previews/</code>:
        <code>preview.png</code> (hero), <code>applications.png</code>, <code>dashboard.png</code>, <code>stats.png</code>.
      </p>

      <div class="grid3">
        <!-- Applications -->
        <div class="card">
          <div class="decor">
            <div class="canvas shot">
              <?php if ($app_src): ?>
                <img src="<?= htmlspecialchars($app_src) ?>" alt="Applications preview">
              <?php else: ?><div class="ph">Applications preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Applications</div>
          <p class="muted">Search, filter, and update status fast. One place for every job you’ve applied to.</p>
        </div>

        <!-- Dashboard -->
        <div class="card">
          <div class="decor">
            <div class="canvas shot">
              <?php if ($dash_src): ?>
                <img src="<?= htmlspecialchars($dash_src) ?>" alt="Dashboard preview">
              <?php else: ?><div class="ph">Dashboard preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Dashboard</div>
          <p class="muted">Snapshot of offers, interviews and totals at a glance.</p>
        </div>

        <!-- Stats -->
        <div class="card">
          <div class="decor">
            <div class="canvas shot">
              <?php if ($stats_src): ?>
                <img src="<?= htmlspecialchars($stats_src) ?>" alt="Stats preview">
              <?php else: ?><div class="ph">Stats preview</div><?php endif; ?>
            </div>
          </div>
          <div class="label">Stats</div>
          <p class="muted">Understand success rate and trends to focus where it matters.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="section" id="features">
    <div class="limiter">
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">Why Tracklly?</h2>
      <div class="grid3">
        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>🧭 Organized pipeline</h3>
          <p class="muted">Stages: Applied, Interview, Offer, Rejected, No Answer — always up to date.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>⏰ Never miss interviews</h3>
          <p class="muted">Capture dates, locations and follow-ups in seconds.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>⚡ Fast & simple</h3>
          <p class="muted">Zero clutter. Keyboard-friendly, one-click actions.</p>
        </div></div>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="section" id="pricing">
    <div class="limiter">
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">Simple pricing</h2>
      <p class="muted" style="margin:0 0 16px 0;">Tracklly is free — no credit card required.</p>

      <div class="price-wrap">
        <div class="price">
          <div>
            <span class="tag">Free forever</span>
            <div class="big" style="margin:8px 0 6px 0">$0 <span class="muted" style="font-size:18px">/ month</span></div>
            <p class="muted">Everything you need to manage your job hunt efficiently.</p>
            <div class="bullets" style="margin-top:10px">
              <div class="bullet"><span class="check">✔</span><span>Unlimited applications</span></div>
              <div class="bullet"><span class="check">✔</span><span>Status & filters</span></div>
              <div class="bullet"><span class="check">✔</span><span>Notes & job links</span></div>
              <div class="bullet"><span class="check">✔</span><span>Stats overview</span></div>
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

          <div class="decor">
            <div class="canvas shot">
              <?php if ($stats_src): ?>
                <img src="<?= htmlspecialchars($stats_src) ?>" alt="Stats preview (pricing)">
              <?php else: ?><div class="ph">Your progress at a glance</div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- FAQ -->
  <section class="section" id="faq">
    <div class="limiter faq">
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">FAQ</h2>
      <div class="qa">
        <h4>Is Tracklly really free?</h4>
        <p class="muted">Yes. Track as many applications as you want for $0/month.</p>
      </div>
      <div class="qa" style="margin-top:10px">
        <h4>Do I need to install anything?</h4>
        <p class="muted">No installation. It runs in your browser on desktop and mobile.</p>
      </div>
      <div class="qa" style="margin-top:10px">
        <h4>Can I export my data?</h4>
        <p class="muted">CSV export is on the roadmap. For now, copy/paste works well for lists and notes.</p>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <div style="text-align:center">
      <img src="/static/images/icon2.png" alt="Tracklly" style="height:46px;margin:0 auto 6px;border-radius:10px">
      <div class="muted">&copy; <?= date('Y') ?> Tracklly. All rights reserved.</div>
      <div class="social" aria-label="Follow us">
        <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank" rel="noopener">
          <svg class="icon" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M12.9 2h3.1c.2 1.1.7 2.1 1.4 3 .8.9 1.8 1.6 2.9 2v3.2c-1.6-.1-3.1-.6-4.4-1.5v5.9c0 3.7-3 6.6-6.6 6.6S2.8 18.3 2.8 14.6c0-3.5 2.6-6.3 6-6.6v3.3c-1.5.3-2.6 1.6-2.6 3.3 0 1.9 1.5 3.4 3.4 3.4 1.9 0 3.4-1.5 3.4-3.4V2z"/></svg>
        </a>
        <a class="icon-btn" href="https://www.instagram.com/tracklly/#" target="_blank" rel="noopener">
          <svg class="icon" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm5.6-.9a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2z"/></svg>
        </a>
      </div>
    </div>
  </footer>

  <script>
    // Smooth in-page scrolling
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click', e=>{
        const id = a.getAttribute('href').slice(1);
        const el = document.getElementById(id);
        if(el){ e.preventDefault(); el.scrollIntoView({behavior:'smooth', block:'start'}); }
      });
    });
  </script>
</body>
</html>