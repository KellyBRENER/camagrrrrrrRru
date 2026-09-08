<?php
require_once __DIR__ . '/../app/Core/Security.php';
Security::startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
Security::requireCsrf();
session_unset();
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
header('Location: /?page=home', true, 303);
exit;
