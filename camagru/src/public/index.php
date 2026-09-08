<?php
require_once __DIR__ . '/../app/Core/Security.php';
Security::startSession();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
}
header('Permissions-Policy: camera=(self)');
header("Feature-Policy: camera 'self'");

// On inclut la connexion PDO
require_once __DIR__ . '/../config/database.php'; 
// On inclut le Router
require_once __DIR__ . '/../app/Core/Router.php';
// On récupère le tableau de routes
$routes = require __DIR__ . '/../config/routes.php';

Security::validateSession($pdo);
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$isLoggedIn = isset($_SESSION['user_id']);

$router = new Router($routes, $pdo); // On lui passe $pdo pour les contrôleurs
$router->handleRequest($page, $isLoggedIn);
