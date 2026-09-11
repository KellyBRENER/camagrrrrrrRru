<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '0');

set_exception_handler(function (Throwable $error) {
    http_response_code(500);
    header('Cache-Control: no-store');

    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Service temporairement indisponible.'
        ]);
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8">'
        . '<title>Camagru indisponible</title>'
        . '<p>Le service est temporairement indisponible. Réessayez dans quelques instants.</p>';
});

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
