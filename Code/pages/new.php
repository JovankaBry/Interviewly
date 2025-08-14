<?php
// pages/new.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- helpers ---------- */
function url_for(string $name, array $params = []): string {
    $map = [
        'applications.new'               => '/pages/new.php',
        'applications.list_applications' => '/pages/applications.php',
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
    require __DIR__ . '/../api/db.php'; // provides $pdo (and maybe $mysqli)

    // inputs
    $company          = trim($_POST['company']          ?? '');
    $position         = trim($_POST['position']         ?? '');
    $location         = trim($_POST['location']         ?? '');
    $job_link         = trim($_POST['job_link']         ?? '');
    $source           = trim($_POST['source']           ?? '');
    $applied_date     = trim($_POST['applied_date']     ?? '');
    $next_action_date = trim($_POST['next_action_date'] ?? '');
    $salary_range     = trim($_POST['salary_range']     ?? '');
    $notes            = trim($_POST['notes']            ?? '');

    // validation
    if ($company === '' || $position === '') $errors[] = 'Company and Position are required.';
    $dateRe = '/^\d{4}-\d{2}-\d{2}$/';
    if ($applied_date === '' || !preg_match($dateRe, $applied_date)) $errors[] = 'Applied date must be YYYY-MM-DD.';
    if ($next_action_date !== '' && !preg_match($dateRe, $next_action_date)) $errors[] = 'Next action date must be YYYY-MM-DD.';

    // normalize link
    if ($job_link !== '' && !preg_match('~^https?://~i', $job_link)) {
        $job_link = 'https://' . $job_link;
    }

    if (!$errors) {
        // INSERT (expects columns to exist: location, job_link, source, next_action_date, salary_range, notes, updated_at)
        $sql = "INSERT INTO applications
                (company, position, status, location, job_link, source,
                 applied_date, next_action_date, salary_range, notes,
                 created_at, updated_at)
                VALUES (:company, :position, :status, :location, :job_link, :source,
                        :applied_date, :next_action_date, :salary_range, :notes,
                        NOW(), NOW())";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':company'          => $company,
                ':position'         => $position,
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
    try {
      if (!sourceEl.value.trim()) sourceEl.value = new URL(normalized).hostname;
    } catch {}
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
</script>

<style>
  :root{
    --bg:#0f172a; --text:#f8fafc; --muted:#9ca3af; --border:#1e293b; --primary:#2563eb;
    --radius:10px; --gap:14px;
  }
  .new-wrap{ background:var(--bg); padding:var(--gap); display:flex; flex-direction:column }
  .label{ color:var(--text); font-weight:600; margin:14px 0 6px }
  .input{
    color:var(--text); background:transparent; border:1px solid var(--border);
    border-radius: var(--radius); padding:10px; outline:none;
  }
  .input::placeholder{ color:var(--muted) }
  .pill-btn{
    width:100%; padding:14px; border-radius:999px; font-weight:700; cursor:pointer;
    background: var(--primary); color:#000; border:0;
  }
  .error-box{
    background:#2b2b2b; color:#ffb4b4; padding:12px; border-radius:8px; margin-bottom:16px;
  }
  .error-box ul{ margin:8px 0 0 18px; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/base.php';
