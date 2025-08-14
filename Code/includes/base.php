<?php
// includes/base.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---- sessions + (optional) auth helpers ---- */
require_once __DIR__ . '/../api/session.php';
if (file_exists(__DIR__ . '/../auth/auth.php')) {
  require_once __DIR__ . '/../auth/auth.php'; // gives is_logged_in(), current_user(), current_user_id()
}

/* ---- routes helper ---- */
if (!function_exists('url_for')) {
  function url_for(string $name, array $params = []): string {
      $map = [
          'home.home'                      => '/index.php',
          'applications.list_applications' => '/pages/applications.php',
          'applications.new'               => '/pages/new.php',
          'applications.set_status'        => '/pages/set_status.php',
          'stats.stats'                    => '/pages/stats.php',
          'auth.login'                     => '/auth/login.php',
          'auth.logout'                    => '/auth/logout.php',
      ];
      $path = $map[$name] ?? '#';
      if ($params) {
          $q = http_build_query($params);
          $path .= (str_contains($path, '?') ? '&' : '?') . $q;
      }
      return $path;
  }
}

/* ---- active link helper ---- */
if (!function_exists('is_active')) {
  function is_active(string $endpoint): bool {
      $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      $target = url_for($endpoint);
      if ($endpoint === 'home.home') return $uri === '/' || $uri === '/index.php';
      return $uri === $target;
  }
}

/* ---- defaults ----
   If the page already set $counts / $total, we keep them.
   Otherwise, we compute per-user counts here (if logged in). */
$counts  = $counts  ?? null;   // possibly set by the page
$total   = $total   ?? null;
$title   = $title   ?? 'Interviewly - Job Application Tracker';
$content = $content ?? '';

$me  = $_SESSION['user'] ?? null;
$uid = isset($me['id']) ? (int)$me['id'] : 0;

if ($counts === null || $total === null) {
  $scopedCounts = ['Accepted'=>0,'Interview'=>0,'Pending'=>0,'Rejected'=>0,'No Answer'=>0];
  if ($uid > 0) {
    try {
      require_once __DIR__ . '/../api/db.php'; // $pdo
      $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS c
        FROM applications
        WHERE user_id = :uid
        GROUP BY status
      ");
      $stmt->execute([':uid' => $uid]);
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s = $r['status'];
        if (isset($scopedCounts[$s])) $scopedCounts[$s] = (int)$r['c'];
      }
    } catch (Throwable $e) {
      error_log('base.php header counts error: ' . $e->getMessage());
    }
  }
  $counts = $counts ?? $scopedCounts;
  $total  = $total  ?? array_sum($scopedCounts);
}

$counts = array_merge(['Accepted'=>0,'Interview'=>0,'Pending'=>0,'Rejected'=>0,'No Answer'=>0], (array)$counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/static/images/icon2.png" />

  <!-- Your base stylesheet -->
  <link rel="stylesheet" href="/static/css/style.css" />

  <!-- Lightweight enhancements layered on top of your theme -->
  <style>
    :root{
      /* Falls back if your theme doesn't define these */
      --bg: #0b1220;
      --card: rgba(255,255,255,0.04);
      --border: rgba(255,255,255,0.1);
      --text: #e8ecf3;
      --muted: #9aa7bd;
      --primary: #8B0000; /* your noted primary from DagangID theme, still looks great here */
      --primary-500: var(--primary);
      --primary-300: #ffb3b3;
      --ring: rgba(255,255,255,0.18);
      --shadow: 0 10px 30px rgba(0,0,0,0.35);
      --radius: 14px;
      --radius-sm: 10px;
      --radius-lg: 18px;
    }

    html, body {
      background: var(--bg);
      color: var(--text);
      -webkit-font-smoothing: antialiased;
      text-rendering: optimizeLegibility;
    }

    .container{
      max-width: 1100px;
      margin: 0 auto;
      padding: 20px;
    }

    /* Header shell with subtle gradient + border */
    .combined-header{
      position: relative;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background:
        radial-gradient(1200px 500px at 10% -20%, rgba(139,0,0,0.25), transparent 60%),
        linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
      box-shadow: var(--shadow);
      overflow: clip;
      animation: cardEnter 550ms ease-out both;
    }

    @keyframes cardEnter {
      from { opacity: 0; transform: translateY(8px) scale(0.99); }
      to   { opacity: 1; transform: translateY(0)   scale(1); }
    }

    /* Navigation */
    .main-nav{
      display:flex; align-items:center; gap:16px;
      padding: 16px 18px;
      border-bottom: 1px solid var(--border);
      backdrop-filter: saturate(120%) blur(4px);
    }

    .logo a{
      display:inline-flex; align-items:center; gap:10px;
      font-weight: 700; letter-spacing: 0.2px;
    }

    .logo img{
      height: 28px;
      width: 28px;
      filter: drop-shadow(0 1px 6px rgba(139,0,0,0.4));
      transform: translateZ(0);
    }

    .nav-links{
      display:flex; gap: 8px;
      margin-left: 6px;
    }

    .nav-link{
      position: relative;
      padding: 8px 12px;
      border-radius: 10px;
      color: var(--muted);
      text-decoration: none;
      transition: color .2s ease, background-color .2s ease, transform .2s ease;
    }

    .nav-link:hover{ color: #fff; background: rgba(255,255,255,0.04); transform: translateY(-1px); }

    .nav-link.active{
      color: #fff;
      background: linear-gradient(180deg, rgba(139,0,0,0.25), rgba(139,0,0,0.15));
      border: 1px solid rgba(139,0,0,0.35);
      box-shadow:
        inset 0 0 0 1px rgba(255,255,255,0.04),
        0 6px 18px rgba(139,0,0,0.18);
    }

    .nav-auth .btn, .nav-auth .btn-outline{
      display:inline-flex; align-items:center; gap:8px;
      padding: 8px 12px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      transition: transform .2s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
    }

    .btn{
      background: linear-gradient(180deg, var(--primary-500), #5a0000);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.08);
      box-shadow: 0 10px 20px rgba(139,0,0,0.25), inset 0 0 0 1px rgba(255,255,255,0.06);
    }

    .btn:hover{ transform: translateY(-1px); box-shadow: 0 12px 24px rgba(139,0,0,0.3); }

    .btn-outline{
      border: 1px solid var(--border);
      color: #fff; background: rgba(255,255,255,0.03);
    }

    .btn-outline:hover{ border-color: rgba(255,255,255,0.2); transform: translateY(-1px); }

    .muted{ color: var(--muted); }

    /* Hero */
    .hero-content{
      padding: 26px 22px 28px;
      display: grid;
      grid-template-columns: 1.2fr .8fr;
      gap: 16px;
      align-items: center;
    }

    .subtitle{
      margin: 0 0 10px 0;
      color: #eaeef7;
      font-size: 1.05rem;
      opacity: .95;
      line-height: 1.45;
      max-width: 60ch;
      animation: fadeUp .6s ease-out both .05s;
    }

    @keyframes fadeUp{
      from { opacity:0; transform: translateY(6px); }
      to   { opacity:1; transform: translateY(0); }
    }

    .hero-stats{
      display:flex; gap: 12px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .stat-item{
      min-width: 120px;
      padding: 14px 16px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: linear-gradient(180deg, rgba(255,255,255,0.035), rgba(255,255,255,0.02));
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
      isolation: isolate;
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
      animation: fadeUp .6s ease-out both .12s;
    }

    .stat-item::after{
      content:"";
      position:absolute; inset:-1px;
      border-radius: inherit;
      background: conic-gradient(from 180deg at 50% 50%, rgba(139,0,0,0.15), transparent 40%, rgba(255,255,255,0.06) 60%, transparent 80%, rgba(139,0,0,0.15));
      filter: blur(12px);
      opacity:.35;
      z-index:-1;
      transition: opacity .25s ease;
    }

    .stat-item:hover{ transform: translateY(-2px); border-color: rgba(139,0,0,0.35); }

    .stat-number{
      font-size: 1.8rem; font-weight: 800;
      letter-spacing: 0.3px;
      display: block;
      line-height: 1.1;
    }

    .stat-label{
      color: var(--muted); font-size: .9rem;
    }

    /* Main content card */
    .main-content{
      margin-top: 18px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px;
      background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02));
      box-shadow: var(--shadow);
      animation: cardEnter 500ms ease-out both .05s;
    }

    /* Footer */
    .main-footer{
      text-align: center;
      color: var(--muted);
      padding: 18px 10px 8px;
      font-size: .9rem;
      opacity: .85;
    }

    /* Focus styles for accessibility */
    a, button, [role="button"]{
      outline: none;
    }
    a:focus-visible, .btn:focus-visible, .btn-outline:focus-visible, .nav-link:focus-visible{
      box-shadow: 0 0 0 3px var(--ring);
    }

    /* Mobile */
    @media (max-width: 800px){
      .hero-content{
        grid-template-columns: 1fr;
      }
      .hero-stats{
        justify-content: flex-start;
      }
      .nav-links{
        display:none; /* keeps header clean on small screens; feel free to swap to a burger later */
      }
      .logo img{ height:24px; width:24px; }
    }

    /* Respect reduced motion */
    @media (prefers-reduced-motion: reduce){
      *{ animation: none !important; transition: none !important; }
    }
  </style>
</head>
<body>
  <div class="container">
    <header class="combined-header">
      <nav class="main-nav" aria-label="Primary">
        <h1 class="logo">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>" style="text-decoration:none;color:inherit">
            <img src="/static/images/icon2.png" alt="Interviewly logo">
            Interviewly
          </a>
        </h1>

        <div class="nav-links" role="navigation" aria-label="Sections">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>"
             class="nav-link <?= is_active('home.home') ? 'active' : '' ?>"
             <?= is_active('home.home') ? 'aria-current="page"' : '' ?>>Home</a>

          <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>"
             class="nav-link <?= is_active('applications.list_applications') ? 'active' : '' ?>"
             <?= is_active('applications.list_applications') ? 'aria-current="page"' : '' ?>>Applications</a>

          <a href="<?= htmlspecialchars(url_for('stats.stats')) ?>"
             class="nav-link <?= is_active('stats.stats') ? 'active' : '' ?>"
             <?= is_active('stats.stats') ? 'aria-current="page"' : '' ?>>Stats</a>
        </div>

        <div class="nav-auth" style="margin-left:auto; display:flex; align-items:center; gap:10px;">
          <?php if (!empty($me)): ?>
            <span class="muted" style="white-space:nowrap;">
              Hello, <?= htmlspecialchars($me['username'] ?? $me['email'] ?? 'User') ?>
            </span>
            <a class="btn-outline" href="<?= htmlspecialchars(url_for('auth.logout')) ?>">Logout</a>
          <?php else: ?>
            <?php
              $next = $_SERVER['REQUEST_URI'] ?? '/index.php';
              $loginUrl = url_for('auth.login', ['next' => $next]);
            ?>
            <a class="btn" href="<?= htmlspecialchars($loginUrl) ?>">Login</a>
          <?php endif; ?>
        </div>
      </nav>

      <div class="hero-content">
        <p class="subtitle">
          Track your job applications and interviews in one place
        </p>
        <div class="hero-stats" aria-label="Quick stats">
          <div class="stat-item">
            <span class="stat-number js-count" data-to="<?= (int)($counts['Accepted'] ?? 0) ?>">0</span>
            <span class="stat-label">Offers</span>
          </div>
          <div class="stat-item">
            <span class="stat-number js-count" data-to="<?= (int)($counts['Interview'] ?? 0) ?>">0</span>
            <span class="stat-label">Interviews</span>
          </div>
          <div class="stat-item">
            <span class="stat-number js-count" data-to="<?= (int)$total ?>">0</span>
            <span class="stat-label">Total</span>
          </div>
        </div>
      </div>
    </header>

    <main class="main-content" id="content">
      <?= $content ?>
    </main>

    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Interviewly. All rights reserved.</p>
    </footer>
  </div>

  <!-- Tiny, dependency-free enhancements -->
  <script>
    (function(){
      // Count-up for hero stats (respects reduced motion)
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) {
        document.querySelectorAll('.js-count').forEach(el => {
          el.textContent = el.getAttribute('data-to') || '0';
        });
        return;
      }

      const easeOut = t => 1 - Math.pow(1 - t, 3); // cubic easeOut
      const dur = 900;

      function countUp(el){
        const end = parseInt(el.getAttribute('data-to') || '0', 10);
        const start = 0;
        const startTime = performance.now();

        function frame(now){
          const p = Math.min(1, (now - startTime) / dur);
          const val = Math.round(start + (end - start) * easeOut(p));
          el.textContent = val.toLocaleString();
          if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
      }

      const items = document.querySelectorAll('.js-count');
      // Trigger only when visible
      const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries, obs)=>{
        entries.forEach(e=>{
          if(e.isIntersecting){
            countUp(e.target);
            obs.unobserve(e.target);
          }
        });
      }, { threshold: 0.6 }) : null;

      items.forEach(el=>{
        if(io){ io.observe(el); } else { countUp(el); }
      });
    })();
  </script>
</body>
</html>