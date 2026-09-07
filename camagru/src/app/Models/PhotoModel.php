<?php
class PhotoModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function create($userId, $hashtags = [], $stickerIds = []) {
        $this->db->beginTransaction();

        try {
            $sql = "
                INSERT INTO photos (user_id, path)
                VALUES (:user_id, '')
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            $photoId = (int) $this->db->lastInsertId();
            $path = $this->buildRelativePhotoPath($userId, $photoId);
            $this->updatePath($photoId, $path);
            $this->syncHashtags($photoId, $this->buildCreateHashtags($hashtags, $stickerIds));

            $this->db->commit();
            return [
                'photo_id' => $photoId,
                'path' => $path
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la creation de la photo : " . $e->getMessage());
            return false;
        }
    }

    public function buildRelativePhotoPath($userId, $photoId) {
        return sprintf('uploads/photos/%d/%d.png', (int) $userId, (int) $photoId);
    }

    public function updatePath($photoId, $path) {
        $sql = "UPDATE photos SET path = :path WHERE photo_id = :photo_id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':photo_id' => $photoId,
            ':path' => $path
        ]);
    }

    public function getById($photoId) {
        $sql = "
            SELECT
                p.photo_id,
                p.user_id,
                p.path,
                p.created_at,
                u.username,
                COUNT(DISTINCT l.user_id) AS likes_count,
                COUNT(DISTINCT c.comment_id) AS comments_count
            FROM photos p
            INNER JOIN users u ON u.id = p.user_id
            LEFT JOIN photo_likes l ON l.photo_id = p.photo_id
            LEFT JOIN comments c ON c.photo_id = p.photo_id
            WHERE p.photo_id = :photo_id
            GROUP BY p.photo_id, p.user_id, p.path, p.created_at, u.username
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':photo_id' => $photoId]);

        return $stmt->fetch();
    }

    public function getPublicPhotos($limit = 5, $offset = 0, $viewerId = null) {
        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);
        $viewerId = $viewerId ? (int) $viewerId : null;

        $sql = "
            SELECT
                p.photo_id,
                p.user_id,
                p.path,
                p.created_at,
                u.username,
                COUNT(DISTINCT l.user_id) AS likes_count,
                COUNT(DISTINCT c.comment_id) AS comments_count,
                MAX(CASE WHEN l.user_id = :viewer_id THEN 1 ELSE 0 END) AS liked_by_user
            FROM photos p
            INNER JOIN users u ON u.id = p.user_id
            LEFT JOIN photo_likes l ON l.photo_id = p.photo_id
            LEFT JOIN comments c ON c.photo_id = p.photo_id
            GROUP BY p.photo_id, p.user_id, p.path, p.created_at, u.username
            ORDER BY p.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':viewer_id', $viewerId, $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getPublicPhotosByHashtag($hashtag, $limit = 5, $offset = 0, $viewerId = null) {
        $hashtag = $this->normalizeHashtag($hashtag);

        if ($hashtag === '') {
            return [];
        }

        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);
        $viewerId = $viewerId ? (int) $viewerId : null;

        $sql = "
            SELECT
                p.photo_id,
                p.user_id,
                p.path,
                p.created_at,
                u.username,
                COUNT(DISTINCT l.user_id) AS likes_count,
                COUNT(DISTINCT c.comment_id) AS comments_count,
                MAX(CASE WHEN l.user_id = :viewer_id THEN 1 ELSE 0 END) AS liked_by_user
            FROM photos p
            INNER JOIN users u ON u.id = p.user_id
            INNER JOIN photo_hashtags ph_filter ON ph_filter.photo_id = p.photo_id
            INNER JOIN hashtags h_filter ON h_filter.hashtag_id = ph_filter.hashtag_id
            LEFT JOIN photo_likes l ON l.photo_id = p.photo_id
            LEFT JOIN comments c ON c.photo_id = p.photo_id
            WHERE h_filter.hashtag LIKE :hashtag
            GROUP BY p.photo_id, p.user_id, p.path, p.created_at, u.username
            ORDER BY p.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':viewer_id', $viewerId, $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':hashtag', '%' . $hashtag . '%');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getByUser($userId) {
        $sql = "
            SELECT photo_id, user_id, path, created_at
            FROM photos
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function deleteByOwner($photoId, $userId) {
        $sql = "DELETE FROM photos WHERE photo_id = :photo_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':photo_id' => $photoId,
            ':user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function like($photoId, $userId) {
        $sql = "
            INSERT IGNORE INTO photo_likes (photo_id, user_id)
            VALUES (:photo_id, :user_id)
        ";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':photo_id' => $photoId,
            ':user_id' => $userId
        ]);
    }

    public function unlike($photoId, $userId) {
        $sql = "DELETE FROM photo_likes WHERE photo_id = :photo_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':photo_id' => $photoId,
            ':user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countLikes($photoId) {
        $sql = "SELECT COUNT(*) FROM photo_likes WHERE photo_id = :photo_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':photo_id' => $photoId]);

        return (int) $stmt->fetchColumn();
    }

    public function isLikedByUser($photoId, $userId) {
        $sql = "SELECT 1 FROM photo_likes WHERE photo_id = :photo_id AND user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':photo_id' => $photoId,
            ':user_id' => $userId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function addComment($photoId, $userId, $comment) {
        $comment = trim($comment);

        if ($comment === '') {
            return false;
        }

        if (mb_strlen($comment, 'UTF-8') > 500) {
            $comment = mb_substr($comment, 0, 500, 'UTF-8');
        }

        $sql = "
            INSERT INTO comments (photo_id, user_id, comment)
            VALUES (:photo_id, :user_id, :comment)
        ";
        $stmt = $this->db->prepare($sql);

        if (!$stmt->execute([
            ':photo_id' => $photoId,
            ':user_id' => $userId,
            ':comment' => $comment
        ])) {
            return false;
        }

        return (int) $this->db->lastInsertId();
    }

    public function getComments($photoId) {
        $sql = "
            SELECT
                c.comment_id,
                c.photo_id,
                c.user_id,
                c.comment,
                c.created_at,
                c.updated_at,
                u.username
            FROM comments c
            INNER JOIN users u ON u.id = c.user_id
            WHERE c.photo_id = :photo_id
            ORDER BY c.created_at ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':photo_id' => $photoId]);

        return $stmt->fetchAll();
    }

    public function countComments($photoId) {
        $sql = "SELECT COUNT(*) FROM comments WHERE photo_id = :photo_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':photo_id' => $photoId]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteCommentByOwner($commentId, $userId) {
        $sql = "DELETE FROM comments WHERE comment_id = :comment_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':comment_id' => $commentId,
            ':user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function searchByHashtag($hashtag, $limit = 5, $offset = 0) {
        $hashtag = $this->normalizeHashtag($hashtag);

        if ($hashtag === '') {
            return [];
        }

        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);

        $sql = "
            SELECT p.photo_id, p.user_id, p.path, p.created_at, u.username
            FROM photos p
            INNER JOIN users u ON u.id = p.user_id
            INNER JOIN photo_hashtags ph ON ph.photo_id = p.photo_id
            INNER JOIN hashtags h ON h.hashtag_id = ph.hashtag_id
            WHERE h.hashtag = :hashtag
            ORDER BY p.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':hashtag' => $hashtag]);

        return $stmt->fetchAll();
    }

    public function getAvailableHashtags($limit = 20) {
        $limit = max(1, min(50, (int) $limit));

        $sql = "
            SELECT h.hashtag, COUNT(DISTINCT ph.photo_id) AS photos_count
            FROM hashtags h
            INNER JOIN photo_hashtags ph ON ph.hashtag_id = h.hashtag_id
            INNER JOIN photos p ON p.photo_id = ph.photo_id
            GROUP BY h.hashtag_id, h.hashtag
            ORDER BY photos_count DESC, h.hashtag ASC
            LIMIT $limit
        ";

        return $this->db->query($sql)->fetchAll();
    }

    public function getHashtags($photoId) {
        $sql = "
            SELECT h.hashtag
            FROM hashtags h
            INNER JOIN photo_hashtags ph ON ph.hashtag_id = h.hashtag_id
            WHERE ph.photo_id = :photo_id
            ORDER BY h.hashtag ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':photo_id' => $photoId]);

        return array_column($stmt->fetchAll(), 'hashtag');
    }

    public function syncHashtags($photoId, $hashtags) {
        $normalized = $this->normalizeHashtags($hashtags);

        $deleteSql = "DELETE FROM photo_hashtags WHERE photo_id = :photo_id";
        $deleteStmt = $this->db->prepare($deleteSql);
        $deleteStmt->execute([':photo_id' => $photoId]);

        foreach ($normalized as $hashtag) {
            $hashtagId = $this->findOrCreateHashtag($hashtag);

            if (!$hashtagId) {
                continue;
            }

            $sql = "
                INSERT IGNORE INTO photo_hashtags (photo_id, hashtag_id)
                VALUES (:photo_id, :hashtag_id)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':photo_id' => $photoId,
                ':hashtag_id' => $hashtagId
            ]);
        }

        return true;
    }

    public function buildCreateHashtags($hashtags, $stickerIds = []) {
        $normalized = $this->normalizeHashtags($hashtags, 5);

        if (is_string($stickerIds)) {
            $stickerIds = [$stickerIds];
        }

        if (!is_array($stickerIds)) {
            $stickerIds = [];
        }

        foreach (array_unique($stickerIds) as $stickerId) {
            $sticker = $this->getActiveSticker($stickerId);

            if ($sticker && !empty($sticker['hashtag'])) {
                $normalized[] = $sticker['hashtag'];
            }
        }

        return $this->normalizeHashtags($normalized);
    }

    public function getActiveSticker($stickerId) {
        $sql = "
            SELECT sticker_id, path, width, height, hashtag, is_active
            FROM stickers
            WHERE sticker_id = :sticker_id AND is_active = 1
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sticker_id' => $stickerId]);

        return $stmt->fetch();
    }

    public function getActiveStickers() {
        $sql = "
            SELECT sticker_id, path, width, height, hashtag, is_active
            FROM stickers
            WHERE is_active = 1
            ORDER BY sticker_id ASC
        ";

        return $this->db->query($sql)->fetchAll();
    }

    private function findOrCreateHashtag($hashtag) {
        $selectSql = "SELECT hashtag_id FROM hashtags WHERE hashtag = :hashtag LIMIT 1";
        $selectStmt = $this->db->prepare($selectSql);
        $selectStmt->execute([':hashtag' => $hashtag]);
        $existingId = $selectStmt->fetchColumn();

        if ($existingId) {
            return (int) $existingId;
        }

        $insertSql = "INSERT INTO hashtags (hashtag) VALUES (:hashtag)";
        $insertStmt = $this->db->prepare($insertSql);

        if (!$insertStmt->execute([':hashtag' => $hashtag])) {
            return false;
        }

        return (int) $this->db->lastInsertId();
    }

    private function normalizeHashtags($hashtags, $limit = null) {
        if (is_string($hashtags)) {
            $hashtags = preg_split('/[\s,]+/', $hashtags);
        }

        if (!is_array($hashtags)) {
            return [];
        }

        $normalized = [];

        foreach ($hashtags as $hashtag) {
            $clean = $this->normalizeHashtag($hashtag);

            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        $normalized = array_values(array_unique($normalized));

        if ($limit !== null) {
            return array_slice($normalized, 0, max(0, (int) $limit));
        }

        return $normalized;
    }

    private function normalizeHashtag($hashtag) {
        $hashtag = trim((string) $hashtag);
        $hashtag = mb_strtolower($hashtag, 'UTF-8');

        if (mb_strlen($hashtag, 'UTF-8') > 25 || !preg_match('/^\p{L}+(?:-\p{L}+)*$/u', $hashtag)) {
            return '';
        }

        return $hashtag;
    }
}
