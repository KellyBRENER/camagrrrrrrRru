<?php
require_once __DIR__ . '/../Models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new UserModel($pdo);
    }

    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    private function jsonResponse($success, $message = null) {
        header('Content-Type: application/json');
        $payload = ['success' => (bool) $success];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        echo json_encode($payload);
        exit;
    }

    private function flowLog($step, $context = []) {
        $line = '[REGISTER_FLOW] ' . $step;
        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
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
            $isAjax = $this->isAjaxRequest();
            $requestId = bin2hex(random_bytes(6));
            $this->flowLog('register_post_received', [
                'requestId' => $requestId,
                'isAjax' => $isAjax,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $this->flowLog('register_payload_parsed', [
                'requestId' => $requestId,
                'username' => $username,
                'email' => $email,
                'passwordLength' => strlen($password)
            ]);

            // Validation simple (tu pourras ajouter des regex plus tard)
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'Veuillez remplir tous les champs.';
                $this->flowLog('register_validation_failed', ['requestId' => $requestId, 'reason' => 'empty_fields']);
                if ($isAjax) {
                    $this->jsonResponse(false, $message);
                }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
            if (strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-zA-Z]+#", $password)) {
				$message = 'Le mot de passe doit contenir 8 caractères, un chiffre et une lettre.';
                $this->flowLog('register_validation_failed', ['requestId' => $requestId, 'reason' => 'weak_password']);
                if ($isAjax) {
                    $this->jsonResponse(false, $message);
                }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$message = 'Le format de l\'adresse email est invalide.';
                $this->flowLog('register_validation_failed', ['requestId' => $requestId, 'reason' => 'invalid_email']);
                if ($isAjax) {
                    $this->jsonResponse(false, $message);
                }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
			$token = bin2hex(random_bytes(32));
            $this->flowLog('register_token_generated', ['requestId' => $requestId]);

            // Tentative de création
            $success = $this->userModel->create($username, $email, $password, $token);

            if ($success) {
                $this->flowLog('register_user_created', ['requestId' => $requestId, 'email' => $email]);
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $link = sprintf('%s://%s/?page=verify&token=%s', $scheme, $host, urlencode($token));
                $this->flowLog('register_verify_link_built', ['requestId' => $requestId, 'host' => $host]);

                $to = $email;
    			$subject = "Activez votre compte Camagru 🐆";

    			$headers = [
        			"From" => "Camagru <no-reply@camagru.com>",
        			"Reply-To" => "no-reply@camagru.com",
        			"Content-Type" => "text/html; charset=UTF-8", // On autorise le HTML et les accents
        			"X-Mailer" => "PHP/" . phpversion()
    			];
				$body = "
					<html>
					<head>
						<title>Confirmation d'inscription</title>
					</head>
					<body style='font-family: Georgia, serif; background-color: #f5e6d3; padding: 20px;'>
						<div style='max-width: 600px; margin: 0 auto; background: white; border: 3px solid #8b6f47; border-radius: 15px; padding: 30px;'>
            			<h1 style='color: #6b4423;'>Bienvenue, $username !</h1>
            			<p>Ta tanière est presque prête. Clique sur le lien ci-dessous pour valider ton compte :</p>
            			<p style='text-align: center; margin: 30px 0;'>
                			<a href='$link' style='background: #ff8c42; color: white; padding: 15px 25px; text-decoration: none; border-radius: 25px; font-weight: bold; border: 2px solid #6b4423;'>
                    			ACTIVER MON COMPTE
                			</a>
            			</p>
            			<p style='font-size: 0.8rem; color: #8b6f47;'>Si le bouton ne fonctionne pas, copie ce lien : <br> $link</p>
        				</div>
    				</body>
    				</html>
    			";

    			$headerString = "";
    			foreach ($headers as $key => $value) {
        			$headerString .= "$key: $value\r\n";
    			}

    			$mailSent = mail($to, $subject, $body, $headerString);

    			if ($mailSent) {
                    $this->flowLog('register_mail_sent', ['requestId' => $requestId, 'to' => $email]);
                    if ($isAjax) {
                        $this->jsonResponse(true);
                    }
                    header('Location: /?page=registerinprogress');
                    exit;
    			} else {
                    $message = "Erreur lors de l'envoi du mail.";
                    $this->flowLog('register_mail_failed', ['requestId' => $requestId, 'to' => $email]);
                    if ($isAjax) {
                        $this->jsonResponse(false, $message);
                    }
                    header('Location: /?page=register&error=' . urlencode($message));
                    exit;
    			}
            } else {
                	$message = 'Nom d\'utilisateur ou email déjà pris.';
	                	$this->flowLog('register_create_failed', ['requestId' => $requestId, 'reason' => 'username_or_email_taken']);
                	if ($isAjax) {
                		$this->jsonResponse(false, $message);
                	}
                	header('Location: /?page=register&error=' . urlencode($message));
                	exit;
            }
        }

        // 2. Si on veut juste voir la page (GET)
        return "register.php";
    }

	public function verify() {
		// 1. On récupère le token dans l'URL (?page=verify&token=...)
		$token = $_GET['token'] ?? null;
        $this->flowLog('verify_received', ['hasToken' => !empty($token)]);

		if (!$token) {
            return "verify_failed.php"; // Affiche "Token manquant"
		}

		// 2. On demande au modèle de vérifier si ce token existe en base
		$user = $this->userModel->confirmAccount($token);

		if ($user) {
            $this->flowLog('verify_success');
			// Succès : Le compte est activé
			return "verify_success.php";
		} else {
            $this->flowLog('verify_failed');
			// Échec : Token invalide ou déjà utilisé
            return "verify_failed.php";
		}
	}

    public function studio() {
        return "pictureStudio.php";
    }
}
