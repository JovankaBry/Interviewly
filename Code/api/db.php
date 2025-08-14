<?php
$config = require __DIR__ . '/config.php';

$dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
} catch (Throwable $e) {
  // helpful error in dev; comment this out in production
  http_response_code(500);
  die('DB connection failed: ' . $e->getMessage());
}
