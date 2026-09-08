<?php

function databaseSettings() {
    $database = getenv('MYSQL_DATABASE') ?: 'camagru';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
        throw new RuntimeException('Nom de base invalide.');
    }
    $password = getenv('MYSQL_ROOT_PASSWORD');
    if ($password === false || $password === '') {
        throw new RuntimeException('MYSQL_ROOT_PASSWORD doit être défini dans le fichier .env.');
    }
    return [getenv('DB_HOST') ?: 'db', $database, getenv('DB_USER') ?: 'root', $password];
}
