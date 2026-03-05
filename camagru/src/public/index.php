<?php
session_start();

// On inclut la connexion PDO
require_once __DIR__ . '/../config/database.php'; 
// On inclut le Router
require_once __DIR__ . '/../app/Core/Router.php';
// On récupère le tableau de routes
$routes = require __DIR__ . '/../config/routes.php';

$page = $_GET['page'] ?? 'home';
$isLoggedIn = isset($_SESSION['user_id']);

$router = new Router($routes, $pdo); // On lui passe $pdo pour les contrôleurs
$router->handleRequest($page, $isLoggedIn);