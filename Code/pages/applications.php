<?php
// pages/applications.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- auth + db ---------- */
require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php'; // $pdo

/* ---------- helpers ---------- */
function url_for(string $name, array $params = []): string {
    $map = [
        'home.home'                      => '/index.php',
        'applications.new'               => '/pages/new.php',
        'applications.list_applications' => '/pages/applications.php',
        'applications.set_status'        => '/pages/set_status.php',
        'stats.stats'                    => '/pages/stats.php',
    ];
    $path = $map[$name] ?? '#';
    if ($params) {
        $q = http_build_query($params);
        $path .= (str_contains($path, '?') ? '&' : '?') . $q;
    }
    return $path;
}
function v($row, $key) { return is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? ''); }

/* ---------- inputs: filter + search (optional) ---------- */
$validStatuses = ['Pending','Interview','Accepted','Rejected','No Answer'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $validStatuses, true) ? $_GET['filter'] : null;
$q      = trim($_GET['q'] ?? '');

/* ---------- fetch rows from DB (SCOPED BY user_id) ---------- */
$rows = [];
try {
    $sql = "SELECT id, company, position, status
            FROM applications
            WHERE user_id = :uid";
    $params = [':uid' => $uid];

    if ($filter) {
        $sql .= " AND status = :status";
        $params[':status'] = $filter;
    }
    if ($q !== '') {
        $sql .= " AND (company LIKE :q OR position LIKE :q)";
        $params[':q'] = "%{$q}%";
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
    $load_error = 'Could not load applications: ' . $e->getMessage();
}

/* ---------- page ---------- */
$title = 'Applications';
ob_start();
?>

<!-- Add button + search -->
<div class="list-header">
  <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="add-btn">
    <span class="add-btn-icon">+</span>
    <span class="add-btn-text">Add Application</span>
  </a>

  <form method="get" action="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="search-wrap">
    <input class="search-input" type="text" name="q" placeholder="Search company or position"
           value="<?= htmlspecialchars($q) ?>">
    <?php if ($filter): ?>
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <?php endif; ?>
    <button class="search-btn" type="submit">Search</button>
    <?php if ($q || $filter): ?>
      <a class="clear-btn" href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!empty($load_error)): ?>
  <div class="card" style="background:#2b2b2b;color:#ffb4b4;padding:12px;border-radius:8px;margin:10px 0;">
    <?= htmlspecialchars($load_error) ?>
  </div>
<?php endif; ?>

<!-- Application List -->
<div class="list">
  <?php if (!$rows): ?>
    <div class="card">
      <div class="title">No applications found</div>
      <div class="company">Try adding one or adjust your search/filter.</div>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
    <div class="card">
      <div class="title"><?= htmlspecialchars(v($r, 'position')) ?></div>
      <a class="company" href="/companies/<?= rawurlencode(v($r, 'company')) ?>">
        <?= htmlspecialchars(v($r, 'company')) ?>
      </a>

      <div class="status-container">
        <span class="status-label">Status:</span>

        <?php $status = (string) v($r, 'status'); ?>
        <button
          class="status-pill <?= strtolower(str_replace(' ', '', $status)) ?>"
          type="button"
          onclick="openStatusMenu('<?= htmlspecialchars(v($r, 'id')) ?>', this, event)">
          <?= htmlspecialchars($status) ?> <span class="dropdown-arrow">▼</span>
        </button>
      </div>

      <!-- Hidden form to submit status change -->
      <form id="form-<?= htmlspecialchars(v($r, 'id')) ?>" method="post"
            action="<?= htmlspecialchars(url_for('applications.set_status', ['app_id' => v($r, 'id')])) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Floating status menu (one instance only) -->
<div id="status-menu" class="floating-menu" role="menu" aria-hidden="true">
  <button type="button" class="status-option pending"   data-status="Pending">Pending</button>
  <button type="button" class="status-option interview" data-status="Interview">Interview</button>
  <button type="button" class="status-option accepted"  data-status="Accepted">Accepted</button>
  <button type="button" class="status-option rejected"  data-status="Rejected">Rejected</button>
  <button type="button" class="status-option noanswer"  data-status="No Answer">No Answer</button>
</div>

<script>
  let CURRENT_ID = null;

  function openStatusMenu(id, btn, ev) {
    ev.stopPropagation();
    CURRENT_ID = id;

    const menu = document.getElementById('status-menu');
    const rect = btn.getBoundingClientRect();

    menu.style.left = (window.scrollX + rect.left) + 'px';
    menu.style.top  = (window.scrollY + rect.bottom + 6) + 'px';
    menu.classList.add('show');
    menu.setAttribute('aria-hidden', 'false');
  }
  function closeStatusMenu() {
    const menu = document.getElementById('status-menu');
    menu.classList.remove('show');
    menu.setAttribute('aria-hidden', 'true');
    CURRENT_ID = null;
  }

  document.getElementById('status-menu').addEventListener('click', function (e) {
    const btn = e.target.closest('.status-option');
    if (!btn || !CURRENT_ID) return;

    const status = btn.dataset.status;
    const form = document.getElementById('form-' + CURRENT_ID);
    if (!form) return;

    form.querySelector('input[name="status"]').value = status;
    closeStatusMenu();
    form.submit();
  });

  document.addEventListener('click', closeStatusMenu);
  window.addEventListener('scroll', closeStatusMenu, { passive: true });
  window.addEventListener('resize', closeStatusMenu);
  document.getElementById('status-menu').addEventListener('click', e => e.stopPropagation());
</script>

<style>
.search-wrap{ display:flex; gap:8px; align-items:center; margin-left:auto }
.search-input{
  background:transparent; border:1px solid #1e293b; border-radius:10px; padding:8px 10px; color:#f8fafc;
}
.search-btn,.clear-btn{
  border:0; padding:8px 12px; border-radius:999px; text-decoration:none; cursor:pointer;
  background:#2563eb; color:#000; font-weight:600;
}
.clear-btn{ background:#334155; color:#f8fafc }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../includes/base.php';
