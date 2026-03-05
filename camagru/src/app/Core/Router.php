<?php
class Router {
    private $routes;
    private $pdo;

    public function __construct($routes, $pdo) {
        $this->routes = $routes;
        $this->pdo = $pdo;
    }

    public function handleRequest($page, $isLoggedIn) {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

        if (!array_key_exists($page, $this->routes)) {
            //ou bien header("HTTP/1.0 404 Not Found"); exit;
            $page = 'home';
        }

        $route = $this->routes[$page];

        // Sécuritési page demandée privée et utilisateur non connecté
        if (!$route['public'] && !$isLoggedIn) {
            if ($isAjax) {
                http_response_code(403);
                echo "<h2>Accès refusé</h2><p>Veuillez vous <a href='#' data-page='login'>connecter</a>.</p>";
                exit;
            }
            //redirection
            header("Location: /?page=login"); 
            exit;
        }

        // Exécution du contrôleur
        require_once __DIR__ . "/../Controllers/" . $route['controller'] . ".php";
        $controllerName = $route['controller'];
        $controller = new $controllerName($this->pdo);
        
        // Le contrôleur nous donne le nom du fichier vue (ex: 'home.php')
        $viewFile = $controller->{$route['method']}();

        // C'EST ICI QUE SE FAIT LE CHOIX DU LAYOUT
        $viewPath = __DIR__ . "/../Views/" . $viewFile;

        if ($isAjax) {
            require $viewPath; // On envoie juste le morceau
        } else {
            require __DIR__ . "/../Views/layout.php"; // On envoie le squelette qui inclura $viewPath
        }
    }
}