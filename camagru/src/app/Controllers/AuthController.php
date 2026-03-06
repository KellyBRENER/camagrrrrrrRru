<?php
require_once __DIR__ . '/../Models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new UserModel($pdo);
    }
    //POST username + mot de passe pour se connecter
    //GET pour afficher le formulaire de connexion
    public function login() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // 1. On cherche l'utilisateur par son pseudo
        $user = $this->userModel->getByUsername($username);

        // 2. On vérifie si l'utilisateur existe ET si le mot de passe est bon
        if ($user && password_verify($password, $user['password'])) {
            
            // On remplit la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            echo json_encode(['success' => true]);
            exit;
        } else {
            // Sécurité : on ne dit pas si c'est le pseudo ou le mot de passe qui est faux
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
            exit;
        }
    }

    return "login.php"; // Si GET, on affiche le formulaire
}

    //POST email + username + mot de passe pour s'inscrire
    //GET pour afficher le formulaire d'inscription
    public function register() {
        // 1. Si on reçoit des données (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');

            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Validation simple (tu pourras ajouter des regex plus tard)
            if (empty($username) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs.']);
                exit;
            }
            if (strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password)) {
                echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir 8 caractères, un chiffre et une lettre.']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Le format de l\'adresse email est invalide.'
                ]);
            exit;
            }

            // Tentative de création
            $success = $this->userModel->create($username, $email, $password);

            if ($success) {
                $link = "http://localhost:8080/?page=verify&token=" . $token;
                $subject = "Confirmez votre compte Camagru";
                $message = "Cliquez ici pour valider votre compte : " . $link;
                mail($email, $subject, $message); // Fonction native PHP
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur ou email déjà pris.']);
            }
            exit;
        }

        // 2. Si on veut juste voir la page (GET)
        return "register.php";
    }

    public function studio() {
        return "pictureStudio.php";
    }
}