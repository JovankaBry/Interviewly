<?php
// pages/set_status.php
// Update an application's status, then redirect back to the list (or referrer)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../api/db.php'; // provides $pdo

// same interface as your form: ?app_id=... and POST[status]
$appId  = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

$allowed = ['Pending','Interview','Accepted','Rejected','No Answer'];

try {
    if ($appId > 0 && in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare(
            "UPDATE applications
             SET status = :s, updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':s' => $status, ':id' => $appId]);
    }
} catch (Throwable $e) {
    // optional: log and show a friendly message (but still redirect)
    error_log('set_status error: ' . $e->getMessage());
}

// Redirect back: prefer the page user came from, fall back to list
$back = '/pages/applications.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    // keep it on your site only
    $url = $_SERVER['HTTP_REFERER'];
    if (parse_url($url, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        $back = $url;
    }
}

// Use 303 to avoid form resubmission
header('Location: ' . $back, true, 303);
exit;
