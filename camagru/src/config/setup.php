<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/environment.php';

try {
    [$host, $db, $user, $pass] = databaseSettings();
    // Connexion SANS dbname
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/../database/init.sql');
    $sql = str_replace(['CREATE DATABASE IF NOT EXISTS camagru', 'USE camagru;'], ['CREATE DATABASE IF NOT EXISTS `' . $db . '`', 'USE `' . $db . '`;'], $sql);
    $pdo->exec($sql);
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migration) {
        $pdo->exec(file_get_contents($migration));
    }

    echo "DataBase initialisée avec succès.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Impossible d’initialiser la base. Vérifiez sa disponibilité et la configuration locale.\n");
    exit(1);
}
