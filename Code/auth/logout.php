<?php
// /auth/logout.php
require_once __DIR__ . '/auth.php';
logout();
header('Location: /index.php', true, 303);
exit;