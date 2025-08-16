<?php
// /pages/admin/index.php — Admin dashboard (counts + animated charts, no external libs)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../api/session.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../auth/auth.php';

require_admin(); // gate this page

/* ------------------------------------------------------------------
   Keep your original SQL (do not change)
-------------------------------------------------------------------*/
$counts = ['users'=>0,'applications'=>0,'feedback'=>0];
try { $counts['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Throwable $e) {}
try { $counts['applications'] = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(); } catch (Throwable $e) {}
try { $counts['feedback'] = (int)$pdo->query("SELECT COUNT(*) FROM site_feedback")->fetchColumn(); } catch (Throwable $e) {}

/* ------------------------------------------------------------------
   Optional: tiny helper to build a daily series for the last N days.
   - Tries to read <table>.created_at; if the column doesn't exist,
     returns a zero-filled series so the UI still looks good.
-------------------------------------------------------------------*/
function daily_series(PDO $pdo, string $table, string $dateCol = 'created_at', int $days = 30): array {
    $series = [];
    $labels = [];
    // Build label list (last N days -> today)
    $start = new DateTime("-" . ($days-1) . " days");
    for ($i = 0; $i < $days; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $key = $d->format('Y-m-d');
        $labels[] = $key;
        $series[$key] = 0;
    }

    // Try to read from the given column
    try {
        $sql = "SELECT DATE($dateCol) AS d, COUNT(*) AS c
                FROM `$table`
                WHERE $dateCol >= (CURDATE() - INTERVAL " . ($days-1) . " DAY)
                GROUP BY DATE($dateCol)
                ORDER BY d";
        $stmt = $pdo->query($sql);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $k = $row['d'];
            if (isset($series[$k])) $series[$k] = (int)$row['c'];
        }
    } catch (Throwable $e) {
        // Column likely doesn't exist — keep zero series
    }

    return ['labels' => $labels, 'values' => array_values($series)];
}

// Build 30-day series (best-effort; safe fallbacks if no created_at)
$usersSeries = daily_series($pdo, 'users', 'created_at', 30);
$appsSeries  = daily_series($pdo, 'applications', 'created_at', 30);
$fbSeries    = daily_series($pdo, 'site_feedback', 'created_at', 30);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin · Tracklly</title>
<link rel="icon" type="image/png" href="/static/images/icon2.png" />
<style>
  :root{
    --bg:#0b0f1a;--bg2:#0a1220;--panel:#0f1626;--panel2:#0c1424;
    --muted:#9aa4b2;--text:#eaf2ff;--border:#1e2a3b;
    --primary:#3b82f6;--primary-2:#2563eb;--ok:#16a34a;--warn:#eab308;--err:#ef4444;
    --radius:16px;--rsm:12px;--shadow:0 22px 50px rgba(0,0,0,.35);
    --ease1:cubic-bezier(.22,.61,.36,1); --ease2:cubic-bezier(.16,1,.3,1);
  }
  *{box-sizing:border-box}
  body{
    margin:0;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--text);
    font:16px/1.6 system-ui,Segoe UI,Roboto,Helvetica,Arial;
  }
  a{color:inherit;text-decoration:none}

  /* Top bar */
  .nav{
    position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;
    padding:10px 16px;background:rgba(10,18,32,.72);backdrop-filter:blur(10px);
    border-bottom:1px solid var(--border);
  }
  .brand{display:flex;gap:10px;align-items:center;font-weight:900;letter-spacing:.2px}
  .brand img{width:38px;height:38px;border-radius:10px}
  .links{display:flex;gap:10px;flex-wrap:wrap}
  .link{
    padding:8px 12px;border-radius:10px;color:#d8e1f0;border:1px solid transparent;font-weight:700;
    transition:.2s var(--ease1);
  }
  .link:hover{background:#0b1222;border-color:var(--border)}
  .link.active{
    background:#0b1222;border-color:var(--border);
    box-shadow:inset 0 0 0 1px rgba(59,130,246,.18), 0 2px 8px rgba(0,0,0,.25);
  }
  .btn{
    display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
    background:linear-gradient(180deg,var(--primary),var(--primary-2));color:#fff;
    border:1px solid rgba(255,255,255,.06); font-weight:800; transition:transform .18s var(--ease2),filter .18s var(--ease2);
  }
  .btn:hover{transform:translateY(-1px);filter:brightness(1.05)}

  /* Layout */
  .container{max-width:1240px;margin:0 auto;padding:26px 16px}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  @media (max-width:1000px){ .grid{grid-template-columns:1fr 1fr} }
  @media (max-width:720px){ .grid{grid-template-columns:1fr} }

  /* Cards */
  .card{
    position:relative;border:1px solid var(--border);border-radius:var(--radius);
    background:linear-gradient(180deg,var(--panel),var(--panel2));
    padding:16px;box-shadow:var(--shadow);overflow:hidden;transform:translateY(0);
    transition:transform .25s var(--ease2), box-shadow .25s var(--ease2), border-color .25s var(--ease1);
  }
  .card:hover{transform:translateY(-3px);border-color:#28406a}
  .card::before{
    content:"";position:absolute;inset:-20% -40% auto auto;height:180px;width:320px;
    background:radial-gradient(closest-side, rgba(59,130,246,.18), transparent 70%);
    filter:blur(20px);pointer-events:none;
  }
  .muted{color:var(--muted)}
  .kpi{
    display:flex;align-items:center;justify-content:space-between;gap:12px
  }
  .kpi .num{font-size:34px;font-weight:900;line-height:1}
  .kpi .tag{font-size:.8rem;padding:4px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
  .row{display:grid;grid-template-columns:1.2fr 1fr;gap:18px;margin-top:18px}
  @media (max-width:1100px){ .row{grid-template-columns:1fr} }

  /* Chart canvas */
  .chart-wrap{height:280px}
  canvas{width:100%;height:100%;display:block}

  /* Reveal on load */
  .reveal{opacity:0;transform:translateY(22px);transition:opacity .6s var(--ease1),transform .6s var(--ease1)}
  .reveal.in{opacity:1;transform:none}

  /* Tiny legend */
  .legend{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
  .dot{width:10px;height:10px;border-radius:50%}
  .lg{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:.9rem}
</style>
</head>
<body>
<header class="nav">
  <div class="brand">
    <img src="/static/images/icon2.png" alt="">
    <span>Tracklly · Admin</span>
  </div>
  <nav class="links">
    <a class="link active" href="/pages/admin/index.php">Dashboard</a>
    <a class="link" href="/pages/admin/feedback.php">Feedback</a>
    <a class="link" href="/">Public Site</a>
  </nav>
</header>

<main class="container">
  <!-- KPIs -->
  <section class="grid">
    <div class="card reveal">
      <div class="kpi">
        <div>
          <div class="muted">Users</div>
          <div class="num" data-count="<?= (int)$counts['users'] ?>">0</div>
        </div>
        <span class="tag">All time</span>
      </div>
      <div class="legend">
        <span class="lg"><span class="dot" style="background:#60a5fa"></span>Last 30 days</span>
      </div>
      <div class="chart-wrap"><canvas id="usersChart"></canvas></div>
    </div>

    <div class="card reveal">
      <div class="kpi">
        <div>
          <div class="muted">Applications</div>
          <div class="num" data-count="<?= (int)$counts['applications'] ?>">0</div>
        </div>
        <span class="tag">All time</span>
      </div>
      <div class="legend">
        <span class="lg"><span class="dot" style="background:#34d399"></span>Last 30 days</span>
      </div>
      <div class="chart-wrap"><canvas id="appsChart"></canvas></div>
    </div>

    <div class="card reveal">
      <div class="kpi">
        <div>
          <div class="muted">Feedback</div>
          <div class="num" data-count="<?= (int)$counts['feedback'] ?>">0</div>
        </div>
        <span class="tag">All time</span>
      </div>
      <div class="legend">
        <span class="lg"><span class="dot" style="background:#f59e0b"></span>Last 30 days</span>
      </div>
      <div class="chart-wrap"><canvas id="fbChart"></canvas></div>
      <div style="margin-top:12px">
        <a class="btn" href="/pages/admin/feedback.php">Review feedback</a>
      </div>
    </div>
  </section>

  <!-- Split row: activity + notes -->
  <section class="row">
    <div class="card reveal">
      <h3 style="margin:0 0 10px 0">Activity (last 30 days)</h3>
      <div class="legend">
        <span class="lg"><span class="dot" style="background:#60a5fa"></span>Users</span>
        <span class="lg"><span class="dot" style="background:#34d399"></span>Applications</span>
        <span class="lg"><span class="dot" style="background:#f59e0b"></span>Feedback</span>
      </div>
      <div class="chart-wrap"><canvas id="comboChart"></canvas></div>
    </div>

    <div class="card reveal">
      <h3 style="margin:0 0 10px 0">Quick actions</h3>
      <ul style="margin:0;padding-left:18px;color:var(--muted)">
        <li>Export feedback to CSV (coming soon)</li>
        <li>Promote/demote users (separate screen)</li>
        <li>Monitor signups and app adds daily</li>
      </ul>
      <div style="margin-top:14px">
        <a class="btn" href="/pages/admin/feedback.php">Go to Feedback</a>
      </div>
    </div>
  </section>
</main>

<script>
/* -------------------
   Helpers (vanilla)
--------------------*/
const ease = (t)=>t<.5 ? 2*t*t : -1+(4-2*t)*t;

// Count-up KPIs
function animateCount(el, target, ms=900){
  const start = performance.now();
  function step(t){
    const p = Math.min(1, (t-start)/ms);
    const n = Math.round(target * ease(p));
    el.textContent = n.toLocaleString();
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// Tiny chart renderer (no deps)
function drawLineChart(ctx, labels, values, color, options={}) {
  const dpr = window.devicePixelRatio || 1;
  const W = ctx.canvas.clientWidth * dpr, H = ctx.canvas.clientHeight * dpr;
  ctx.canvas.width = W; ctx.canvas.height = H;

  const pad = 28 * dpr;
  const x0 = pad, y0 = pad, x1 = W - pad, y1 = H - pad;
  const n = values.length;

  const max = Math.max(1, Math.max.apply(null, values));
  const min = 0;

  // background grid
  ctx.clearRect(0,0,W,H);
  ctx.strokeStyle = 'rgba(255,255,255,.06)';
  ctx.lineWidth = 1 * dpr;
  ctx.beginPath();
  for(let i=0;i<=4;i++){
    const y = y1 - (i/4)*(y1-y0);
    ctx.moveTo(x0,y); ctx.lineTo(x1,y);
  }
  ctx.stroke();

  // area gradient
  const grad = ctx.createLinearGradient(0, y0, 0, y1);
  grad.addColorStop(0, color.replace('1)', '.28)'));
  grad.addColorStop(1, color.replace('1)', '0)'));

  // line path (animate)
  const pts = values.map((v, i) => {
    const x = x0 + (i/(n-1))*(x1-x0);
    const y = y1 - ((v-min)/(max-min))*(y1-y0);
    return {x,y};
  });

  let prog = 0;
  const drawFrame = () => {
    prog = Math.min(1, prog + 0.04);
    const lastIndex = Math.floor(prog * (n-1));
    const frac = prog * (n-1) - lastIndex;

    ctx.save();
    // Area
    ctx.beginPath();
    ctx.moveTo(pts[0].x, y1);
    for(let i=0;i<lastIndex;i++) ctx.lineTo(pts[i].x, pts[i].y);
    // interpolate to next point
    if (lastIndex < n-1) {
      const a = pts[lastIndex], b = pts[lastIndex+1];
      const ix = a.x + (b.x - a.x)*frac;
      const iy = a.y + (b.y - a.y)*frac;
      ctx.lineTo(ix, iy);
    }
    ctx.lineTo(pts[Math.max(0,lastIndex)].x, y1);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    for(let i=1;i<=lastIndex;i++) ctx.lineTo(pts[i].x, pts[i].y);
    if (lastIndex < n-1) {
      const a = pts[lastIndex], b = pts[lastIndex+1];
      const ix = a.x + (b.x - a.x)*frac;
      const iy = a.y + (b.y - a.y)*frac;
      ctx.lineTo(ix, iy);
    }
    ctx.strokeStyle = color;
    ctx.lineWidth = 2 * dpr;
    ctx.stroke();

    ctx.restore();
    if (prog < 1) requestAnimationFrame(drawFrame);
  };
  requestAnimationFrame(drawFrame);
}

function drawComboChart(canvas, labels, s1, s2, s3){
  const ctx = canvas.getContext('2d');
  const color1 = 'rgba(96,165,250,1)';   // users
  const color2 = 'rgba(52,211,153,1)';   // apps
  const color3 = 'rgba(245,158,11,1)';   // feedback

  const dpr = window.devicePixelRatio || 1;
  const W = canvas.clientWidth * dpr, H = canvas.clientHeight * dpr;
  canvas.width = W; canvas.height = H;

  const pad = 28 * dpr;
  const x0 = pad, y0 = pad, x1 = W - pad, y1 = H - pad;

  const all = s1.concat(s2,s3);
  const max = Math.max(1, Math.max(...all));
  const min = 0;

  // grid
  const grid = canvas.getContext('2d');
  grid.clearRect(0,0,W,H);
  grid.strokeStyle = 'rgba(255,255,255,.06)';
  grid.lineWidth = 1 * dpr;
  grid.beginPath();
  for(let i=0;i<=4;i++){
    const y = y1 - (i/4)*(y1-y0);
    grid.moveTo(x0,y); grid.lineTo(x1,y);
  }
  grid.stroke();

  // re-usable draw
  const make = (values, color)=>{
    const grad = ctx.createLinearGradient(0, y0, 0, y1);
    grad.addColorStop(0, color.replace('1)', '.24)'));
    grad.addColorStop(1, color.replace('1)', '0)'));
    const n = values.length;
    const pts = values.map((v,i)=>{
      const x = x0 + (i/(n-1))*(x1-x0);
      const y = y1 - ((v-min)/(max-min))*(y1-y0);
      return {x,y};
    });
    let prog=0;
    const frame=()=>{
      prog = Math.min(1, prog + 0.035);
      const last = Math.floor(prog*(n-1));
      const frac = prog*(n-1) - last;

      ctx.save();
      // area
      ctx.beginPath();
      ctx.moveTo(pts[0].x, y1);
      for(let i=0;i<last;i++) ctx.lineTo(pts[i].x, pts[i].y);
      if (last < n-1){
        const a=pts[last], b=pts[last+1];
        ctx.lineTo(a.x + (b.x-a.x)*frac, a.y + (b.y-a.y)*frac);
      }
      ctx.lineTo(pts[Math.max(0,last)].x, y1);
      ctx.closePath();
      ctx.fillStyle = grad; ctx.fill();

      // line
      ctx.beginPath();
      ctx.moveTo(pts[0].x, pts[0].y);
      for(let i=1;i<=last;i++) ctx.lineTo(pts[i].x, pts[i].y);
      if (last < n-1){
        const a=pts[last], b=pts[last+1];
        ctx.lineTo(a.x + (b.x-a.x)*frac, a.y + (b.y-a.y)*frac);
      }
      ctx.strokeStyle = color; ctx.lineWidth = 2*dpr; ctx.stroke();
      ctx.restore();

      if (prog < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  };

  make(s1, color1);
  setTimeout(()=>make(s2, color2), 90);
  setTimeout(()=>make(s3, color3), 180);
}

/* -------------------
   Boot
--------------------*/
const kpiEls = document.querySelectorAll('.num[data-count]');
kpiEls.forEach(el => animateCount(el, parseInt(el.getAttribute('data-count'),10) || 0));

const observer = new IntersectionObserver((ents)=>{
  ents.forEach(en=>{
    if(en.isIntersecting){ en.target.classList.add('in'); observer.unobserve(en.target); }
  });
}, {threshold:.15, rootMargin:'0px 0px -10% 0px'});
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Data from PHP
const usersLabels = <?=
  json_encode($usersSeries['labels'], JSON_UNESCAPED_SLASHES)
?>;
const usersValues = <?=
  json_encode($usersSeries['values'], JSON_UNESCAPED_SLASHES)
?>;
const appsValues  = <?=
  json_encode($appsSeries['values'], JSON_UNESCAPED_SLASHES)
?>;
const fbValues    = <?=
  json_encode($fbSeries['values'], JSON_UNESCAPED_SLASHES)
?>;

// Individual charts
drawLineChart(document.getElementById('usersChart').getContext('2d'), usersLabels, usersValues, 'rgba(96,165,250,1)');
drawLineChart(document.getElementById('appsChart').getContext('2d'),  usersLabels, appsValues,  'rgba(52,211,153,1)');
drawLineChart(document.getElementById('fbChart').getContext('2d'),    usersLabels, fbValues,    'rgba(245,158,11,1)');

// Combo chart
drawComboChart(document.getElementById('comboChart'), usersLabels, usersValues, appsValues, fbValues);

// Handle resize (redraw canvases)
let rAF;
window.addEventListener('resize', ()=>{
  cancelAnimationFrame(rAF);
  rAF = requestAnimationFrame(()=>{
    drawLineChart(document.getElementById('usersChart').getContext('2d'), usersLabels, usersValues, 'rgba(96,165,250,1)');
    drawLineChart(document.getElementById('appsChart').getContext('2d'),  usersLabels, appsValues,  'rgba(52,211,153,1)');
    drawLineChart(document.getElementById('fbChart').getContext('2d'),    usersLabels, fbValues,    'rgba(245,158,11,1)');
    drawComboChart(document.getElementById('comboChart'), usersLabels, usersValues, appsValues, fbValues);
  });
});
</script>
</body>
</html>