<?php

$pdo = new PDO(
    "mysql:host=db",
    "root",
    "rootpassword",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("DROP DATABASE IF EXISTS camagru");

echo "Database dropped\n";