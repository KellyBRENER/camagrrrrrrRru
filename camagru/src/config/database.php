<?php
//ce fichier permet d'initialiser la connexion à la DB pour chaque connexion client
require_once __DIR__ . '/environment.php';
[$host, $db, $user, $pass] = databaseSettings();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

