<?php
// /index.php — Public landing page for Tracklly
// Clean marketing copy that matches your features. No pricing preview image,
// no "More features coming soon" banner, and expanded FAQ.

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

/* ---- Image paths (used for hero + previews) ---- */
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
  <meta name="description" content="Tracklly helps you track applications, interviews, offers, notes and follow-ups. Fast search, status filters, and clear stats in one place. Free forever." />
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
    .limiter{max-width:1320px;margin:0 auto}

    /* Hero layout */
    .hero{display:grid;grid-template-columns:1fr 1.2fr;gap:28px;align-items:center}
    @media (max-width:960px){ .hero{grid-template-columns:1fr} }

    .kicker{display:inline-block;color:#cfe1ff;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);padding:6px 10px;border-radius:999px;font-weight:800;font-size:.9rem}
    .title{margin:12px 0 8px 0;font-size:46px;line-height:1.08;font-weight:900}
    @media (max-width:600px){ .title{font-size:34px} }
    .subtitle{color:#c9d3e1;max-width:680px}
    .cta-row{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap}

    /* Decorated frame */
    .decor{position:relative;border:1px solid var(--border);border-radius:var(--radius);padding:14px;background:var(--panel);box-shadow:0 22px 50px rgba(0,0,0,.35);overflow:hidden}
    .decor::before,.decor::after{content:"";position:absolute;border-radius:50%;filter:blur(30px);opacity:.55;pointer-events:none;z-index:0}
    .decor::before{width:380px;height:220px;left:-80px;top:-40px;background:radial-gradient(closest-side, rgba(59,130,246,.30), transparent 65%)}
    .decor::after{width:420px;height:260px;right:-120px;bottom:-60px;background:radial-gradient(closest-side, rgba(37,99,235,.22), transparent 65%)}
    .canvas{position:relative;z-index:1;border-radius:12px;background:linear-gradient(135deg,#15223a,#0f1f37);overflow:hidden}

    .hero-canvas{aspect-ratio: 16/9;width:100%}
    .hero-canvas img{width:100%;height:100%;object-fit:contain;object-position:center;display:block}

    /* Previews */
    .shot{height:clamp(420px,45vw,560px)}
    .shot img{width:100%;height:100%;object-fit:contain}
    .ph{position:absolute;inset:0;display:grid;place-items:center;color:#cfe1ff;font-weight:800;letter-spacing:.2px}

    /* Grid */
    .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
    @media (max-width:1100px){ .grid3{grid-template-columns:1fr 1fr} }
    @media (max-width:720px){ .grid3{grid-template-columns:1fr} }
    .card{background:transparent;border:0;padding:0}
    .label{margin:12px 2px 0 2px;font-weight:800}
    .muted{color:var(--muted)}

    /* Pricing */
    .price-wrap{display:grid;grid-template-columns:1fr;gap:16px}
    .price{
      display:block; /* single-column: removed the preview image block */
      background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:18px
    }
    .tag{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.35);color:#cfe1ff;font-weight:800;font-size:.85rem}
    .big{font-size:42px;font-weight:900}
    .bullets{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    @media (max-width:600px){ .bullets{grid-template-columns:1fr} }
    .bullet{display:flex;gap:8px;align-items:flex-start}
    .check{color:#8bffb1}

    /* FAQ + Footer */
    .faq .qa{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px}
    .faq .qa h4{margin:0 0 6px 0}
    .faq .qa + .qa{margin-top:10px}
    footer{padding:24px 10px 36px;border-top:1px solid var(--border);margin-top:26px;text-align:center}
    .social{display:flex;gap:10px;justify-content:center;margin-top:8px}
    .icon-btn{width:40px;height:40px;border-radius:50%;display:inline-grid;place-items:center;background:#0b1222;border:1px solid var(--border);transition:.15s transform,.15s box-shadow,.15s filter}
    .icon-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(0,0,0,.35);filter:brightness(1.05)}
    .icon{width:22px;height:22px;display:block}
    .icon-btn img{width:22px;height:22px;display:block}
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
          Add jobs, set status (Pending · Interview · Accepted · Rejected · No Answer), save the job link and notes,
          plan follow-ups with a Next Action Date, and see your totals and success rate at a glance.
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

      <div class="decor">
        <div class="canvas hero-canvas">
          <?php if ($hero_src): ?>
            <img src="<?= htmlspecialchars($hero_src) ?>" alt="Tracklly preview">
          <?php else: ?><div class="ph">Tracklly preview</div><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- PREVIEWS -->
  <section class="section" id="previews">
    <div class="limiter">
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">See Tracklly in action</h2>
      <p class="muted" style="margin:0 0 16px 0;">
        Real screens from the app—Applications, Dashboard, and Stats—built to keep your search organized and measurable.
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
          <p class="muted">
            Add new roles, search instantly, and switch filters for Pending, Interview, Accepted, Rejected or No Answer.
            Save job link, source, location, salary range and notes for each application.
          </p>
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
          <p class="muted">
            See totals (offers, interviews, all applications), success rate, and recent activity—so you always know
            what moved last and where to focus next.
          </p>
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
      <h2 class="title" style="font-size:28px;margin:0 0 10px 0;">Why Tracklly?</h2>
      <div class="grid3">
        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>🧭 Organized pipeline</h3>
          <p class="muted">Statuses: Pending, Interview, Accepted, Rejected, No Answer. Update in one click and stay on top of every role.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📝 Rich application details</h3>
          <p class="muted">Store job link, source, location, salary range and personal notes so everything is in one place.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📅 Plan your next step</h3>
          <p class="muted">Use the Next Action Date to remember callbacks, interviews or follow-ups—no more missed opportunities.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>📊 Clear insights</h3>
          <p class="muted">Counts and success rate on the dashboard plus charts on the Stats page show progress at a glance.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>⚡ Fast & simple</h3>
          <p class="muted">Zero clutter UI with quick search and filters to update dozens of applications without friction.</p>
        </div></div>

        <div class="decor" style="padding:0"><div class="canvas" style="padding:18px">
          <h3>✅ Always with you</h3>
          <p class="muted">Works in your browser on desktop and mobile—no installs required.</p>
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
          <!-- Removed the right-side preview image on purpose -->
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

      <div class="qa">
        <h4>What statuses can I use?</h4>
        <p class="muted">You can switch between Pending, Interview, Accepted, Rejected, and No Answer at any time.</p>
      </div>

      <div class="qa">
        <h4>What information can I save for each application?</h4>
        <p class="muted">Company, position, job type, location, job link, source (e.g., LinkedIn), salary range, notes, and a Next Action Date for follow-ups.</p>
      </div>

      <div class="qa">
        <h4>How do the stats work?</h4>
        <p class="muted">The Dashboard shows totals and success rate. The Stats page summarizes your pipeline with bar and pie charts by status.</p>
      </div>

      <div class="qa">
        <h4>Do I need to install anything?</h4>
        <p class="muted">No. Tracklly runs in your browser on desktop and mobile—no downloads required.</p>
      </div>

      <div class="qa">
        <h4>Can I export my data?</h4>
        <p class="muted">CSV export is on the roadmap. For now, copy/paste works well for lists and notes.</p>
      </div>

      <div class="qa">
        <h4>Is my data private?</h4>
        <p class="muted">Your data stays in your account. Avoid sharing login credentials with others to keep it secure.</p>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <img src="/static/images/icon2.png" alt="Tracklly" style="height:46px;margin:0 auto 6px;border-radius:10px">
    <div class="muted">&copy; <?= date('Y') ?> Tracklly. All rights reserved.</div>
    <div class="social" aria-label="Follow us">
      <!-- TikTok -->
      <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank" rel="noopener" aria-label="Follow us on TikTok">
        <img src="/static/images/tiktok.png" alt="TikTok">
      </a>
      <!-- Instagram -->
      <a class="icon-btn" href="https://www.instagram.com/tracklly/" target="_blank" rel="noopener" aria-label="Follow us on Instagram">
        <img src="/static/images/instagram.png" alt="Instagram">
      </a>
    </div>
  </footer>

  <script>
    // Smooth in-page scrolling
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click', e=>{
        const id=a.getAttribute('href').slice(1);
        const el=document.getElementById(id);
        if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'})}
      })
    })
  </script>
</body>
</html>