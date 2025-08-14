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

/* ---- defaults (no changes to data logic) ---- */
$counts  = $counts  ?? null;
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
  <!-- Important for mobile: ensures proper scaling and prevents zoom side‑effects -->
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="icon" type="image/png" href="/static/images/icon2.png" />
  <link rel="stylesheet" href="/static/css/style.css" />

  <!-- Minimal, targeted CSS to keep nav links visible and usable on phones -->
  <style>
    :root{
      /* use your theme tokens as fallbacks only */
      --primary:#2563eb;
      --primary-dark:#1d4ed8;
      --muted:#9ca3af;
      --text:#f8fafc;
      --bg:#0f172a;
      --border:#1e293b;
      --radius:14px;
      --radius-sm:10px;
    }

    html, body { background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
    body { margin: 0; }

    .container{ max-width:1100px; margin:0 auto; padding:16px; }

    .combined-header{
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: rgba(255,255,255,0.03);
      backdrop-filter: blur(6px) saturate(115%);
      -webkit-backdrop-filter: blur(6px) saturate(115%);
      box-shadow: 0 12px 28px rgba(0,0,0,.28);
      overflow: hidden;
    }

    .main-nav{
      display:flex; align-items:center; gap:12px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
    }
    .logo{ margin:0; font-weight:800; letter-spacing:.2px; font-size:1.6rem; }
    .logo a{ display:inline-flex; align-items:center; gap:10px; color:inherit; text-decoration:none; }
    .logo img{ height:26px; width:26px; }

    /* NAV LINKS: ensure visible on mobile */
    .nav-links{
      display:flex; gap:8px; margin-left:6px;
      flex-wrap: wrap;                       /* wraps to next line on narrow screens */
      max-width: 100%;
      overflow-x: auto;                      /* if long, allow horizontal scroll */
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;                 /* Firefox */
    }
    .nav-links::-webkit-scrollbar{ display:none; } /* WebKit */

    .nav-link{
      padding:10px 12px; border-radius:10px; text-decoration:none;
      font-weight:800; color: var(--muted);
      transition: color .2s, background .2s, transform .2s;
      white-space: nowrap;                   /* keep pills compact when scrolling */
    }
    .nav-link:hover{ color:#fff; background:rgba(255,255,255,.05); transform: translateY(-1px); }
    .nav-link.active{
      color:#fff; background: rgba(37,99,235,.18);
      border:1px solid rgba(37,99,235,.35);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.05), 0 8px 20px rgba(37,99,235,.22);
    }

    .nav-auth{ margin-left:auto; display:flex; align-items:center; gap:10px; }
    .nav-auth .btn, .nav-auth .btn-outline{
      display:inline-flex; align-items:center; gap:8px; padding:10px 12px;
      border-radius:10px; font-weight:800; text-decoration:none;
    }
    .btn{ background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color:#fff; border:1px solid rgba(255,255,255,.06); }
    .btn-outline{ color:#fff; background: rgba(255,255,255,.03); border:1px solid var(--border); }
    .muted{ color:var(--muted); white-space:nowrap; }

    .hero-content{
      padding: 20px 16px;
      display:grid; grid-template-columns: 1.2fr .8fr; gap: 16px; align-items:center;
    }
    .subtitle{ margin:0; color:#e9efff; opacity:.95; font-size:1.05rem; line-height:1.45; }
    .hero-stats{ display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap; }
    .stat-item{
      min-width:120px; padding: 14px 16px; border-radius: var(--radius-sm);
      border:1px solid var(--border); background: rgba(255,255,255,.035);
      box-shadow: 0 12px 28px rgba(0,0,0,.28);
    }
    .stat-number{ font-size:1.8rem; font-weight:800; line-height:1.1; display:block; }
    .stat-label{ color: var(--muted); font-size:.9rem; }

    .main-content{
      margin-top: 16px;
      border:1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      background: rgba(255,255,255,.03);
      box-shadow: 0 12px 28px rgba(0,0,0,.28);
    }
    .main-footer{ text-align:center; color:var(--muted); padding:18px 10px 8px; font-size:.9rem; }

    /* Accessibility focus */
    a:focus-visible, .btn:focus-visible, .btn-outline:focus-visible, .nav-link:focus-visible{
      outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.35);
    }

    /* ===== Mobile tweaks ONLY (do NOT hide the links) ===== */
    @media (max-width: 800px){
      .main-nav{ flex-wrap: wrap; }                 /* allow nav to break into lines */
      .logo{ font-size: 1.4rem; }
      .hero-content{ grid-template-columns: 1fr; }  /* stack hero on phones */
      .hero-stats{ justify-content:flex-start; }
      .nav-auth{ width:100%; justify-content:flex-end; } /* auth row flows under links */
    }

    /* iOS safe-area padding at the bottom so content isn’t cropped by Safari bar */
    @supports (padding: max(0px)) {
      .container { padding-bottom: max(16px, env(safe-area-inset-bottom)); }
    }
  </style>
</head>
<body>
  <div class="container">
    <header class="combined-header">
      <nav class="main-nav" aria-label="Primary">
        <h1 class="logo">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>">
            <img src="/static/images/icon2.png" alt="Interviewly logo">
            Interviewly
          </a>
        </h1>

        <!-- Links are ALWAYS visible and wrap/scroll on small screens -->
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

        <div class="nav-auth">
          <?php if (!empty($me)): ?>
            <span class="muted">Hello, <?= htmlspecialchars($me['username'] ?? $me['email'] ?? 'User') ?></span>
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
        <p class="subtitle">Track your job applications and interviews in one place</p>
        <div class="hero-stats" aria-label="Quick stats">
          <div class="stat-item">
            <span class="stat-number"><?= (int)($counts['Accepted'] ?? 0) ?></span>
            <span class="stat-label">Offers</span>
          </div>
          <div class="stat-item">
            <span class="stat-number"><?= (int)($counts['Interview'] ?? 0) ?></span>
            <span class="stat-label">Interviews</span>
          </div>
          <div class="stat-item">
            <span class="stat-number"><?= (int)$total ?></span>
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
</body>
</html>