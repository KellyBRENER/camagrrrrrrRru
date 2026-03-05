<?php
class UserModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function create($username, $email, $password) {
        // ON HASHE TOUJOURS LE MOT DE PASSE (Sécurité 42 !)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$username, $email, $hashedPassword]);
        } catch (PDOException $e) {
            return false; // Probablement un doublon de username ou email
        }
    }

    public function getByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
}