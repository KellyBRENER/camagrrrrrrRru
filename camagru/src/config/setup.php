<?php
require_once __DIR__ . '/database.php'; // On récupère $pdo
$sql = file_get_contents(__DIR__ . '/../database/init.sql');
$pdo->exec($sql);
echo "Database initialisée\n";