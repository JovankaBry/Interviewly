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

<style>
  :root{
    --primary:#2563eb; --primary-dark:#1d4ed8; --primary-light:#3b82f6;
    --bg:#0f172a; --bg-light:#0e1626; --text:#f8fafc; --muted:#9ca3af; --border:#1e293b;
    --radius:12px; --gap:16px; --ring: rgba(37,99,235,.35);
    --shadow-lg: 0 14px 30px rgba(0,0,0,.35);
    --shadow-sm: 0 8px 18px rgba(0,0,0,.25);
  }

  .page-head{
    border:1px solid var(--border);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
    box-shadow: var(--shadow-sm);
    padding: 14px;
    margin-bottom: 16px;
  }
  .page-title{ margin:0; font-size:1.4rem; font-weight:800; letter-spacing:.2px }

  /* Filter chips */
  .filters{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px }
  .chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius: 999px; font-weight:700; font-size:.95rem;
    border:1px solid var(--border); color:#cbd5e1; background: rgba(255,255,255,.02);
    transition: transform .18s ease, background .18s ease, border-color .18s ease;
    white-space: nowrap;
  }
  .chip:hover{ transform: translateY(-1px); background: rgba(255,255,255,.04); }
  .chip.active{
    color:#fff; background: linear-gradient(180deg, rgba(37,99,235,.25), rgba(37,99,235,.12));
    border-color: rgba(37,99,235,.35);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.04), 0 6px 18px rgba(37,99,235,.18);
  }

  /* Search + Add actions */
  .head-actions{ display:flex; gap: 12px; align-items:center; margin-top: 12px; flex-wrap: wrap; }

  .search-group{
    position: relative;
    flex: 1 1 360px;
    min-width: 220px;
  }
  .search-input{
    width: 100%;
    background: rgba(255,255,255,.02);
    border:1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    padding: 12px 94px 12px 12px; /* space for the button on the right */
    font-size: 16px;              /* prevents iOS zoom on focus */
    line-height: 1.2;
    outline: none;
  }
  .search-input:focus{ box-shadow: 0 0 0 3px var(--ring); }

  .search-btn{
    position: absolute; right: 6px; top: 6px; bottom: 6px;
    display:inline-flex; align-items:center; justify-content:center;
    min-width: 78px; height: auto;
    padding: 0 12px;
    border-radius: 10px; border:0;
    background: var(--primary); color:#fff; font-weight:800;
    cursor: pointer;
    transition: transform .15s ease, filter .15s ease;
  }
  .search-btn:hover{ transform: translateY(-1px); filter: brightness(1.05); }

  /* Make the search button smaller on phones */
  @media (max-width: 600px){
    .search-input{ padding: 10px 78px 10px 10px; font-size: 15px; }
    .search-btn{ min-width: 64px; padding: 0 10px; border-radius: 9px; font-size: .9rem; }
  }

  .add-btn{
    display:inline-flex; align-items:center; gap:10px; white-space:nowrap;
    padding: 10px 14px; border-radius: 10px;
    background: linear-gradient(180deg, var(--primary), var(--primary-dark));
    color:#fff; font-weight:700; border:1px solid rgba(255,255,255,.06);
    box-shadow: 0 10px 20px rgba(37,99,235,.25), inset 0 0 0 1px rgba(255,255,255,.06);
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
  }
  .add-btn:hover{ transform: translateY(-1px); box-shadow: 0 12px 22px rgba(37,99,235,.32); }
  .add-btn-icon{ font-size:1.2rem; }
  @media (max-width: 860px){ .add-btn{ width:100%; justify-content:center; } }

  /* List & cards */
  .list{ display:grid; gap: 12px; margin-top: 8px; }
  .card{
    position:relative;
    border:1px solid var(--border); border-radius: var(--radius);
    background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
    box-shadow: var(--shadow-sm);
    padding: 14px;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
  }
  .card:hover{ transform: translateY(-2px); border-color: rgba(37,99,235,.35); box-shadow: var(--shadow-lg); }
  .title{ font-weight:800; font-size:1.05rem; margin-bottom:4px }
  .company{ color: var(--muted); display:inline-flex; align-items:center; gap:6px; }
  .company:hover{ color: var(--primary-light); }

  .status-container{ display:flex; align-items:center; gap:10px; margin-top: 12px }
  .status-label{ color: var(--muted); font-size: .9rem; }

  /* Status pills */
  .status-pill{ border:0; border-radius: 999px; padding: 6px 12px; font-weight:700; color:#fff; cursor:pointer; }
  .status-pill.pending{ background:#3b82f6; }
  .status-pill.interview{ background:#f59e0b; }
  .status-pill.accepted{ background:#10b981; }
  .status-pill.rejected{ background:#ef4444; }
  .status-pill.noanswer{ background:#64748b; }
  .dropdown-arrow{ font-size:12px; margin-left:6px; }

  /* Menu (fixed to viewport & clamped) */
  .floating-menu{
    position: fixed;
    z-index: 9999;
    display:none;
    min-width:180px;
    padding:6px;
    background: var(--bg-light);
    border:1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
  }
  .floating-menu.show{ display:block; }
  .status-option{
    display:block; width:100%; margin:0 0 6px 0; padding:8px 12px; border:0; border-radius: 10px;
    font-weight:700; text-align:left; color:#fff; cursor:pointer;
    transition: transform .15s ease, filter .15s ease;
  }
  .status-option:last-child{ margin-bottom:0 }
  .status-option:hover{ transform: translateX(4px); filter: brightness(1.1); }
  .status-option.pending{ background:#3b82f6; }
  .status-option.interview{ background:#f59e0b; }
  .status-option.accepted{ background:#10b981; }
  .status-option.rejected{ background:#ef4444; }
  .status-option.noanswer{ background:#64748b; }

  .empty-state{ text-align:center; padding: 40px 10px; }
  .empty-state h3{ margin:0 0 6px 0; }
  .empty-state .muted{ color: var(--muted); margin-bottom: 16px; }
</style>

<!-- Header -->
<div class="page-head">
  <h1 class="page-title">Applications</h1>

  <div class="filters" role="tablist" aria-label="Status filters">
    <?php
      $mk = function(string $label, ?string $key) use ($filter){
        $active = $key === null ? ($filter === null) : ($filter === $key);
        $href   = $key === null
                ? url_for('applications.list_applications')
                : url_for('applications.list_applications', ['filter'=>$key]);
        $cls    = 'chip' . ($active ? ' active' : '');
        echo '<a class="'.$cls.'" href="'.htmlspecialchars($href).'">'.htmlspecialchars($label).'</a>';
      };
      $mk('All', null);
      $mk('Pending','Pending');
      $mk('Interview','Interview');
      $mk('Accepted','Accepted');
      $mk('Rejected','Rejected');
      $mk('No Answer','No Answer');
    ?>
  </div>

  <div class="head-actions">
    <!-- Compact search group -->
    <form method="get" action="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="search-group" role="search">
      <input class="search-input" type="text" name="q" placeholder="Search company or position"
             value="<?= htmlspecialchars($q) ?>" aria-label="Search company or position" inputmode="search" />
      <?php if ($filter): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
      <button class="search-btn" type="submit">Search</button>
    </form>

    <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="add-btn">
      <span class="add-btn-icon">＋</span> Add Application
    </a>
  </div>
</div>

<?php if (!empty($load_error)): ?>
  <div class="card" style="background:#2b2b2b;color:#ffb4b4;padding:12px;border-radius:8px;margin:12px 0;">
    <?= htmlspecialchars($load_error) ?>
  </div>
<?php endif; ?>

<!-- List -->
<div class="list">
  <?php if (!$rows): ?>
    <div class="card">
      <div class="empty-state">
        <h3>No applications found</h3>
        <div class="muted">Try adding one or adjust your search/filter.</div>
        <a class="add-btn" href="<?= htmlspecialchars(url_for('applications.new')) ?>">
          <span class="add-btn-icon">＋</span> Add Application
        </a>
      </div>
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

        <form id="form-<?= htmlspecialchars(v($r, 'id')) ?>" method="post"
              action="<?= htmlspecialchars(url_for('applications.set_status', ['app_id' => v($r, 'id')])) ?>">
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Floating status menu -->
<div id="status-menu" class="floating-menu" role="menu" aria-hidden="true">
  <button type="button" class="status-option pending"   data-status="Pending">Pending</button>
  <button type="button" class="status-option interview" data-status="Interview">Interview</button>
  <button type="button" class="status-option accepted"  data-status="Accepted">Accepted</button>
  <button type="button" class="status-option rejected"  data-status="Rejected">Rejected</button>
  <button type="button" class="status-option noanswer"  data-status="No Answer">No Answer</button>
</div>

<script>
  // Attach menu to body (safest on mobile), and position/clamp near the clicked pill
  (function(){
    const menu = document.getElementById('status-menu');
    if (menu && menu.parentNode !== document.body) document.body.appendChild(menu);
  })();

  let CURRENT_ID = null;

  function openStatusMenu(id, btn, ev) {
    ev.stopPropagation();
    CURRENT_ID = id;

    const menu = document.getElementById('status-menu');
    const r = btn.getBoundingClientRect();

    // Show to measure
    menu.style.visibility = 'hidden';
    menu.classList.add('show');

    const w = menu.offsetWidth;
    const h = menu.offsetHeight;
    const pad = 8;

    // Clamp to viewport
    const x = Math.max(pad, Math.min(r.left, window.innerWidth  - w - pad));
    const y = Math.max(pad, Math.min(r.bottom + 6, window.innerHeight - h - pad));

    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
    menu.style.visibility = 'visible';
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

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../includes/base.php';
