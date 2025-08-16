<?php
// /pages/admin/feedback.php — Review feedback entries (search + pagination + delete + CSV export)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../api/session.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../auth/auth.php';

require_admin(); // gate this page

/* Actions: delete one */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_id'])) {
  $id = (int)$_POST['delete_id'];
  $tok = $_POST['csrf'] ?? '';
  if ($id && admin_csrf_check($tok)) {
    $stmt = $pdo->prepare("DELETE FROM site_feedback WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    header("Location: /pages/admin/feedback.php?deleted=1");
    exit;
  } else {
    header("Location: /pages/admin/feedback.php?err=csrf");
    exit;
  }
}

/* Export CSV */
if (isset($_GET['export']) && $_GET['export'] === '1') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=feedback.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','name','message','ip','ua','created_at']);
  $stmt = $pdo->query("SELECT id,name,message,ip,ua,created_at FROM site_feedback ORDER BY id DESC");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
  fclose($out);
  exit;
}

/* List w/ search + pagination */
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];
if ($q !== '') {
  $where = "WHERE (name LIKE :q OR message LIKE :q OR ip LIKE :q OR ua LIKE :q)";
  $params[':q'] = "%$q%";
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM site_feedback $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT id,name,message,ip,ua,created_at
  FROM site_feedback
  $where
  ORDER BY id DESC
  LIMIT :limit OFFSET :offset
");
foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pages = max(1, (int)ceil($total / $per_page));
$csrf = admin_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Feedback · Admin</title>
<link rel="icon" type="image/png" href="/static/images/icon2.png" />
<style>
  :root{
    --bg:#0b0f1a;--bg2:#0a1220;--panel:#0f1626;--muted:#9aa4b2;--text:#eaf2ff;
    --border:#1e2a3b;--primary:#3b82f6;--primary-2:#2563eb;--ok:#22c55e;--warn:#ef4444;
    --radius:16px;--radius-sm:12px;--shadow:0 12px 32px rgba(0,0,0,.35);
    --ease1:cubic-bezier(.22,.61,.36,1);
  }
  *{box-sizing:border-box}
  body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--text);
       font:16px/1.6 system-ui,Segoe UI,Roboto,Helvetica,Arial;-webkit-font-smoothing:antialiased}
  a{color:inherit;text-decoration:none}

  /* Top bar */
  .nav{
    position:sticky;top:0;z-index:60;display:flex;align-items:center;justify-content:space-between;
    gap:12px;padding:12px 20px;background:rgba(10,18,32,.72);backdrop-filter:blur(10px);
    border-bottom:1px solid var(--border);transition:background .25s var(--ease1)
  }
  .nav.scrolled{background:rgba(10,18,32,.86)}
  .brand{display:flex;gap:10px;align-items:center;font-weight:900}
  .brand img{width:36px;height:36px;border-radius:8px}
  .links{display:flex;gap:10px}
  .link{padding:8px 12px;border-radius:10px;border:1px solid transparent;color:#d8e1f0}
  .link:hover,.link.active{background:#0b1222;border-color:var(--border)}

  /* Layout */
  .container{max-width:1200px;margin:0 auto;padding:24px 18px}
  .controls-wrap{
    display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 14px 0
  }
  .input{
    background:#0b1222;color:var(--text);border:1px solid var(--border);border-radius:10px;
    padding:10px 12px;min-width:260px
  }
  .btn{
    display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
    background:#0b1222;color:#fff;border:1px solid var(--border);cursor:pointer;
    transition:transform .18s var(--ease1), filter .18s var(--ease1)
  }
  .btn:hover{transform:translateY(-1px);filter:brightness(1.05)}
  .btn.primary{background:linear-gradient(180deg,var(--primary),var(--primary-2));border-color:rgba(255,255,255,.06)}
  .btn.danger{background:linear-gradient(180deg,#f87171,#ef4444);border-color:rgba(255,255,255,.06)}

  .small{font-size:.92rem;color:var(--muted)}
  .meta{color:var(--muted);font-size:.92rem}

  /* Cards grid */
  .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }

  .card{
    background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-sm);
    padding:14px; box-shadow:var(--shadow); transform:translateY(0);
    transition:transform .2s var(--ease1), box-shadow .2s var(--ease1), border-color .2s var(--ease1);
    opacity:0; translate:0 12px;
  }
  .card:hover{transform:translateY(-2px);border-color:rgba(59,130,246,.35)}
  .card.inview{opacity:1; translate:0 0}
  .rowtop{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .name{font-weight:800}
  .badges{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .badge{
    display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;
    background:#0b1222;border:1px solid var(--border);color:#cfe1ff;font-size:.82rem
  }

  /* Message clamp + toggle */
  .msg{
    margin:8px 0 0 0;white-space:pre-wrap;word-wrap:break-word;
  }
  .msg.clamp{
    display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden;
    max-height:7.2em; /* fallback */
  }
  .toggle{
    margin-top:6px;border:0;background:transparent;color:#9ac1ff;font-weight:700;
    cursor:pointer;padding:4px 0;border-bottom:1px dashed rgba(154,193,255,.35)
  }
  .toggle[hidden]{display:none}

  /* Pagination */
  .pagination{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 0 0}
  .page{
    padding:6px 10px;border-radius:10px;border:1px solid var(--border);background:#0b1222;
    transition:transform .15s var(--ease1)
  }
  .page:hover{transform:translateY(-1px)}
  .page.active{background:#122036}

  /* Toasts */
  .toast{
    position:fixed; right:16px; bottom:16px; z-index:70;
    padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.08);
    background:rgba(15,22,38,.95); color:#eaf2ff; box-shadow:var(--shadow);
    transform:translateY(8px); opacity:0; transition:all .25s var(--ease1)
  }
  .toast.show{transform:none; opacity:1}

</style>
</head>
<body>
<header class="nav" id="topnav">
  <div class="brand">
    <img src="/static/images/icon2.png" alt="">
    <span>Tracklly · Admin</span>
  </div>
  <nav class="links">
    <a class="link" href="/pages/admin/index.php">Dashboard</a>
    <a class="link active" href="/pages/admin/feedback.php">Feedback</a>
    <a class="link" href="/">Public Site</a>
  </nav>
</header>

<main class="container">
  <h1 style="margin:0 0 8px 0">Feedback</h1>
  <div class="small" style="margin:0 0 12px 0"><?= number_format($total) ?> result(s)</div>

  <div class="controls-wrap">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
      <input class="input" type="text" name="q" placeholder="Search name / message / IP / UA…" value="<?= htmlspecialchars($q) ?>">
      <button class="btn primary" type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn" href="/pages/admin/feedback.php">Clear</a><?php endif; ?>
    </form>
    <a class="btn" href="/pages/admin/feedback.php?export=1" title="Download CSV">Export CSV</a>
  </div>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="toast show" id="toast-ok">Deleted.</div>
  <?php elseif (isset($_GET['err']) && $_GET['err']==='csrf'): ?>
    <div class="toast show" id="toast-err">Action failed (CSRF).</div>
  <?php endif; ?>

  <div class="grid" id="cards">
    <?php foreach ($rows as $r): ?>
      <div class="card">
        <div class="rowtop">
          <div>
            <div class="name"><?= $r['name'] ? htmlspecialchars($r['name']) : 'Anonymous' ?></div>
            <div class="meta"><?= htmlspecialchars($r['created_at']) ?> · <span class="small"><?= htmlspecialchars($r['ip'] ?? '') ?></span></div>
          </div>
          <div class="badges">
            <?php if (!empty($r['ip'])): ?>
              <span class="badge" data-copy="<?= htmlspecialchars($r['ip']) ?>" title="Click to copy IP">IP</span>
            <?php endif; ?>
            <?php if (!empty($r['ua'])): ?>
              <span class="badge" title="User Agent">UA</span>
            <?php endif; ?>
            <form class="inline" method="post" onsubmit="return confirm('Delete this feedback?')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" type="submit" title="Delete">Delete</button>
            </form>
          </div>
        </div>

        <div class="msg clamp"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
        <button class="toggle" hidden>Show more</button>

        <?php if (!empty($r['ua'])): ?>
          <div class="small" style="margin-top:8px">UA: <?= htmlspecialchars($r['ua']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($p=1; $p<=$pages; $p++): ?>
        <?php
          $qs = http_build_query(array_filter(['q'=>$q, 'page'=>$p]));
          $href = '/pages/admin/feedback.php'.($qs ? ('?'.$qs) : '');
        ?>
        <a class="page <?= $p===$page?'active':'' ?>" href="<?= $href ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</main>

<script>
  // Shade nav on scroll
  const topnav = document.getElementById('topnav');
  window.addEventListener('scroll', () => topnav.classList.toggle('scrolled', scrollY > 4), {passive:true});

  // Intersection fade-in for cards
  const cards = document.querySelectorAll('.card');
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(en => { if (en.isIntersecting){ en.target.classList.add('inview'); io.unobserve(en.target); }});
  }, {threshold:.15, rootMargin:'0px 0px -8% 0px'});
  cards.forEach(c => io.observe(c));

  // Line-clamp detection + toggle
  function initClamp(card){
    const msg = card.querySelector('.msg');
    const btn = card.querySelector('.toggle');
    if (!msg || !btn) return;

    // Wait for layout
    requestAnimationFrame(() => {
      const clamped = msg.scrollHeight > msg.offsetHeight + 2; // tolerance
      if (clamped) {
        btn.hidden = false;
        btn.addEventListener('click', () => {
          const isClamped = msg.classList.contains('clamp');
          msg.classList.toggle('clamp', !isClamped);
          btn.textContent = isClamped ? 'Show less' : 'Show more';
        });
      }
    });
  }
  document.querySelectorAll('.card').forEach(initClamp);

  // Copy helper for IP badges
  document.addEventListener('click', (e) => {
    const badge = e.target.closest('.badge[data-copy]');
    if (!badge) return;
    const txt = badge.getAttribute('data-copy');
    navigator.clipboard?.writeText(txt).then(()=>{
      badge.textContent = 'Copied';
      setTimeout(()=> badge.textContent = 'IP', 900);
    });
  }, {passive:true});

  // Auto-hide toast
  const toast = document.querySelector('.toast');
  if (toast) setTimeout(()=> toast.classList.remove('show'), 1800);
</script>
</body>
</html>