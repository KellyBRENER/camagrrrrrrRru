<?php
require_once __DIR__ . '/../Core/Security.php';

final class PhotoNotifications {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function send($photoId, $actorId, $comment = null) {
        // An SMTP failure must not undo an already saved comment/like or invite retries.
        try {
            $stmt = $this->db->prepare('SELECT owner.email, actor.username
                FROM photos p JOIN users owner ON owner.id = p.user_id
                JOIN users actor ON actor.id = ?
                WHERE p.photo_id = ? AND owner.email_notifications = 1 AND owner.is_verified = 1');
            $stmt->execute([$actorId, $photoId]);
            $recipient = $stmt->fetch();
            if (!$recipient || !Security::validEmail($recipient['email'])) return;

            $actor = htmlspecialchars($recipient['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $gallery = htmlspecialchars(Security::appUrl() . '/?page=gallery', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $profile = htmlspecialchars(Security::appUrl() . '/?page=profil', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = '<p>' . $actor . ($comment === null ? ' a aimé' : ' a commenté') . ' votre montage n°' . (int) $photoId . '.</p>';
            if ($comment !== null) {
                $body .= '<blockquote>' . nl2br(htmlspecialchars($comment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</blockquote>';
            }
            $body .= '<p><a href="' . $gallery . '">Voir la galerie</a></p>'
                . '<p><a href="' . $profile . '">Gérer mes notifications</a></p>';
            $subject = $comment === null ? 'Camagru : nouveau like' : 'Camagru : nouveau commentaire';
            if (!@mail($recipient['email'], $subject, $body,
                "From: Camagru <no-reply@camagru.com>\r\nContent-Type: text/html; charset=UTF-8\r\n")) {
                error_log('Notification Camagru : envoi email impossible.');
            }
        } catch (Throwable $e) {
            error_log('Notification Camagru : traitement impossible.');
        }
    }
}
