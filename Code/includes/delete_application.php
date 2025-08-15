<?php
// includes/delete_application.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php'; // $pdo

// Only allow POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /pages/applications.php?err=method');
    exit;
}

$appId = (int)($_POST['app_id'] ?? 0);
$return = $_POST['return'] ?? '/pages/applications.php';
if ($appId <= 0) {
    header('Location: ' . $return . (str_contains($return, '?') ? '&' : '?') . 'err=badid');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $appId, ':uid' => $uid]);
    $ok = $stmt->rowCount() > 0;

    $sep = (str_contains($return, '?') ? '&' : '?');
    header('Location: ' . $return . $sep . ($ok ? 'deleted=1' : 'deleted=0'));
    exit;
} catch (Throwable $e) {
    $sep = (str_contains($return, '?') ? '&' : '?');
    header('Location: ' . $return . $sep . 'err=delete');
    exit;
}