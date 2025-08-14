<?php
// pages/set_status.php
// Update an application's status (scoped to the logged-in user), then redirect back.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/auth.php';
require_login();
$uid = current_user_id();

require_once __DIR__ . '/../api/db.php'; // $pdo

$appId  = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$allowed = ['Pending','Interview','Accepted','Rejected','No Answer'];

try {
    if ($appId > 0 && in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare(
            "UPDATE applications
             SET status = :s, updated_at = NOW()
             WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([':s' => $status, ':id' => $appId, ':uid' => $uid]);
    }
} catch (Throwable $e) {
    error_log('set_status error: ' . $e->getMessage());
}

// Redirect back (same host only)
$back = '/pages/applications.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $url = $_SERVER['HTTP_REFERER'];
    if (parse_url($url, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        $back = $url;
    }
}
header('Location: ' . $back, true, 303);
exit;
