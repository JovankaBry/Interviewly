<?php
// index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- auth + db ---------- */
require_once __DIR__ . '/auth/auth.php';
require_login();
$uid = current_user_id();          // <- use this in queries

require_once __DIR__ . '/api/db.php'; // provides $pdo

/* ---------- helpers ---------- */
function url_for(string $name, array $params = []): string {
    $map = [
        'applications.list_applications' => '/pages/applications.php',
        'applications.new'               => '/pages/new.php',
        'stats.stats'                    => '/pages/stats.php',
        'auth.login'                     => '/auth/login.php',
        'auth.logout'                    => '/auth/logout.php',
    ];
    $path = $map[$name] ?? '#';
    if ($params) {
        $query = http_build_query($params);
        $path .= (str_contains($path, '?') ? '&' : '?') . $query;
    }
    return $path;
}
function v($row, $key) {
    return is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
}

/* ---------- fetch recent rows (for activity) ---------- */
$rows = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, company, position, status
        FROM applications
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Index fetch rows error: ' . $e->getMessage());
}

/* ---------- fetch counts by status (scoped to user) ---------- */
$counts = ['Pending'=>0,'Interview'=>0,'Accepted'=>0,'Rejected'=>0,'No Answer'=>0];
try {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS c
        FROM applications
        WHERE user_id = :uid
        GROUP BY status
    ");
    $stmt->execute([':uid' => $uid]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s = $r['status'];
        if (isset($counts[$s])) $counts[$s] = (int)$r['c'];
    }
} catch (Throwable $e) {
    error_log('Index fetch counts error: ' . $e->getMessage());
}
$total = array_sum($counts);

/* ---------- page ---------- */
$title = 'Home';
ob_start();
?>

<!-- Page-only tweaks: increase vertical space before the status card -->
<style>
  /* Add more breathing room between the top tiles and the status card */
  .status-card { margin-top: calc(var(--gap) * 1); } /* adjust 2.2 → 1.8–3 as you like */
  /* Optional: also add extra gap after tiles row if you prefer that approach */
  /* .tiles-row { margin-bottom: calc(var(--gap) * 2.2); } */
</style>

<!-- Dashboard Tiles -->
<div class="tiles-row">
  <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="tile tile-dark1">
    <div class="tile-title">Applications</div>
    <div class="tile-number"><?= count($rows) ?></div>
    <div class="tile-hint">View &amp; manage all</div>
  </a>

  <a href="<?= htmlspecialchars(url_for('stats.stats')) ?>" class="tile tile-dark2">
    <div class="tile-title">Statistics</div>
    <div class="tile-number"><?= (int)($counts['Accepted'] ?? 0) ?>/<?= (int)$total ?></div>
    <div class="tile-hint">
      Success rate:
      <?= sprintf('%.1f', $total ? (($counts['Accepted'] ?? 0) / $total * 100) : 0) ?>%
    </div>
  </a>
</div>

<!-- Status Overview Card -->
<div class="card status-card">
  <div class="card-header">
    <h2 class="section-title">Application Status</h2>
    <a href="<?= htmlspecialchars(url_for('stats.stats')) ?>" class="header-link">View analytics</a>
  </div>

  <div class="status-list">
    <?php $order = ['Pending','Interview','Accepted','Rejected','No Answer']; ?>
    <?php foreach ($order as $label): ?>
      <?php
        $value = (int)($counts[$label] ?? 0);
        $pct   = $total ? ($value / $total * 100) : 0;
      ?>
      <a href="<?= htmlspecialchars(url_for('applications.list_applications', ['filter' => $label])) ?>" class="status-row">
        <div class="status-left">
          <span class="status-pill <?= strtolower(str_replace(' ', '', $label)) ?>"><?= htmlspecialchars($label) ?></span>
          <span class="count-text"><?= $value ?></span>
        </div>
        <div class="status-right">
          <div class="progress-bar">
            <div class="progress-fill <?= strtolower(str_replace(' ', '', $label)) ?>"
                 data-pct="<?= sprintf('%.2f', $pct) ?>"></div>
          </div>
          <span class="percentage"><?= sprintf('%.0f', $pct) ?>%</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Recent Activity Card -->
<div class="card activity-card">
  <div class="card-header">
    <h2 class="section-title">Recent Activity</h2>
    <?php if (count($rows) > 0): ?>
      <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="header-link">View all</a>
    <?php endif; ?>
  </div>

  <?php if (count($rows) === 0): ?>
    <div class="empty-state">
      <h3>No applications yet</h3>
      <p class="muted">Add your first job application to get started</p>
      <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="btn">Add Application</a>
    </div>
  <?php else: ?>
    <div class="activity-list">
      <?php foreach ($rows as $r): ?>
        <div class="activity-item">
          <div class="activity-content">
            <div class="activity-title"><?= htmlspecialchars(v($r, 'position')) ?></div>
            <div class="activity-subtitle"><?= htmlspecialchars(v($r, 'company')) ?></div>
          </div>
          <span class="status-pill <?= strtolower(str_replace(' ', '', v($r, 'status'))) ?>">
            <?= htmlspecialchars(v($r, 'status')) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="actions">
  <a href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="btn">My Applications</a>
  <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="btn-outline">Add New</a>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.progress-fill[data-pct]').forEach(el => {
      const pct = parseFloat(el.dataset.pct || '0') || 0;
      el.style.width = pct + '%';
    });
  });
</script>

<?php
$content = ob_get_clean();
/* base.php uses $title, $counts, $total, $content */
include __DIR__ . '/includes/base.php';