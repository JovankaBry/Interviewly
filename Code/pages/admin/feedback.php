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
  :root{--bg:#0b0f1a;--bg2:#0a1220;--panel:#0f1626;--muted:#9aa4b2;--text:#eaf2ff;--border:#1e2a3b;--primary:#3b82f6;--radius:16px}
  *{box-sizing:border-box} body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--text);font:16px/1.6 system-ui,Segoe UI,Roboto,Helvetica,Arial}
  a{color:inherit;text-decoration:none}
  .nav{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:rgba(10,18,32,.7);backdrop-filter:blur(10px);border-bottom:1px solid var(--border)}
  .brand{display:flex;gap:10px;align-items:center;font-weight:900}
  .brand img{width:36px;height:36px;border-radius:8px}
  .links{display:flex;gap:10px}
  .link{padding:8px 12px;border-radius:10px;color:#d8e1f0;border:1px solid transparent}
  .link.active,.link:hover{background:#0b1222;border-color:var(--border)}
  .container{max-width:1200px;margin:0 auto;padding:28px 20px}
  .muted{color:var(--muted)}
  .controls{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px 0}
  .input{background:#0b1222;color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px 12px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:#0b1222;color:#fff;border:1px solid var(--border)}
  .btn.primary{background:linear-gradient(180deg,#3b82f6,#2563eb);border-color:rgba(255,255,255,.06)}
  .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }
  .card{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:12px}
  .rowtop{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .name{font-weight:800}
  .meta{color:var(--muted);font-size:.9rem}
  .msg{white-space:pre-wrap;margin:6px 0 0 0}
  .small{font-size:.9rem;color:var(--muted)}
  .pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
  .page{padding:6px 10px;border-radius:10px;border:1px solid var(--border);background:#0b1222}
  .page.active{background:#122036}
  form.inline{display:inline}
</style>
</head>
<body>
<header class="nav">
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
  <h1 style="margin:0 0 10px 0">Feedback</h1>
  <div class="controls">
    <form method="get">
      <input class="input" type="text" name="q" placeholder="Search name/message/ip/ua…" value="<?= htmlspecialchars($q) ?>">
      <button class="btn primary" type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn" href="/pages/admin/feedback.php">Clear</a><?php endif; ?>
    </form>
    <a class="btn" href="/pages/admin/feedback.php?export=1">Export CSV</a>
  </div>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="small" style="margin:0 0 10px 0;color:#8bffb1">Deleted.</div>
  <?php elseif (isset($_GET['err']) && $_GET['err']==='csrf'): ?>
    <div class="small" style="margin:0 0 10px 0;color:#ff9b9b">Action failed (CSRF).</div>
  <?php endif; ?>

  <div class="small" style="margin-bottom:8px"><?= number_format($total) ?> result(s)</div>

  <div class="grid">
    <?php foreach ($rows as $r): ?>
      <div class="card">
        <div class="rowtop">
          <div>
            <div class="name"><?= $r['name'] ? htmlspecialchars($r['name']) : 'Anonymous' ?></div>
            <div class="meta"><?= htmlspecialchars($r['created_at']) ?> · <span class="small"><?= htmlspecialchars($r['ip'] ?? '') ?></span></div>
          </div>
          <div>
            <form class="inline" method="post" onsubmit="return confirm('Delete this feedback?')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
              <button class="btn" type="submit">Delete</button>
            </form>
          </div>
        </div>
        <div class="msg"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
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
</body>
</html>