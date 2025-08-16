<?php
// pages/new.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- auth + db ---------- */
require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php'; // provides $pdo

/* ---------- helpers ---------- */
function url_for(string $name, array $params = []): string {
    $map = [
        'home.home'                      => '/pages/home.php',
        'applications.new'               => '/pages/new.php',
        'applications.list_applications' => '/pages/applications.php',
        'applications.set_status'        => '/pages/set_status.php',
        'stats.stats'                    => '/pages/stats.php',
        'auth.logout'                    => '/auth/logout.php',
        'admin.index'                    => '/pages/admin/index.php',
    ];
    $path = $map[$name] ?? '#';
    if ($params) {
        $q = http_build_query($params);
        $path .= (str_contains($path, '?') ? '&' : '?') . $q;
    }
    return $path;
}
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ---------- handle POST ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company          = trim($_POST['company']          ?? '');
    $position         = trim($_POST['position']         ?? '');
    $job_type         = trim($_POST['job_type']         ?? '');
    $location         = trim($_POST['location']         ?? '');
    $job_link         = trim($_POST['job_link']         ?? '');
    $source           = trim($_POST['source']           ?? '');
    $applied_date     = trim($_POST['applied_date']     ?? '');
    $next_action_date = trim($_POST['next_action_date'] ?? '');
    $salary_range     = trim($_POST['salary_range']     ?? '');
    $notes            = trim($_POST['notes']            ?? '');

    if ($company === '' || $position === '') $errors[] = 'Company and Position are required.';
    $dateRe = '/^\d{4}-\d{2}-\d{2}$/';
    if ($applied_date === '' || !preg_match($dateRe, $applied_date)) $errors[] = 'Applied date must be YYYY-MM-DD.';
    if ($next_action_date !== '' && !preg_match($dateRe, $next_action_date)) $errors[] = 'Next action date must be YYYY-MM-DD.';

    if ($job_link !== '' && !preg_match('~^https?://~i', $job_link)) {
        $job_link = 'https://' . $job_link;
    }

    if (!$errors) {
        $sql = "INSERT INTO applications
                (user_id, company, position, job_type, status, location, job_link, source,
                 applied_date, next_action_date, salary_range, notes,
                 created_at, updated_at)
                VALUES (:uid, :company, :position, :job_type, :status, :location, :job_link, :source,
                        :applied_date, :next_action_date, :salary_range, :notes,
                        NOW(), NOW())";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid'              => $uid,
                ':company'          => $company,
                ':position'         => $position,
                ':job_type'         => ($job_type !== '') ? $job_type : null,
                ':status'           => 'Pending',
                ':location'         => $location ?: null,
                ':job_link'         => $job_link ?: null,
                ':source'           => $source ?: null,
                ':applied_date'     => $applied_date ?: null,
                ':next_action_date' => $next_action_date ?: null,
                ':salary_range'     => $salary_range ?: null,
                ':notes'            => $notes ?: null,
            ]);
            header('Location: ' . url_for('applications.list_applications'));
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

/* ---------- view ---------- */
$title = 'Add Application';
ob_start();
?>

<?php if (!empty($errors)): ?>
  <div class="card error-box">
    <strong>There was a problem:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form id="new-form" method="post" action="<?= h(url_for('applications.new')) ?>" class="new-wrap">
  <label class="label">Company *</label>
  <input class="input" name="company" placeholder="e.g. Amazon" required value="<?= h($_POST['company'] ?? '') ?>">

  <label class="label">Position *</label>
  <input class="input" name="position" placeholder="e.g. Hardware Intern" required value="<?= h($_POST['position'] ?? '') ?>">

  <!-- Job Type (Custom dropdown, posts as job_type) -->
  <label class="label">Job Type</label>
  <?php
    $types = [
      'Full-time','Career Entry','Part-time','Internship','Working Student','Contract','Temporary','Freelance','Thesis','Other'
    ];
    $selectedType = $_POST['job_type'] ?? '';
    $displayLabel = $selectedType !== '' ? $selectedType : '— Select —';
  ?>
  <div class="dd" id="dd-jobtype">
    <input type="hidden" name="job_type" id="job_type" value="<?= h($selectedType) ?>">
    <button type="button" class="dd__button" aria-haspopup="listbox" aria-expanded="false">
      <span class="dd__label"><?= h($displayLabel) ?></span>
      <svg class="dd__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <ul class="dd__menu" role="listbox" tabindex="-1">
      <li class="dd__item dd__item--placeholder" data-value="">— Select —</li>
      <?php foreach ($types as $t): ?>
        <li class="dd__item<?= ($selectedType===$t?' dd__item--selected':'') ?>" data-value="<?= h($t) ?>"><?= h($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <label class="label">Location</label>
  <input class="input" name="location" placeholder="City, Country" value="<?= h($_POST['location'] ?? '') ?>">

  <label class="label">Job Link</label>
  <input class="input" name="job_link" id="job_link" placeholder="https://..." inputmode="url" autocomplete="url"
         value="<?= h($_POST['job_link'] ?? '') ?>">

  <label class="label">Source</label>
  <input class="input" name="source" id="source" placeholder="LinkedIn, Company site"
         value="<?= h($_POST['source'] ?? '') ?>">

  <label class="label">Applied Date *</label>
  <input class="input" name="applied_date" id="applied_date" placeholder="YYYY-MM-DD" required
         value="<?= h($_POST['applied_date'] ?? '') ?>">

  <label class="label">Next Action Date</label>
  <input class="input" name="next_action_date" id="next_action_date" placeholder="YYYY-MM-DD"
         value="<?= h($_POST['next_action_date'] ?? '') ?>">

  <label class="label">Salary Range</label>
  <input class="input" name="salary_range" placeholder="e.g. 60–75k" value="<?= h($_POST['salary_range'] ?? '') ?>">

  <label class="label">Notes</label>
  <textarea class="input" name="notes" rows="6" placeholder="Anything important…"><?= h($_POST['notes'] ?? '') ?></textarea>

  <div style="height:12px"></div>
  <button type="submit" class="pill-btn">Save Application</button>
  <div style="height:24px"></div>
</form>

<script>
  // set default applied date = today if empty
  (function(){
    const el = document.getElementById('applied_date');
    if (!el.value) el.value = new Date().toISOString().slice(0,10);
  })();

  // normalize link on blur + infer source host
  const linkEl = document.getElementById('job_link');
  const sourceEl = document.getElementById('source');
  linkEl.addEventListener('blur', () => {
    const v = linkEl.value.trim();
    if (!v) return;
    let normalized = v;
    if (!/^https?:\/\//i.test(v)) normalized = "https://" + v;
    linkEl.value = normalized;
    try { if (!sourceEl.value.trim()) sourceEl.value = new URL(normalized).hostname; } catch {}
  });

  // simple client validation
  document.getElementById('new-form').addEventListener('submit', (e) => {
    const f = e.target;
    const dateRe = /^\d{4}-\d{2}-\d{2}$/;
    if (!f.company.value.trim() || !f.position.value.trim()) {
      e.preventDefault(); return alert("Company and position are required.");
    }
    if (!dateRe.test(f.applied_date.value.trim())) {
      e.preventDefault(); return alert("Applied date must be YYYY-MM-DD.");
    }
    const next = f.next_action_date.value.trim();
    if (next && !dateRe.test(next)) {
      e.preventDefault(); return alert("Next action date must be YYYY-MM-DD.");
    }
  });

  // ---- Custom dropdown logic (UI only, posts via hidden input) ----
  (function(){
    const dd = document.getElementById('dd-jobtype');
    const btn = dd.querySelector('.dd__button');
    const menu = dd.querySelector('.dd__menu');
    const label = dd.querySelector('.dd__label');
    const hidden = document.getElementById('job_type');

    const open = () => {
      dd.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      menu.focus();
    };
    const close = () => {
      dd.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    };

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      dd.classList.contains('is-open') ? close() : open();
    });

    // choose
    menu.addEventListener('click', (e) => {
      const item = e.target.closest('.dd__item');
      if (!item) return;
      const val = item.dataset.value ?? '';
      hidden.value = val;
      label.textContent = val || '— Select —';
      menu.querySelectorAll('.dd__item').forEach(i => i.classList.remove('dd__item--selected'));
      item.classList.add('dd__item--selected');
      close();
    });

    // close on outside click / escape
    document.addEventListener('click', (e) => {
      if (!dd.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });
  })();
</script>

<style>
:root{
  --bg:#0f172a; --bg-2:#0b1220; --bg-3:#0a1020;
  --text:#f8fafc; --muted:#9ca3af; --border:#1e293b;
  --primary:#2563eb; --primary-dark:#193e9e; --primary-25: rgba(37,99,235,.25);
  --radius:10px; --gap:14px;
  color-scheme: dark;
}

/* Layout + inputs (unchanged look) */
.new-wrap{ background:var(--bg); padding:var(--gap); display:flex; flex-direction:column }
.label{ color:var(--text); font-weight:600; margin:14px 0 6px }
.input{
  color:var(--text); background-color:var(--bg-2); border:1px solid var(--border);
  border-radius: var(--radius); padding:10px 12px; outline:none; transition:.15s;
}
.input:focus{ border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-25); }
.input::placeholder{ color:var(--muted) }
.pill-btn{
  width:100%; padding:14px; border-radius:999px; font-weight:700; cursor:pointer;
  background: linear-gradient(180deg, var(--primary), var(--primary-dark));
  color:#fff; border:0; box-shadow:0 8px 20px rgba(37,99,235,.25);
}
.error-box{ background:#2b2b2b; color:#ffb4b4; padding:12px; border-radius:8px; margin-bottom:16px; }
.error-box ul{ margin:8px 0 0 18px; }

/* ===================== */
/*   Custom Dropdown UI  */
/* ===================== */
.dd{ position:relative; }
.dd__button{
  width:100%;
  display:flex; align-items:center; justify-content:space-between;
  gap:10px;
  color:var(--text);
  background:var(--bg-2);
  border:1px solid var(--border);
  border-radius: var(--radius);
  padding:10px 12px;
  cursor:pointer;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.dd__button:hover{ border-color:#2a3b57; }
.dd__button:focus{ outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-25); }
.dd__chev{ opacity:.8 }
.dd.is-open .dd__chev{ transform: rotate(180deg); transition: transform .15s ease; }

.dd__menu{
  position:absolute; top:calc(100% + 6px); left:0; right:0;
  background: radial-gradient(100% 120% at 0% 0%, rgba(37,99,235,.05), transparent 60%),
              var(--bg-3);
  border:1px solid rgba(120,140,190,.35);
  border-radius:12px;
  box-shadow:
    0 12px 28px rgba(0,0,0,.55),
    0 0 0 2px rgba(255,255,255,0.02) inset;
  padding:6px;
  list-style:none; margin:0;
  max-height:240px; overflow:auto; z-index: 50;
  display:none;
}
.dd.is-open .dd__menu{ display:block; }

.dd__item{
  padding:10px 12px;
  border-radius:8px;
  color:var(--text);
  background:transparent;
  cursor:pointer;
  transition:background .12s ease, transform .06s ease;
}
.dd__item--placeholder{
  color:var(--muted);
  font-style:italic;
}
.dd__item:hover{
  background: rgba(37,99,235,.18);
}
.dd__item:active{
  transform: translateY(1px);
}
.dd__item--selected{
  background: linear-gradient(180deg, rgba(37,99,235,.35), rgba(25,62,158,.35));
  border:1px solid rgba(120,140,190,.35);
}

/* Scrollbar inside menu */
.dd__menu::-webkit-scrollbar{ width:10px }
.dd__menu::-webkit-scrollbar-track{ background:#0a1020 }
.dd__menu::-webkit-scrollbar-thumb{ background:#1f2b4a; border-radius:8px }
.dd__menu::-webkit-scrollbar-thumb:hover{ background:#2a3a63 }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/base.php';