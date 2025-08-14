<?php
// pages/stats.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- DB connection (PDO) ---------- */
require_once __DIR__ . '/../api/db.php'; // provides $pdo

/* ---------- fetch counts from DB ---------- */
// We keep these 5 labels fixed to match your enum.
$labels = ['Pending','Interview','Accepted','Rejected','No Answer'];
$data   = array_fill(0, count($labels), 0);

try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS c
        FROM applications
        GROUP BY status
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // map DB results into $data using fixed label order
    $index = array_flip($labels);
    foreach ($rows as $r) {
        $s = $r['status'] ?? '';
        if (isset($index[$s])) {
            $data[$index[$s]] = (int)$r['c'];
        }
    }
} catch (Throwable $e) {
    // If DB fails, keep zeros and show a soft note.
    $load_error = 'Could not load stats: ' . $e->getMessage();
}

$total = array_sum($data);

/* ---------- helper ---------- */
function je($v): string {
  return htmlspecialchars(
    json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
  );
}

/* ---------- page ---------- */
$title = 'Stats';
ob_start();
?>

<div class="card" style="text-align:center">
  <div class="text-xl font-bold" style="font-weight:700; margin-bottom:12px;">Application Status</div>

  <?php if (!empty($load_error)): ?>
    <div class="muted" style="color:#ffb4b4;margin-bottom:10px;"><?= htmlspecialchars($load_error) ?></div>
  <?php endif; ?>

  <?php if ((int)$total === 0): ?>
    <div class="muted">No data yet — add an application to see stats.</div>
  <?php else: ?>
    <div id="stats-data"
         data-labels='<?= je($labels) ?>'
         data-values='<?= je($data) ?>'></div>

    <!-- Bar -->
    <div style="max-width:980px; margin-inline:auto; margin-bottom:12px;">
      <canvas id="barChart" height="240"></canvas>
    </div>

    <!-- Pie -->
    <div style="max-width:680px; margin-inline:auto;">
      <canvas id="pieChart" height="260"></canvas>
    </div>
  <?php endif; ?>
</div>

<?php if ((int)$total > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  const el = document.getElementById('stats-data');
  const LABELS = JSON.parse(el.dataset.labels || "[]");
  const DATA   = JSON.parse(el.dataset.values || "[]");

  const COLORS = {
    primary: "#2563eb",
    warn:    "#fbbf24",
    ok:      "#10b981",
    bad:     "#ef4444",
    muted:   "#9ca3af",
    text:    "#e5e7eb",
    grid:    "#1f2937",
    bg:      "#0f172a"
  };
  const STATUS_COLOR = {
    "Pending":   COLORS.primary,
    "Interview": COLORS.warn,
    "Accepted":  COLORS.ok,
    "Rejected":  COLORS.bad,
    "No Answer": COLORS.muted,
  };

  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display:false, labels:{ color: COLORS.text } } },
    scales: {
      x: { ticks:{ color: COLORS.text }, grid:{ color: COLORS.grid } },
      y: { beginAtZero:true, ticks:{ color: COLORS.text, precision:0, stepSize:1 }, grid:{ color: COLORS.grid } }
    }
  };

  new Chart(document.getElementById("barChart"), {
    type: "bar",
    data: {
      labels: LABELS,
      datasets: [{ data: DATA, backgroundColor: LABELS.map(l => STATUS_COLOR[l]), borderRadius: 6 }]
    },
    options: commonOptions
  });

  new Chart(document.getElementById("pieChart"), {
    type: "pie",
    data: {
      labels: LABELS,
      datasets: [{ data: DATA, backgroundColor: LABELS.map(l => STATUS_COLOR[l]), borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: "bottom", labels: { color: COLORS.text, boxWidth: 14, padding: 12 } } } }
  });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/base.php';
