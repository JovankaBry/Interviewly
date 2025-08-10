<?php
// pages/set_status.php
// updates an application's status then redirects back to the list

require __DIR__ . '/../api/db.php'; // gives you $pdo (PDO connection)

$appId  = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$allowed = ['Pending','Interview','Accepted','Rejected','No Answer'];
if ($appId > 0 && in_array($status, $allowed, true)) {
    $stmt = $pdo->prepare("UPDATE applications SET status = :s, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':s' => $status, ':id' => $appId]);
}

// back to list (or use a query param to return to previous page)
header('Location: /pages/applications.php');
exit;
