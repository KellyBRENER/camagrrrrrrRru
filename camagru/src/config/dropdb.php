<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/environment.php';
[$host, $db, $user, $pass] = databaseSettings();
$pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$db`");
echo "Database dropped\n";
