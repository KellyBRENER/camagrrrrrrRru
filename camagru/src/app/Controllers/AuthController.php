<?php
require_once __DIR__ . '/../Models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new UserModel($pdo);
    }
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Logique de vérification (on verra après)
            // header('Content-Type: application/json'); echo ... ; exit;
        }
        return "login.php"; // Affiche le formulaire
    }

    //POST username + mot de passe pour se connecter
    //GET pour afficher le formulaire de connexion
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
            // Dans AuthController.php, méthode register()
            if (strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password)) {
                echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir 8 caractères, un chiffre et une lettre.']);
                exit;
            }

            // Tentative de création
            $success = $this->userModel->create($username, $email, $password);

            if ($success) {
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