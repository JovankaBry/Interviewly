<?php
// /pages/admin/index.php — Admin dashboard (counts + nav)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../api/session.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../auth/auth.php';

require_admin(); // gate this page

// quick counts (best-effort)
$counts = ['users'=>0,'applications'=>0,'feedback'=>0];
try { $counts['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Throwable $e) {}
try { $counts['applications'] = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(); } catch (Throwable $e) {}
try { $counts['feedback'] = (int)$pdo->query("SELECT COUNT(*) FROM site_feedback")->fetchColumn(); } catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin · Tracklly</title>
<link rel="icon" type="image/png" href="/static/images/icon2.png" />
<style>
  :root{--bg:#0b0f1a;--bg2:#0a1220;--panel:#0f1626;--muted:#9aa4b2;--text:#eaf2ff;--border:#1e2a3b;--primary:#3b82f6;--primary-2:#2563eb;--radius:16px}
  *{box-sizing:border-box} body{margin:0;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--text);font:16px/1.6 system-ui,Segoe UI,Roboto,Helvetica,Arial}
  a{color:inherit;text-decoration:none}
  .nav{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:rgba(10,18,32,.7);backdrop-filter:blur(10px);border-bottom:1px solid var(--border)}
  .brand{display:flex;gap:10px;align-items:center;font-weight:900}
  .brand img{width:36px;height:36px;border-radius:8px}
  .links{display:flex;gap:10px}
  .link{padding:8px 12px;border-radius:10px;color:#d8e1f0;border:1px solid transparent}
  .link.active,.link:hover{background:#0b1222;border-color:var(--border)}
  .container{max-width:1200px;margin:0 auto;padding:28px 20px}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }
  .card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
  .big{font-size:34px;font-weight:900}
  .muted{color:var(--muted)}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:linear-gradient(180deg,var(--primary),var(--primary-2));color:#fff;border:1px solid rgba(255,255,255,.06)}
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
  <h1 style="margin:0 0 16px 0">Overview</h1>
  <div class="grid">
    <div class="card">
      <div class="muted">Users</div>
      <div class="big"><?= number_format($counts['users']) ?></div>
    </div>
    <div class="card">
      <div class="muted">Applications</div>
      <div class="big"><?= number_format($counts['applications']) ?></div>
    </div>
    <div class="card">
      <div class="muted">Feedback</div>
      <div class="big"><?= number_format($counts['feedback']) ?></div>
      <div style="margin-top:10px"><a class="btn" href="/pages/admin/feedback.php">Review feedback</a></div>
    </div>
  </div>
</main>
</body>
</html>