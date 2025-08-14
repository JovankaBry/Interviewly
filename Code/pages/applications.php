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
    /* match your blue theme tokens */
    --primary:#2563eb; --primary-dark:#1d4ed8; --primary-light:#3b82f6;
    --ok:#10b981; --warn:#fbbf24; --bad:#ef4444;
    --bg:#0f172a; --bg-light:#0e1626; --text:#f8fafc; --muted:#9ca3af; --border:#1e293b;
    --radius:12px; --gap:16px; --ring: rgba(37,99,235,.35);
    --shadow-lg: 0 14px 30px rgba(0,0,0,.35);
    --shadow-sm: 0 8px 18px rgba(0,0,0,.25);
  }

  /* ===== Header block with search UNDER filters ===== */
  .page-head{
    border:1px solid var(--border);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
    box-shadow: var(--shadow-sm);
    padding: 14px;
    margin-bottom: 14px;
  }
  .head-title{
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
  }
  .page-title{ margin:0; font-size:1.2rem; font-weight:800; letter-spacing:.2px }
  .head-filters{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }

  /* chips */
  .chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px; font-weight:700; font-size:.9rem;
    border:1px solid var(--border); color:#cbd5e1; background: rgba(255,255,255,.02);
    transition: transform .18s ease, background .18s ease, border-color .18s ease;
  }
  .chip:hover{ transform: translateY(-1px); background: rgba(255,255,255,.04); }
  .chip.active{
    color:#fff; background: linear-gradient(180deg, rgba(37,99,235,.25), rgba(37,99,235,.12));
    border-color: rgba(37,99,235,.35);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.04), 0 6px 18px rgba(37,99,235,.18);
  }

  /* search row under filters */
  .head-actions{
    display:flex; gap: 12px; align-items:center; margin-top: 12px;
    flex-wrap: wrap;
  }
  .search-wrap{
    flex:1;
    display:flex; gap:8px; align-items:center;
    padding:6px; border:1px solid var(--border); border-radius: 12px;
    background: rgba(255,255,255,.02);
    min-width: 260px;
  }
  .search-input{
    flex:1; background:transparent; border:0; outline:none; color:var(--text);
    padding: 6px 8px; min-width: 140px;
  }
  .search-btn, .clear-btn{
    padding:8px 12px; border-radius: 999px; border:0; cursor:pointer; font-weight:700;
    transition: transform .18s ease, filter .18s ease;
  }
  .search-btn{ background: var(--primary); color:#fff }
  .search-btn:hover{ transform: translateY(-1px); filter: brightness(1.05); }
  .clear-btn{ background:#334155; color:#fff; text-decoration:none }
  .clear-btn:hover{ transform: translateY(-1px); filter: brightness(1.05); }

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

  /* ===== List ===== */
  .list{ display:grid; gap: 12px; margin-top: 12px; }
  .card{
    position:relative; overflow:visible; isolation:isolate;
    border:1px solid var(--border); border-radius: var(--radius);
    background: linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
    box-shadow: var(--shadow-sm);
    padding: 14px;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
  }
  .card:hover{ transform: translateY(-2px); border-color: rgba(37,99,235,.35); box-shadow: var(--shadow-lg); }
  .card[data-anim]{ opacity:0; transform: translateY(8px); }
  .card.in{ opacity:1; transform:none; transition: opacity .45s ease, transform .45s ease; }

  .title{ font-weight:800; font-size:1.05rem; margin-bottom:4px }
  .company{ color: var(--muted); display:inline-flex; align-items:center; gap:6px; }
  .company:hover{ color: var(--primary-light); }

  .status-container{ display:flex; align-items:center; gap:10px; margin-top: 12px }
  .status-label{ color: var(--muted); font-size: .9rem; }

  /* Floating status menu */
  .floating-menu{
    position:absolute; top:0; left:0; z-index:2000; display:none;
    min-width:180px; padding:6px;
    background: var(--bg-light); border:1px solid var(--border); border-radius: 12px;
    box-shadow: var(--shadow-lg);
    animation: pop .12s ease both;
  }
  .floating-menu.show{ display:block; }
  @keyframes pop{ from{ transform:translateY(-4px); opacity:0 } to{ transform:none; opacity:1 } }

  .status-option{
    display:block; width:100%; margin:0 0 6px 0; padding:8px 12px; border:0; border-radius: 10px;
    font-weight:700; text-align:left; color:#fff; cursor:pointer;
    transition: transform .15s ease, filter .15s ease;
  }
  .status-option:last-child{ margin-bottom:0 }
  .status-option:hover{ transform: translateX(4px); filter: brightness(1.1); }

  /* Focus ring */
  .status-pill:focus-visible, .chip:focus-visible, .add-btn:focus-visible,
  .search-btn:focus-visible, .clear-btn:focus-visible, .search-input:focus-visible{
    outline:none; box-shadow: 0 0 0 3px var(--ring);
  }

  /* Empty state */
  .empty-state{ text-align:center; padding: 40px 10px; }
  .empty-state h3{ margin:0 0 6px 0; }
  .empty-state .muted{ color: var(--muted); margin-bottom: 16px; }

  /* Keep pill colors from your theme */
  .status-pill.pending{ background:#3b82f6; color:#fff }
  .status-pill.interview{ background:#f59e0b; color:#fff }
  .status-pill.accepted{ background:#10b981; color:#fff }
  .status-pill.rejected{ background:#ef4444; color:#fff }
  .status-pill.noanswer{ background:#64748b; color:#fff }

  /* Mobile tweaks */
  @media (max-width: 860px){
    .add-btn{ width: 100%; justify-content:center; }
    .search-wrap{ flex: 1 1 100%; }
  }
</style>

<!-- ===== Header (title + filters, then search UNDER them) ===== -->
<div class="page-head">
  <div class="head-title">
    <h1 class="page-title">Applications</h1>
  </div>

  <div class="head-filters" role="tablist" aria-label="Status filters">
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
    <form method="get" action="<?= htmlspecialchars(url_for('applications.list_applications')) ?>" class="search-wrap">
      <input class="search-input" type="text" name="q" placeholder="Search company or position"
             value="<?= htmlspecialchars($q) ?>" aria-label="Search">
      <?php if ($filter): ?>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <?php endif; ?>
      <button class="search-btn" type="submit">Search</button>
      <?php if ($q || $filter): ?>
        <a class="clear-btn" href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>">Clear</a>
      <?php endif; ?>
    </form>

    <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="add-btn" title="Add new application">
      <span class="add-btn-icon">＋</span> Add Application
    </a>
  </div>
</div>

<?php if (!empty($load_error)): ?>
  <div class="card" style="background:#2b2b2b;color:#ffb4b4;padding:12px;border-radius:8px;margin:12px 0;">
    <?= htmlspecialchars($load_error) ?>
  </div>
<?php endif; ?>

<!-- ===== Application List ===== -->
<div class="list">
  <?php if (!$rows): ?>
    <div class="card">
      <div class="empty-state">
        <h3>No applications found</h3>
        <div class="muted">Try adding one or adjust your search or filter.</div>
        <a class="add-btn" href="<?= htmlspecialchars(url_for('applications.new')) ?>">
          <span class="add-btn-icon">＋</span> Add Application
        </a>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
    <div class="card" data-anim>
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
  // ===== Status menu logic (unchanged) =====
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

  // ===== Reveal animation for list cards =====
  (function(){
    const els = document.querySelectorAll('.card[data-anim]');
    if(!('IntersectionObserver' in window)){ els.forEach(el=>el.classList.add('in')); return; }
    const io = new IntersectionObserver((entries, obs)=>{
      entries.forEach(e=>{
        if(e.isIntersecting){ e.target.classList.add('in'); obs.unobserve(e.target); }
      });
    }, { threshold: .2 });
    els.forEach(el => io.observe(el));
  })();
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../includes/base.php';