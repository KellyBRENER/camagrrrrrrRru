<?php
class UserModel {
    private $db;
    private $lastError = null;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getByUsername($username) {
        $sql = "SELECT id, username, email, password, is_verified FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);

        return $stmt->fetch();
    }

    public function getByEmail($email) {
        $sql = "SELECT id, username, email, is_verified FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);

        return $stmt->fetch();
    }

    public function updateVerificationToken($userId, $token) {
        $sql = "UPDATE users SET token = ? WHERE id = ? AND is_verified = 0";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$token, $userId]) && $stmt->rowCount() > 0;
    }

    public function create($username, $email, $password, $token) {
        $this->lastError = null;

        // 1. On hache le mot de passe
        // PASSWORD_DEFAULT utilise actuellement BCRYPT, c'est le plus sûr.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 2. On prépare la requête SQL
        $sql = "INSERT INTO users (username, email, password, token) VALUES (:username, :email, :password, :token)";

        try {
            $stmt = $this->db->prepare($sql);

            // 3. On exécute avec les vraies valeurs
            return $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':password' => $hashedPassword,
				':token' => $token
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->lastError = 'duplicate_user';
            } else {
                $this->lastError = 'database_error';
            }

            error_log("Erreur lors de la création de l'utilisateur : " . $e->getMessage());
            return false;
        }
    }

    public function confirmAccount($token) {
    // 1. On cherche d'abord si le token existe et n'est pas encore validé
    $sql = "SELECT id FROM users WHERE token = ? AND is_verified = 0 LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Si trouvé, on passe is_verified à 1 et on nettoie le token
        $updateSql = "UPDATE users SET is_verified = 1, token = NULL WHERE id = ?";
        $updateStmt = $this->db->prepare($updateSql);
        
        // On retourne true si la mise à jour a réussi
        return $updateStmt->execute([$user['id']]);
    }

    // 3. Si le token est invalide, expiré ou déjà utilisé
    return false;
}
}
