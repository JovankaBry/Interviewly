<?php
// includes/base.php

if (!function_exists('url_for')) {
  function url_for(string $name, array $params = []): string {
      $map = [
          'home.home'                      => '/index.php',
          'applications.list_applications' => '/pages/applications.php',
          'applications.new'               => '/pages/new.php',
          'stats.stats'                    => '/pages/stats.php',
      ];
      $path = $map[$name] ?? '#';
      if ($params) {
          $q = http_build_query($params);
          $path .= (str_contains($path, '?') ? '&' : '?') . $q;
      }
      return $path;
  }
}

if (!function_exists('is_active')) {
  function is_active(string $endpoint): bool {
      $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      $target = url_for($endpoint);
      if ($endpoint === 'home.home') return $uri === '/' || $uri === '/index.php';
      return $uri === $target;
  }
}

$counts = $counts ?? ['Accepted'=>0,'Interview'=>0,'Pending'=>0,'Rejected'=>0,'No Answer'=>0];
$total  = $total  ?? array_sum($counts);
$title  = $title  ?? 'Interviewly - Job Application Tracker';
$content = $content ?? '';
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
        <h1 class="logo">Interviewly</h1>
        <div class="nav-links">
          <a href="<?= htmlspecialchars(url_for('home.home')) ?>" class="nav-link <?= is_active('home.home') ? 'active' : '' ?>">Home</a>
          <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="nav-link <?= is_active('applications.list_applications') ? 'active' : '' ?>">Applications</a>
          <a href="<?= htmlspecialchars(url_for('stats.stats')) ?>" class="nav-link <?= is_active('stats.stats') ? 'active' : '' ?>">Stats</a>
        </div>
      </nav>
      <div class="hero-content">
        <p class="subtitle">Track your job applications and interviews in one place</p>
        <div class="hero-stats">
          <div class="stat-item"><span class="stat-number"><?= (int)($counts['Accepted'] ?? 0) ?></span><span class="stat-label">Offers</span></div>
          <div class="stat-item"><span class="stat-number"><?= (int)($counts['Interview'] ?? 0) ?></span><span class="stat-label">Interviews</span></div>
          <div class="stat-item"><span class="stat-number"><?= (int)$total ?></span><span class="stat-label">Total</span></div>
        </div>
      </div>
    </header>

    <main class="main-content"><?= $content ?></main>

    <footer class="main-footer">
      <p>&copy; <?= date('Y') ?> Interviewly. All rights reserved.</p>
    </footer>
  </div>
</body>
</html>
