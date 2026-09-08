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

    public function createPasswordReset($userId, $tokenHash) {
        $this->db->beginTransaction();
        try {
            // Verrou par utilisateur : deux demandes simultanées ne peuvent pas envoyer deux liens.
            $lock = $this->db->prepare('SELECT id FROM users WHERE id = ? AND is_verified = 1 FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetchColumn()) {
                $this->db->rollBack();
                return false;
            }
            $recent = $this->db->prepare('SELECT user_id FROM password_resets WHERE user_id = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)');
            $recent->execute([$userId]);
            if ($recent->fetchColumn()) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, requested_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
                ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), requested_at = NOW()');
            $stmt->execute([$userId, $tokenHash]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function revokePasswordReset($tokenHash) {
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    public function hasValidPasswordReset($tokenHash) {
        $stmt = $this->db->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()');
        $stmt->execute([$tokenHash]);
        return (bool) $stmt->fetchColumn();
    }

    public function resetPassword($tokenHash, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([$tokenHash]);
            $userId = $stmt->fetchColumn();
            if (!$userId) {
                $this->db->rollBack();
                return false;
            }
            $update = $this->db->prepare('UPDATE users SET password = ? WHERE id = ? AND is_verified = 1');
            $update->execute([$hashedPassword, $userId]);
            $this->revokePasswordReset($tokenHash);
            $this->db->commit();
            return $update->rowCount() === 1;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getById($userId) {
        $stmt = $this->db->prepare('SELECT id, username, email, email_notifications FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function setEmailNotifications($userId, $enabled) {
        $stmt = $this->db->prepare('UPDATE users SET email_notifications = ? WHERE id = ?');
        return $stmt->execute([$enabled ? 1 : 0, $userId]);
    }

    public function updateAccount($userId, $currentPassword, $username = null, $email = null, $newPassword = null) {
        $this->lastError = null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT password, email FROM users WHERE id = ? AND is_verified = 1 FOR UPDATE');
            $stmt->execute([$userId]);
            $account = $stmt->fetch();
            if (!$account || !password_verify($currentPassword, $account['password'])) {
                $this->db->rollBack();
                $this->lastError = 'invalid_password';
                return false;
            }
            $hash = $account['password'];
            if ($newPassword !== null) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([$hash, $userId]);
            } else {
                $update = $this->db->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                $update->execute([$username, $email, $userId]);
            }
            if ($newPassword !== null || $email !== $account['email']) {
                // Old reset links must not survive a password or recovery address change.
                $delete = $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?');
                $delete->execute([$userId]);
            }
            $this->db->commit();
            return $hash;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->lastError = ($e->errorInfo[1] ?? null) === 1062 ? 'duplicate_user' : 'database_error';
            return false;
        }
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

    public function hasPendingVerification($token) {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE token = ? AND is_verified = 0 LIMIT 1');
        $stmt->execute([$token]);
        return (bool) $stmt->fetchColumn();
    }

    public function confirmAccount($token) {
        // Vérifier et consommer le token dans une seule opération atomique.
        $stmt = $this->db->prepare('UPDATE users SET is_verified = 1, token = NULL WHERE token = ? AND is_verified = 0');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }
}
