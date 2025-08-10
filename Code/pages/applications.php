<?php
// pages/applications.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- helpers ---
function url_for(string $name, array $params = []): string {
    $map = [
        'applications.new'          => '/pages/new.php',
        'applications.list_applications' => '/pages/applications.php',
        'applications.set_status'   => '/pages/set_status.php',
    ];
    $path = $map[$name] ?? '#';
    if ($params) {
        $q = http_build_query($params);
        $path .= (str_contains($path, '?') ? '&' : '?') . $q;
    }
    return $path;
}
function v($row, $key) { return is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? ''); }

// --- expected data (safe default if not injected) ---
$rows = $rows ?? [];   // array of rows with: id, position, company, status

$title = 'Applications';
ob_start();
?>

<!-- Add button -->
<div class="list-header">
  <a href="<?= htmlspecialchars(url_for('applications.new')) ?>" class="add-btn">
    <span class="add-btn-icon">+</span>
    <span class="add-btn-text">Add Application</span>
  </a>
</div>

<!-- Application List -->
<div class="list">
  <?php foreach ($rows as $r): ?>
  <div class="card">
    <div class="title"><?= htmlspecialchars(v($r, 'position')) ?></div>
    <a class="company" href="/companies/<?= rawurlencode(v($r, 'company')) ?>">
      <?= htmlspecialchars(v($r, 'company')) ?>
    </a>

    <div class="status-container">
      <span class="status-label">Status:</span>

      <!-- Trigger -->
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

    // position menu just under the button
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

  // click on an option => submit hidden form
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

  // close on outside click / scroll / resize
  document.addEventListener('click', closeStatusMenu);
  window.addEventListener('scroll', closeStatusMenu, { passive: true });
  window.addEventListener('resize', closeStatusMenu);

  // prevent clicks inside menu from bubbling to document
  document.getElementById('status-menu').addEventListener('click', function (e) {
    e.stopPropagation();
  });
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../includes/base.php';

