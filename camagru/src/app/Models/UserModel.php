<?php
class UserModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function create($username, $email, $password, $token) {
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
            // Si le username ou l'email existe déjà, PDO lancera une exception
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
