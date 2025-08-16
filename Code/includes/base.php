<?php
// includes/base.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---- sessions + (optional) auth helpers ---- */
require_once __DIR__ . '/../api/session.php';
if (file_exists(__DIR__ . '/../auth/auth.php')) {
  require_once __DIR__ . '/../auth/auth.php';
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

/* ---- defaults ---- */
$counts  = $counts  ?? null;
$total   = $total   ?? null;
$title   = $title   ?? 'Tracklly - Job Application Tracker';
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="icon" type="image/png" href="/static/images/icon2.png" />
  <link rel="stylesheet" href="/static/css/style.css" />

  <style>
    :root{
      --primary:#2563eb;
      --primary-dark:#1d4ed8;
      --muted:#9ca3af;
      --text:#f8fafc;
      --bg:#0f172a;
      --border:#1e293b;
      --radius:14px;
      --radius-sm:10px;
      --card-bg:rgba(255,255,255,0.03);

      --nav-control-h: 36px;
    }

    html, body { background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
    body { margin: 0; }
    .container{ max-width:1100px; margin:0 auto; padding:16px; }

    .combined-header{
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--card-bg);
      backdrop-filter: blur(6px) saturate(115%);
      -webkit-backdrop-filter: blur(6px) saturate(115%);
      box-shadow: 0 12px 28px rgba(0,0,0,.28);
      overflow: hidden;
    }

    /* ===== NAV ===== */
    .main-nav{
      display:flex; align-items:center; gap:12px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      flex-wrap: wrap;
    }
    .logo{ margin:0; font-weight:800; font-size:1.6rem; letter-spacing:.2px; }
    .logo a{ display:inline-flex; align-items:center; gap:10px; color:inherit; text-decoration:none; }
    .logo img{ height:50px; width:50x; }

    .nav-links{
      display:flex; gap:8px; margin-left:6px;
      flex-wrap: wrap;
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
    }
    .nav-links::-webkit-scrollbar{ display:none; }

    .nav-link{
      padding:10px 12px; border-radius:10px; text-decoration:none;
      font-weight:800; color: var(--muted);
      transition: color .2s, background .2s, transform .2s;
      white-space: nowrap;
    }
    .nav-link:hover{ color:#fff; background:rgba(255,255,255,.05); transform: translateY(-1px); }
    .nav-link.active{
      color:#fff; background: rgba(37,99,235,.18);
      border:1px solid rgba(37,99,235,.35);
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.05), 0 8px 20px rgba(37,99,235,.22);
    }

    /* AUTH */
    .nav-auth{
      margin-left:auto;
      display:flex;
      align-items:center;
      gap:12px;
      min-width: 0;
    }
    .nav-auth .greeting{
      display:flex; align-items:center;
      height: var(--nav-control-h);
      line-height: 1;
      color: var(--muted);
      font-size: 1.05rem;
      font-weight: 500;
      padding: 0 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 60vw;
    }

    .btn, .btn-outline, .btn-logout{
      display:flex; align-items:center; justify-content:center;
      height: var(--nav-control-h);
      padding: 0 14px;
      border-radius:10px; font-weight:800; text-decoration:none;
      border:1px solid rgba(255,255,255,.06);
      flex: 0 0 auto;
    }
    .btn{ background: linear-gradient(180deg, var(--primary), var(--primary-dark)); color:#fff; }
    .btn-outline{ color:#fff; background: rgba(255,255,255,.03); border-color: var(--border); }

    /* red logout button */
    .btn-logout{
      background: linear-gradient(180deg, #dc2626, #b91c1c);
      border:1px solid rgba(220,38,38,.4);
      color:#fff;
    }
    .btn-logout:hover{
      background: linear-gradient(180deg, #ef4444, #dc2626);
      box-shadow: 0 6px 14px rgba(220,38,38,.4);
    }

    /* ===== HERO ===== */
    .hero{
      padding: 18px 16px;
      background: var(--card-bg);
      border-top: 1px solid var(--border);
    }
    .subtitle{ margin: 0 0 14px 0; font-size:1.05rem; color:#e9efff; }
    .hero-stats{
      display:flex; gap:14px; flex-wrap:wrap; justify-content:flex-start;
    }
    .stat-item{
      min-width:140px; padding:16px 18px;
      border-radius: var(--radius-sm); border:1px solid var(--border);
      background: rgba(255,255,255,.035); text-align: center;
    }
    .stat-label{ color: var(--muted); font-size:.9rem; text-transform: uppercase; letter-spacing:.3px; margin-bottom:6px; display:block; }
    .stat-number{ font-size:1.9rem; font-weight:800; display:block; }

    .main-content{ margin-top: 16px; border:1px solid var(--border); border-radius: var(--radius); padding:16px; background: rgba(255,255,255,.03); box-shadow: 0 12px 28px rgba(0,0,0,.28); }
    .main-footer{ text-align:center; color:var(--muted); padding:18px 10px 8px; font-size:.9rem; }

    /* Follow / Social icons */
    .social-wrap{
      margin-top:8px;
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:8px;
    }
    .social-title{
      margin:0;
      font-weight:700;
      color:#e5e7eb;
      font-size:.95rem;
    }
    .social-icons{
      display:flex;
      align-items:center;
      gap:10px;
    }
    .icon-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:36px; height:36px;
      border-radius:50%;
      background: #0b1222;
      border:1px solid var(--border);
      text-decoration:none;
      transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .icon-btn:hover{
      transform: translateY(-1px);
      box-shadow: 0 6px 14px rgba(0,0,0,.35);
      background: linear-gradient(180deg, #25f4ee, #fe2c55);
    }
    .icon-btn img{
      width:18px; height:18px;
      display:block;
      filter: brightness(1) contrast(1.05);
    }

    /* ======= Mobile tweaks ======= */
    @media (max-width: 640px){
      :root{ --nav-control-h: 34px; }
      .main-nav{ gap:10px; }

      .nav-links{
        order: 2;
        width: 100%;
        margin-left: 0;
        justify-content: center;
        gap: 6px;
      }

      .nav-auth{
        order: 3;
        width: 100%;
        margin-left: 0;
        justify-content: space-between;
        gap: 8px;
      }
      .nav-auth .greeting{ font-size: .95rem; max-width: calc(100% - 110px); }
      .btn, .btn-outline, .btn-logout{ padding: 0 12px; height: 34px; }

      .hero{ padding: 12px 12px; }
      .subtitle{ font-size: .95rem; margin-bottom: 10px; }
      .hero-stats{ display:grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px; }
      .stat-item{ min-width: 0; padding: 10px 8px; border-radius: 8px; }
      .stat-label{ font-size: .78rem; margin-bottom: 4px; }
      .stat-number{ font-size: 1.35rem; }
    }

    @media (max-width: 380px){
      .hero-stats{ grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 8px; }
      .stat-item{ padding: 9px 6px; }
      .stat-number{ font-size: 1.25rem; }
      .stat-label{ font-size: .74rem; }
      .nav-auth .greeting{ max-width: calc(100% - 100px); }
    }

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
            <img src="/static/images/icon2.png" alt="Tracklly logo">
            Tracklly
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

        <div class="nav-auth">
          <?php if (!empty($me)): ?>
            <span class="greeting">Hello, <?= htmlspecialchars($me['username'] ?? $me['email'] ?? 'User') ?></span>
            <a class="btn-logout" href="<?= htmlspecialchars(url_for('auth.logout')) ?>">Logout</a>
          <?php else: ?>
            <?php $loginUrl = url_for('auth.login', ['next' => $_SERVER['REQUEST_URI'] ?? '/index.php']); ?>
            <a class="btn" href="<?= htmlspecialchars($loginUrl) ?>">Login</a>
          <?php endif; ?>
        </div>
      </nav>

      <div class="hero">
        <p class="subtitle">Track your job applications and interviews in one place</p>
        <div class="hero-stats" aria-label="Quick stats">
          <div class="stat-item">
            <span class="stat-label">Offers</span>
            <span class="stat-number"><?= (int)($counts['Accepted'] ?? 0) ?></span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Interviews</span>
            <span class="stat-number"><?= (int)($counts['Interview'] ?? 0) ?></span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Total</span>
            <span class="stat-number"><?= (int)$total ?></span>
          </div>
        </div>
      </div>
    </header>

    <main class="main-content" id="content">
      <?= $content ?>
    </main>

    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Tracklly. All rights reserved.</p>

      <div class="social-wrap" aria-label="Follow us">
        <p class="social-title">Follow us</p>
        <div class="social-icons">
          <!-- TikTok -->
          <a class="icon-btn" href="https://www.tiktok.com/@tracklly" target="_blank" rel="noopener" aria-label="Follow us on TikTok">
            <img src="/static/images/tiktok.png" alt="TikTok">
          <!-- Instagram -->
          <a class="icon-btn" href="https://www.instagram.com/tracklly/" target="_blank" rel="noopener" aria-label="Follow us on Instagram">
            <img src="/static/images/instagram.png" alt="Instagram">
          </a>
        </div>
      </div>
    </footer>
  </div>
</body>
</html>