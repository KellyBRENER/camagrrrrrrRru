<?php
require_once __DIR__ . '/../Models/UserModel.php';
require_once __DIR__ . '/../Core/Security.php';

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

    private function buildVerificationLink($token) {
        return Security::appUrl() . '/?page=verify&token=' . urlencode($token);
    }

    private function sendVerificationEmail($email, $username, $token) {
        $link = $this->buildVerificationLink($token);
        $subject = "Activez votre compte Camagru 🐆";

        $headers = [
            "From" => "Camagru <no-reply@camagru.com>",
            "Reply-To" => "no-reply@camagru.com",
            "Content-Type" => "text/html; charset=UTF-8",
            "X-Mailer" => "PHP/" . phpversion()
        ];

        $safeUsername = htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = "
            <html>
            <head>
                <title>Confirmation d'inscription</title>
            </head>
            <body style='font-family: Georgia, serif; background-color: #f5e6d3; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; border: 3px solid #8b6f47; border-radius: 15px; padding: 30px;'>
                    <h1 style='color: #6b4423;'>Bienvenue, $safeUsername !</h1>
                    <p>Ton compte est presque prêt. Clique sur le lien ci-dessous pour le valider :</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='$safeLink' style='background: #ff8c42; color: white; padding: 15px 25px; text-decoration: none; border-radius: 25px; font-weight: bold; border: 2px solid #6b4423;'>
                            ACTIVER MON COMPTE
                        </a>
                    </p>
                    <p style='font-size: 0.8rem; color: #8b6f47;'>Si le bouton ne fonctionne pas, copie ce lien : <br> $safeLink</p>
                </div>
            </body>
            </html>
        ";

        $headerString = "";
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        return mail($email, $subject, $body, $headerString);
    }
    //POST username + mot de passe pour se connecter
    //GET pour afficher le formulaire de connexion
    public function login() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (!Security::validText($username, 50) || $username === ''
            || !Security::validText($password, 72) || strlen($password) > 72 || $password === '') {
            $this->jsonResponse(false, 'Identifiants incorrects ou compte non vérifié.');
        }

        // 1. On cherche l'utilisateur par son pseudo
        $user = $this->userModel->getByUsername($username);

        // 2. On vérifie si l'utilisateur existe ET si le mot de passe est bon
        if ($user && password_verify($password, $user['password']) && $user['is_verified']) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['password_version'] = hash('sha256', $user['password']);
            // On remplit la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            echo json_encode(['success' => true]);
            exit;
        } else {
            // Sécurité : on ne dit pas si c'est le pseudo ou le mot de passe qui est faux
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects ou compte non vérifié.']);
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

            $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!Security::validText($username, 50) || !preg_match('/^[\p{L}\p{N}_.-]+$/u', $username) || strlen($email) > 100) {
                $message = 'Pseudo invalide (50 caractères maximum : lettres, chiffres, _, . ou -) ou email trop long.';
                if ($isAjax) { $this->jsonResponse(false, $message); }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
            $this->flowLog('register_payload_parsed', [
                'requestId' => $requestId,
                'username' => $username,
                'email' => $email,
                'passwordLength' => strlen($password)
            ]);

            // Validation simple (tu pourras ajouter des regex plus tard)
            if ($username === '' || $email === '' || $password === '') {
                $message = 'Veuillez remplir tous les champs.';
                $this->flowLog('register_validation_failed', ['requestId' => $requestId, 'reason' => 'empty_fields']);
                if ($isAjax) {
                    $this->jsonResponse(false, $message);
                }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
            if (!Security::validPassword($password)) {
				$message = 'Le mot de passe doit contenir 8 caractères, un chiffre et une lettre.';
                $this->flowLog('register_validation_failed', ['requestId' => $requestId, 'reason' => 'weak_password']);
                if ($isAjax) {
                    $this->jsonResponse(false, $message);
                }
                header('Location: /?page=register&error=' . urlencode($message));
                exit;
            }
            if (!Security::validEmail($email)) {
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
                $this->flowLog('register_verify_link_built', ['requestId' => $requestId, 'host' => $_SERVER['HTTP_HOST'] ?? 'localhost']);

    			$mailSent = $this->sendVerificationEmail($email, $username, $token);

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
                    $createError = $this->userModel->getLastError();
                    if ($createError === 'duplicate_user') {
                        $message = 'Nom d\'utilisateur ou email déjà pris.';
                    } else {
                        $message = 'Erreur de base de données. Vérifiez que la base est initialisée.';
                    }
	                	$this->flowLog('register_create_failed', ['requestId' => $requestId, 'reason' => $createError ?? 'unknown']);
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

    public function resendValidation() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

            if (!Security::validEmail($email)) {
                header('Location: /?page=resend_validation&error=' . urlencode('Adresse email invalide.'));
                exit;
            }

            $user = $this->userModel->getByEmail($email);

            if (!$user) {
                header('Location: /?page=resend_validation&error=' . urlencode('Aucun compte ne correspond a cet email.'));
                exit;
            }

            if ($user['is_verified']) {
                header('Location: /?page=resend_validation&success=' . urlencode('Ce compte est deja active. Vous pouvez vous connecter.'));
                exit;
            }

            $token = bin2hex(random_bytes(32));
            $tokenUpdated = $this->userModel->updateVerificationToken($user['id'], $token);

            if (!$tokenUpdated) {
                header('Location: /?page=resend_validation&error=' . urlencode('Impossible de regenerer le lien de validation.'));
                exit;
            }

            if (!$this->sendVerificationEmail($user['email'], $user['username'], $token)) {
                header('Location: /?page=resend_validation&error=' . urlencode("Erreur lors de l'envoi du mail."));
                exit;
            }

            header('Location: /?page=resend_validation&success=' . urlencode('Un nouveau lien de validation vient d\'etre envoye.'));
            exit;
        }

        return "resend_validation.php";
    }

    public function verify() {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        $input = $isPost ? $_POST : $_GET;
        $token = is_string($input['token'] ?? null) ? $input['token'] : '';
        $this->flowLog('verify_received', ['hasToken' => $token !== '', 'isPost' => $isPost]);

        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return 'verify_failed.php';
        }

        // Le GET du lien email ne consomme jamais le token et n'active pas le compte.
        if (!$isPost) {
            return $this->userModel->hasPendingVerification($token)
                ? 'verify_confirm.php'
                : 'verify_failed.php';
        }

        // Le POST est déjà protégé par le contrôle CSRF central dans index.php.
        if ($this->userModel->confirmAccount($token)) {
            $this->flowLog('verify_success');
            return 'verify_success.php';
        }

        $this->flowLog('verify_failed');
        return 'verify_failed.php';
    }

    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            // Réponse identique, que le compte existe, soit actif ou soit limité en fréquence.
            if (Security::validEmail($email)) {
                $user = $this->userModel->getByEmail($email);
                if ($user && $user['is_verified']) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    if ($this->userModel->createPasswordReset($user['id'], $tokenHash)) {
                        $link = Security::appUrl() . '/?page=reset_password&token=' . urlencode($token);
                        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $body = '<p>Pour choisir un nouveau mot de passe Camagru, cliquez sur le lien ci-dessous.</p>'
                            . '<p><a href="' . $safeLink . '">Réinitialiser mon mot de passe</a></p>'
                            . '<p>Ce lien est valable 30 minutes et ne peut être utilisé qu’une fois.</p>'
                            . '<p>Si vous n’avez pas demandé ce changement, ignorez cet email.</p>';
                        if (!mail($user['email'], 'Réinitialisation de votre mot de passe Camagru', $body,
                            "From: Camagru <no-reply@camagru.com>\r\nContent-Type: text/html; charset=UTF-8\r\n")) {
                            $this->userModel->revokePasswordReset($tokenHash);
                            error_log('Impossible d envoyer le mail de réinitialisation.');
                        }
                    }
                }
            }
            header('Location: /?page=forgot_password&sent=1', true, 303);
            exit;
        }
        return 'forgot_password.php';
    }

    public function resetPassword() {
        // Retirer le secret de l'URL avant de charger la page et ses ressources.
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
            $token = is_string($_GET['token']) ? $_GET['token'] : '';
            $_SESSION['reset_token_hash'] = preg_match('/^[a-f0-9]{64}$/D', $token) ? hash('sha256', $token) : '';
            header('Location: /?page=reset_password', true, 303);
            exit;
        }
        $tokenHash = $_SESSION['reset_token_hash'] ?? '';
        $_SESSION['reset_form_valid'] = $tokenHash !== '' && $this->userModel->hasValidPasswordReset($tokenHash);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['reset_form_valid']) {
            $password = $_POST['password'] ?? null;
            $confirmation = $_POST['password_confirmation'] ?? null;
            if (!Security::validPassword($password)) {
                $_SESSION['reset_error'] = 'Utilisez un mot de passe d’au moins 8 caractères, avec une lettre et un chiffre, et évitez les mots de passe trop longs.';
            } elseif (!is_string($confirmation) || $password !== $confirmation) {
                $_SESSION['reset_error'] = 'Les deux mots de passe ne correspondent pas.';
            } elseif ($this->userModel->resetPassword($tokenHash, $password)) {
                unset($_SESSION['reset_token_hash'], $_SESSION['reset_form_valid'], $_SESSION['reset_error'],
                    $_SESSION['user_id'], $_SESSION['username'], $_SESSION['password_version']);
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /?page=login&password_reset=1', true, 303);
                exit;
            } else {
                $_SESSION['reset_form_valid'] = false;
            }
        }
        return 'reset_password.php';
    }

    public $profile = [];

    public function profil() {
        $this->profile = $this->userModel->getById($_SESSION['user_id']);
        return 'profil.php';
    }

    private function profileResponse($success, $message, $status = 400) {
        if ($this->isAjaxRequest()) {
            http_response_code($success ? 200 : $status);
            $this->jsonResponse($success, $message);
        }
        $_SESSION['profile_notice'] = ['success' => $success, 'message' => $message];
        header('Location: /?page=profil', true, 303);
        exit;
    }

    private function requireProfilePost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            $this->jsonResponse(false, 'Méthode non autorisée.');
        }
    }

    private function finishAccountUpdate($hash, $message) {
        if ($hash === false) {
            $error = $this->userModel->getLastError();
            $messages = ['invalid_password' => 'Mot de passe actuel incorrect.',
                'duplicate_user' => 'Ce pseudo ou cette adresse email est déjà utilisé.',
                'database_error' => 'Impossible d’enregistrer les modifications. Réessayez.'];
            $this->profileResponse(false, $messages[$error] ?? $messages['database_error'], $error === 'database_error' ? 500 : 400);
        }
        $profile = $this->userModel->getById($_SESSION['user_id']);
        $_SESSION['username'] = $profile['username'];
        $_SESSION['password_version'] = hash('sha256', $hash);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if ($this->isAjaxRequest()) {
            $_SESSION['profile_notice'] = ['success' => true, 'message' => $message];
        }
        $this->profileResponse(true, $message);
    }

    public function updateNotifications() {
        $this->requireProfilePost();
        $value = $_POST['email_notifications'] ?? null;
        if (!is_string($value) || !in_array($value, ['0', '1'], true)) {
            $this->profileResponse(false, 'Préférence de notification invalide.');
        }
        try {
            $this->userModel->setEmailNotifications($_SESSION['user_id'], $value === '1');
        } catch (PDOException $e) {
            $this->profileResponse(false, 'Impossible d’enregistrer vos préférences. Réessayez.', 500);
        }
        $message = $value === '1' ? 'Notifications par email activées.' : 'Notifications par email désactivées.';
        if ($this->isAjaxRequest()) {
            $_SESSION['profile_notice'] = ['success' => true, 'message' => $message];
        }
        $this->profileResponse(true, $message);
    }

    public function updateProfile() {
        $this->requireProfilePost();
        $username = $_POST['username'] ?? null;
        $email = $_POST['email'] ?? null;
        $currentPassword = $_POST['current_password'] ?? null;
        if (!Security::validText($username, 50) || !preg_match('/^[\p{L}\p{N}_.-]+$/u', $username)) {
            $this->profileResponse(false, 'Pseudo invalide : 50 caractères maximum, lettres, chiffres, _, . ou -.');
        }
        if (!Security::validEmail($email)) {
            $this->profileResponse(false, 'Adresse email invalide (100 caractères maximum).');
        }
        if (!Security::validText($currentPassword, 72) || strlen($currentPassword) > 72 || $currentPassword === '') {
            $this->profileResponse(false, 'Saisissez votre mot de passe actuel.');
        }
        $hash = $this->userModel->updateAccount($_SESSION['user_id'], $currentPassword, $username, $email);
        $this->finishAccountUpdate($hash, 'Vos informations ont été mises à jour.');
    }

    public function updatePassword() {
        $this->requireProfilePost();
        $currentPassword = $_POST['old_password'] ?? null;
        $newPassword = $_POST['new_password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;
        if (!Security::validText($currentPassword, 72) || strlen($currentPassword) > 72 || $currentPassword === '') {
            $this->profileResponse(false, 'Saisissez votre mot de passe actuel.');
        }
        if (!Security::validPassword($newPassword)) {
            $this->profileResponse(false, 'Utilisez au moins 8 caractères, une lettre et un chiffre, et au plus 72 octets.');
        }
        if (!is_string($confirmation) || $newPassword !== $confirmation) {
            $this->profileResponse(false, 'Les deux nouveaux mots de passe ne correspondent pas.');
        }
        $hash = $this->userModel->updateAccount($_SESSION['user_id'], $currentPassword, null, null, $newPassword);
        $this->finishAccountUpdate($hash, 'Mot de passe modifié. Les autres sessions ont été déconnectées.');
    }

    public function studio() {
        return "studio.php";
    }
}
