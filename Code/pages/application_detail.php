<?php
// pages/application_detail.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- auth + db ---------- */
require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php'; // $pdo

/* ---------- helpers ---------- */
if (!function_exists('url_for')) {
  function url_for(string $name, array $params = []): string {
      $map = [
          'home.home'                      => '/index.php',
          'applications.list_applications' => '/pages/applications.php',
          'applications.detail'            => '/pages/application_detail.php',
          'applications.set_status'        => '/pages/set_status.php',
          'applications.new'               => '/pages/new.php',
          'stats.stats'                    => '/pages/stats.php',
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
function v($row, $key) { return is_array($row) ? ($row[$key] ?? null) : ($row->$key ?? null); }

/* ---------- input ---------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); echo "Application not found"; exit; }

/* ---------- fetch ---------- */
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = :id AND user_id = :uid");
$stmt->execute([':id' => $id, ':uid' => $uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo "Application not found"; exit; }

/* ---------- derived values ---------- */
$position  = (string) (v($row, 'position') ?? '');
$company   = (string) (v($row, 'company') ?? '');
$status    = (string) (v($row, 'status') ?? 'Pending');
$jobType   = (string) (v($row, 'job_type') ?? '');
$salary    = (string) (v($row, 'salary') ?? '');
$notes     = (string) (v($row, 'notes') ?? '');
$jobLink   = (string) (v($row, 'job_link') ?? '');
$location  = (string) (v($row, 'location') ?? '');
$source    = (string) (v($row, 'source') ?? '');
$applied   = (string) (v($row, 'applied_date') ?? (v($row, 'created_at') ?? ''));
$updated   = (string) (v($row, 'updated_at') ?? '');

$companyLinkedIn = 'https://www.linkedin.com/search/results/companies/?keywords=' . rawurlencode($company);
$returnUrl = $_SERVER['REQUEST_URI'] ?? url_for('applications.list_applications');

/* ---------- page ---------- */
$title = "Application Details";
ob_start();
?>

<style>
  /* Page-specific tweaks only (global theme is in style.css) */
  .page-head{ border:1px solid var(--border); border-radius:var(--radius); background:var(--bg-light); padding:16px; position:relative; }

  /* Title + actions */
  .title-line{ display:flex; flex-direction:column; gap:8px; }
  @media (min-width:768px){
    .title-line{ flex-direction:row; align-items:flex-start; justify-content:space-between; }
  }
  .title-line h2{ margin:0; font-size:1.15rem; font-weight:800; line-height:1.25; }

  .actions-top{ display:flex; gap:8px; }
  @media (min-width:768px){
    .actions-top{ position:absolute; top:12px; right:12px; }
  }

  .back-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:4px 8px; border-radius:8px; border:1px solid var(--border);
    background:rgba(255,255,255,.04); color:#fff; font-weight:700; font-size:.8rem; text-decoration:none;
  }
  .back-btn:hover{ transform:translateY(-1px); filter:brightness(1.04) }

  .icon-btn{
    width:32px; height:32px; padding:0; border-radius:10px; border:1px solid rgba(239,68,68,.35);
    background:rgba(239,68,68,.12); color:#fecaca; display:inline-flex; align-items:center; justify-content:center;
    font-size:16px; line-height:1; cursor:pointer; transition:transform .15s ease, filter .15s ease;
  }
  .icon-btn:hover{ transform:translateY(-1px); filter:brightness(1.05); background:rgba(239,68,68,.18); }

  /* Clean mobile wrapping for jobType • company • location */
  .info-line{ display:flex; flex-wrap:wrap; align-items:center; gap:6px 10px; color:var(--muted); margin-top:6px; }
  .info-item{ display:inline-flex; align-items:center; }
  .info-item:not(:first-child)::before{ content:"•"; color:var(--muted); margin:0 6px; }

  .pill-soft{
    display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px;
    border:1px solid var(--border); background:rgba(255,255,255,.04);
    color:#dbeafe; font-weight:700; font-size:.8rem;
  }
  .info-link{ color:inherit; text-decoration:none }
  .info-link:hover{ color:var(--primary); text-decoration:underline }

  .status-container{ display:flex; align-items:center; gap:10px; margin-top:12px; flex-wrap:wrap; }
  .status-label{ color:var(--muted); font-size:14px }

  /* Make the job link EXACTLY like status pill */
  .status-pill.linkpill{ background:var(--primary); border:0; text-decoration:none; }

  .meta-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px }
  @media (max-width:640px){ .meta-grid{ grid-template-columns: 1fr } }
  .meta-item{ background:rgba(255,255,255,.03); border:1px solid var(--border); border-radius:var(--radius); padding:12px }
  .meta-label{ color:var(--muted); font-size:.85rem; margin-bottom:4px }
  .meta-value{ font-weight:700 }

  .notes-box{
    white-space:pre-wrap; background:rgba(255,255,255,.03); border:1px solid var(--border);
    border-radius:var(--radius); padding:12px; min-height:64px
  }

  /* Floating status menu */
  .floating-menu{ position:fixed; z-index:2000; display:none; min-width:180px; padding:6px; background:var(--bg-light); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 8px 16px rgba(0,0,0,.25); }
  .floating-menu.show{ display:block; }
  .floating-menu .status-option{ display:block; width:100%; margin:0 0 6px 0; padding:8px 12px; border:0; border-radius:var(--radius); font-weight:600; text-align:left; color:#fff; cursor:pointer; transition:transform .2s ease, filter .2s ease; }
  .floating-menu .status-option:last-child{ margin-bottom:0 }
  .floating-menu .status-option:hover{ transform:translateX(4px); filter:brightness(1.12) }
  .floating-menu .status-option.pending{ background:#3b82f6 } .floating-menu .status-option.interview{ background:#f59e0b }
  .floating-menu .status-option.accepted{ background:#10b981 } .floating-menu .status-option.rejected{ background:#ef4444 }
  .floating-menu .status-option.noanswer{ background:#64748b }
</style>

<div class="page-head card">
  <div class="title-line">
    <h2><?= htmlspecialchars($position ?: 'Untitled position') ?></h2>

    <div class="actions-top">
      <!-- Trash (icon only) -->
      <form method="post" action="/includes/delete_application.php" onsubmit="return confirm('Delete this application? This cannot be undone.');">
        <input type="hidden" name="app_id" value="<?= (int)$id ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars(url_for('applications.list_applications')) ?>">
        <button type="submit" class="icon-btn" title="Delete" aria-label="Delete">🗑</button>
      </form>

      <!-- Back (compact) -->
      <a class="back-btn" href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>">← Back</a>
    </div>
  </div>

  <div class="info-line">
    <?php if ($jobType !== ''): ?>
      <span class="pill-soft info-item"><?= htmlspecialchars($jobType) ?></span>
    <?php endif; ?>
    <?php if ($company !== ''): ?>
      <a class="info-item info-link" href="<?= htmlspecialchars($companyLinkedIn) ?>" target="_blank" rel="noopener noreferrer">
        <?= htmlspecialchars($company) ?>
      </a>
    <?php endif; ?>
    <?php if ($location !== ''): ?>
      <span class="info-item"><?= htmlspecialchars($location) ?></span>
    <?php endif; ?>
  </div>

  <div class="status-container">
    <span class="status-label">Status:</span>

    <button
      class="status-pill <?= strtolower(str_replace(' ', '', $status)) ?>"
      type="button"
      onclick="openStatusMenu('<?= (int)$id ?>', this, event)">
      <?= htmlspecialchars($status) ?> <span class="dropdown-arrow">▼</span>
    </button>

    <?php if ($jobLink): ?>
      <!-- EXACT same bubble as status using status-pill base -->
      <a class="status-pill linkpill" href="<?= htmlspecialchars($jobLink) ?>" target="_blank" rel="noopener noreferrer">
        Open Job Link ↗
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h3 class="section-title">Overview</h3>
  <div class="meta-grid">
    <?php if ($company !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Company</div>
        <div class="meta-value"><?= htmlspecialchars($company) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($jobType !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Job Type</div>
        <div class="meta-value"><?= htmlspecialchars($jobType) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($salary !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Salary</div>
        <div class="meta-value"><?= htmlspecialchars($salary) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($source !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Source</div>
        <div class="meta-value"><?= htmlspecialchars($source) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($applied !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Applied / Created</div>
        <div class="meta-value"><?= htmlspecialchars($applied) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($updated !== ''): ?>
      <div class="meta-item">
        <div class="meta-label">Last Updated</div>
        <div class="meta-value"><?= htmlspecialchars($updated) ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h3 class="section-title">Notes</h3>
  <div class="notes-box"><?= $notes === '' ? '<span class="muted">No notes yet.</span>' : nl2br(htmlspecialchars($notes)) ?></div>
  <div class="actions" style="margin-top:12px;">
    <a class="back-btn" href="<?= htmlspecialchars(url_for('applications.list_applications')) ?>">← Back to list</a>
    <?php if ($jobLink): ?>
      <a class="status-pill linkpill" href="<?= htmlspecialchars($jobLink) ?>" target="_blank" rel="noopener noreferrer">Open Job Link</a>
    <?php endif; ?>
  </div>
</div>

<!-- Hidden form to update status -->
<form id="form-<?= (int)$id ?>" method="post"
      action="<?= htmlspecialchars(url_for('applications.set_status', ['app_id' => (int)$id])) ?>">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
</form>

<!-- Floating status menu -->
<div id="status-menu" class="floating-menu" role="menu" aria-hidden="true">
  <button type="button" class="status-option pending"   data-status="Pending">Pending</button>
  <button type="button" class="status-option interview" data-status="Interview">Interview</button>
  <button type="button" class="status-option accepted"  data-status="Accepted">Accepted</button>
  <button type="button" class="status-option rejected"  data-status="Rejected">Rejected</button>
  <button type="button" class="status-option noanswer"  data-status="No Answer">No Answer</button>
</div>

<script>
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

    menu.style.visibility = 'hidden';
    menu.classList.add('show');

    const w = menu.offsetWidth;
    const h = menu.offsetHeight;
    const pad = 8;

    const x = Math.max(pad, Math.min(r.left, window.innerWidth  - w - pad));
    const y = Math.max(pad, Math.min(r.bottom + 6, window.innerHeight - h - pad));

    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
    menu.style.visibility = 'visible';
    menu.setAttribute('aria-hidden', 'false');
  }

  function closeStatusMenu() {
    const menu = document.getElementById('status-menu');
    if (!menu) return;
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