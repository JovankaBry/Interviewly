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
  // Only compute if we can scope to a user (logged in)
  $scopedCounts = ['Accepted'=>0,'Interview'=>0,'Pending'=>0,'Rejected'=>0,'No Answer'=>0];
  if ($uid > 0) {
    // Try to load from DB
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
      // fall back to zeros; keep site running
      error_log('base.php header counts error: ' . $e->getMessage());
    }
  }
  $counts = $counts ?? $scopedCounts;
  $total  = $total  ?? array_sum($scopedCounts);
}

// Safety: ensure keys exist
$counts = array_merge(['Accepted'=>0,'Interview'=>0,'Pending'=>0,'Rejected'=>0,'No Answer'=>0], (array)$counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/static/css/style.css">
</head>
<body>
  <div class="container">
    <header class="combined-header">
      <nav class="main-nav">
        <h1 class="logo">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>" style="text-decoration:none;color:inherit">Interviewly</a>
        </h1>

        <div class="nav-links">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>"
             class="nav-link <?= is_active('home.home') ? 'active' : '' ?>">Home</a>

          <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>"
             class="nav-link <?= is_active('applications.list_applications') ? 'active' : '' ?>">Applications</a>

          <a href="<?= htmlspecialchars(url_for('stats.stats')) ?>"
             class="nav-link <?= is_active('stats.stats') ? 'active' : '' ?>">Stats</a>
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
        <p class="subtitle">Track your job applications and interviews in one place</p>
        <div class="hero-stats">
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

    <main class="main-content">
      <?= $content ?>
    </main>

    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Interviewly. All rights reserved.</p>
    </footer>
  </div>
</body>
</html>
