<?php
class UserModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function create($username, $email, $password) {
        // 1. On hache le mot de passe
        // PASSWORD_DEFAULT utilise actuellement BCRYPT, c'est le plus sûr.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 2. On prépare la requête SQL
        $sql = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
        
        try {
            $stmt = $this->db->prepare($sql);
            
            // 3. On exécute avec les vraies valeurs
            return $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':password' => $hashedPassword
            ]);
        } catch (PDOException $e) {
            // Si le username ou l'email existe déjà, PDO lancera une exception
            error_log("Erreur lors de la création de l'utilisateur : " . $e->getMessage());
            return false;
        }
    }
}