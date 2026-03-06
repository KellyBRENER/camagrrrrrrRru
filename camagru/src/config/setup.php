<?php
$host = 'db';
$user = 'root';
$pass = 'rootpassword';

try {
    // Connexion SANS dbname
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/../database/init.sql');
    $pdo->exec($sql);

    echo "DataBase initialisée avec succès.\n";
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage() . "\n");
}