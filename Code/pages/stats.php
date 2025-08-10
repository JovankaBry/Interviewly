<?php
// pages/stats.php

// Expected from controller: $labels (array), $data (array of counts), $total (int).
// Provide safe defaults so the page still renders if they’re missing.
$labels = $labels ?? ['Pending','Interview','Accepted','Rejected','No Answer'];
$data   = $data   ?? array_fill(0, count($labels), 0);
$total  = $total  ?? array_sum($data);

function je($v): string {
  // JSON encode for embedding into data-* attributes safely
  return htmlspecialchars(
    json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
  );
}

$title = 'Stats';
ob_start();
?>

<div class="card" style="text-align:center">
  <div class="text-xl font-bold" style="font-weight:700; margin-bottom:12px;">Application Status</div>

  <?php if ((int)$total === 0): ?>
    <div class="muted">No data yet — add an application to see stats.</div>
  <?php else: ?>
    <!-- put data in data-* so JS stays clean -->
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
  // read JSON safely (no PHP in JS)
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

  // Bar
  new Chart(document.getElementById("barChart"), {
    type: "bar",
    data: {
      labels: LABELS,
      datasets: [{
        data: DATA,
        backgroundColor: LABELS.map(l => STATUS_COLOR[l]),
        borderRadius: 6
      }]
    },
    options: commonOptions
  });

  // Pie
  new Chart(document.getElementById("pieChart"), {
    type: "pie",
    data: {
      labels: LABELS,
      datasets: [{
        data: DATA,
        backgroundColor: LABELS.map(l => STATUS_COLOR[l]),
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: "bottom", labels: { color: COLORS.text, boxWidth: 14, padding: 12 } }
      }
    }
  });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../base.php';
