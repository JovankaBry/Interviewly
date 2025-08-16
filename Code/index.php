<?php
// index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/api/db.php';

$is_logged_in = is_logged_in();
$uid = $is_logged_in ? current_user_id() : null;

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

$rows = [];
$counts = ['Pending'=>0,'Interview'=>0,'Accepted'=>0,'Rejected'=>0,'No Answer'=>0];
$total = 0;

if ($is_logged_in) {
    /* ---------- fetch recent rows ---------- */
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

    /* ---------- fetch counts ---------- */
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
}

$title = 'Home';
ob_start();
?>

<?php if ($is_logged_in): ?>
  <style>
    .tiles-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: var(--gap);
      margin-bottom: calc(var(--gap) * 1.6);
    }
    .tiles-row .tile {
      background-color: var(--card-bg, #0f1623) !important;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 14px;
      box-shadow: var(--shadow-1, 0 6px 20px rgba(0,0,0,0.35));
      padding: 16px;
    }
    .status-card { margin-top: calc(var(--gap) * 1.6); }
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
              <div class="progress-fill <?= strtolower(str_replace(' ', '', $label)) ?>" data-pct="<?= sprintf('%.2f', $pct) ?>"></div>
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

<?php else: ?>
  <!-- Landing Page for visitors -->
  <div class="landing">
    <h1>Welcome to Tracklly</h1>
    <p class="muted">Track and manage your job applications in one place.</p>
    <a href="<?= htmlspecialchars(url_for('auth.login')) ?>" class="btn">Login</a>
    <a href="<?= htmlspecialchars(url_for('auth.login', ['next'=>'/auth/register.php'])) ?>" class="btn-outline">Create Account</a>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/includes/base.php';
