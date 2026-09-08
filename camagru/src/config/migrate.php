<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/database.php';
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migration) {
    $pdo->exec(file_get_contents($migration));
}
echo "Migrations appliquées.\n";
