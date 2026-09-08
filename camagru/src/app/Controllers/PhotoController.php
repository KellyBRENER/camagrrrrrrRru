<?php
require_once __DIR__ . '/../Models/PhotoModel.php';
require_once __DIR__ . '/../Core/Validation.php';
require_once __DIR__ . '/../Services/PhotoComposer.php';
require_once __DIR__ . '/../Services/PhotoNotifications.php';

class PhotoController {
    private $photoModel;
    private $composer;
    private $notifications;

    public function __construct($pdo) {
        $this->photoModel = new PhotoModel($pdo);
        $this->notifications = new PhotoNotifications($pdo);
        $this->composer = new PhotoComposer(__DIR__ . '/../../public');
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            $this->jsonResponse(false, 'Connexion requise.');
        }

        try {
            $payload = $this->readPayload();
            $sourceImage = $_FILES['image'] ?? $_FILES['photo'] ?? ($payload['image'] ?? $payload['source_image'] ?? null);
            $crop = $this->composer->validateCrop($this->readStructuredField($payload, 'crop', []));
            $frameId = $payload['frame_id'] ?? $payload['frameId'] ?? null;
            if ($frameId !== null && $frameId !== '') { Validation::identifier($frameId, 'Cadre'); }
            $hashtags = $this->readStructuredField($payload, 'hashtags', []);

            $hashtags = $this->validateRequestedHashtags($hashtags);

            $requestedStickers = $this->readStructuredField($payload, 'stickers', []);
            $requestedLayers = $this->readStructuredField($payload, 'layers', null);
            $layers = $this->buildRenderLayers($requestedLayers, $frameId, $requestedStickers);
            $stickers = array_values(array_filter($layers, function ($layer) {
                return ($layer['type'] ?? '') === 'sticker';
            }));

            if (count($layers) === 0) {
                throw new InvalidArgumentException('Choisissez au moins un cadre ou un sticker.');
            }

            $stickerIds = array_column($stickers, 'sticker_id');
            $created = $this->photoModel->create((int) $_SESSION['user_id'], $hashtags, $stickerIds);

            if (!$created) {
                throw new RuntimeException('Impossible de creer la photo en base.');
            }

            try {
                $this->composer->compose($sourceImage, $crop, $frameId, $stickers, $created['path'], $layers);
            } catch (Exception $e) {
                $this->deleteGeneratedFile($created['path']);
                $this->photoModel->deleteByOwner((int) $created['photo_id'], (int) $_SESSION['user_id']);
                throw $e;
            }

            $this->jsonResponse(true, 'Photo creee.', [
                'photo_id' => (int) $created['photo_id'],
                'path' => $created['path']
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            $this->jsonResponse(false, $e->getMessage());
        } catch (Exception $e) {
            error_log('Erreur creation montage photo : ' . $e->getMessage());
            http_response_code(500);
            $this->jsonResponse(false, 'Impossible de creer le montage.');
        }
    }

    public function mine() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            $this->jsonResponse(false, 'Connexion requise.');
        }

        $photos = $this->photoModel->getByUser((int) $_SESSION['user_id']);

        $this->jsonResponse(true, null, [
            'photos' => array_map(function ($photo) {
                return [
                    'photo_id' => (int) $photo['photo_id'],
                    'path' => $photo['path'],
                    'created_at' => $photo['created_at']
                ];
            }, $photos)
        ]);
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            $this->jsonResponse(false, 'Connexion requise.');
        }

        $payload = $this->readPayload();
        $photoId = Validation::integer($payload['photo_id'] ?? $payload['photoId'] ?? null, 1, 2147483647, 'Photo');

        if ($photoId <= 0) {
            http_response_code(400);
            $this->jsonResponse(false, 'Photo invalide.');
        }

        $photo = $this->photoModel->getById($photoId);

        if (!$photo || (int) $photo['user_id'] !== (int) $_SESSION['user_id']) {
            http_response_code(404);
            $this->jsonResponse(false, 'Photo introuvable.');
        }

        if (!$this->photoModel->deleteByOwner($photoId, (int) $_SESSION['user_id'])) {
            http_response_code(500);
            $this->jsonResponse(false, 'Impossible de supprimer la photo.');
        }

        $this->deleteGeneratedFile($photo['path']);
        $this->jsonResponse(true, 'Photo supprimee.');
    }

    public function publicList() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        $limit = Validation::integer($_GET['limit'] ?? 20, 1, 50, 'Limite');
        $offset = Validation::integer($_GET['offset'] ?? 0, 0, 2147483647, 'Décalage');
        $hashtag = trim(Validation::text($_GET['hashtag'] ?? '', 25, 'Hashtag', true));
        $viewerId = $_SESSION['user_id'] ?? null;

        if ($hashtag !== '' && (mb_strlen($hashtag, 'UTF-8') > 25 || !preg_match('/^\p{L}+(?:-\p{L}+)*$/u', $hashtag))) {
            http_response_code(400);
            $this->jsonResponse(false, 'Hashtag invalide.');
        }

        $total = $this->photoModel->countPublicPhotos($hashtag);
        // A bookmarked page can disappear after photos are deleted.
        if ($offset >= $total) {
            $offset = $total > 0 ? (int) (floor(($total - 1) / $limit) * $limit) : 0;
        }

        $photos = $hashtag === ''
            ? $this->photoModel->getPublicPhotos($limit, $offset, $viewerId)
            : $this->photoModel->getPublicPhotosByHashtag($hashtag, $limit, $offset, $viewerId);

        $this->jsonResponse(true, null, [
            'is_logged_in' => isset($_SESSION['user_id']),
            'hashtag' => $hashtag === '' ? null : mb_strtolower($hashtag, 'UTF-8'),
            'search_mode' => $hashtag === '' ? null : 'partial',
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
            'photos' => array_map([$this, 'serializePhoto'], $photos)
        ]);
    }

    public function hashtagList() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        $limit = Validation::integer($_GET['limit'] ?? 20, 1, 50, 'Limite');

        $this->jsonResponse(true, null, [
            'hashtags' => array_map(function ($hashtag) {
                return [
                    'hashtag' => $hashtag['hashtag'],
                    'photos_count' => (int) ($hashtag['photos_count'] ?? 0)
                ];
            }, $this->photoModel->getAvailableHashtags($limit))
        ]);
    }

    public function toggleLike() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            $this->jsonResponse(false, 'Connexion requise.');
        }

        $payload = $this->readPayload();
        $photoId = Validation::integer($payload['photo_id'] ?? $payload['photoId'] ?? null, 1, 2147483647, 'Photo');

        if ($photoId <= 0 || !$this->photoModel->getById($photoId)) {
            http_response_code(404);
            $this->jsonResponse(false, 'Photo introuvable.');
        }

        $userId = (int) $_SESSION['user_id'];
        $liked = $this->photoModel->isLikedByUser($photoId, $userId);

        if ($liked) {
            $this->photoModel->unlike($photoId, $userId);
            $liked = false;
        } else {
            if ($this->photoModel->like($photoId, $userId)) {
                $this->notifications->send($photoId, $userId);
            }
            $liked = true;
        }

        $this->jsonResponse(true, null, [
            'photo_id' => $photoId,
            'liked_by_user' => $liked,
            'likes_count' => $this->photoModel->countLikes($photoId)
        ]);
    }

    public function comments() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        $photoId = Validation::integer($_GET['photo_id'] ?? $_GET['photoId'] ?? null, 1, 2147483647, 'Photo');

        if ($photoId <= 0 || !$this->photoModel->getById($photoId)) {
            http_response_code(404);
            $this->jsonResponse(false, 'Photo introuvable.');
        }

        $this->jsonResponse(true, null, [
            'is_logged_in' => isset($_SESSION['user_id']),
            'photo_id' => $photoId,
            'comments' => array_map([$this, 'serializeComment'], $this->photoModel->getComments($photoId))
        ]);
    }

    public function addComment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->jsonResponse(false, 'Methode non autorisee.');
        }

        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            $this->jsonResponse(false, 'Connexion requise.');
        }

        $payload = $this->readPayload();
        $photoId = Validation::integer($payload['photo_id'] ?? $payload['photoId'] ?? null, 1, 2147483647, 'Photo');
        $comment = trim(Validation::text($payload['comment'] ?? '', 500, 'Commentaire', true));

        if ($photoId <= 0 || !$this->photoModel->getById($photoId)) {
            http_response_code(404);
            $this->jsonResponse(false, 'Photo introuvable.');
        }

        if ($comment === '') {
            http_response_code(400);
            $this->jsonResponse(false, 'Commentaire vide.');
        }

        if (mb_strlen($comment, 'UTF-8') > 500) {
            http_response_code(400);
            $this->jsonResponse(false, 'Commentaire limite a 500 caracteres.');
        }

        if (!$this->photoModel->addComment($photoId, (int) $_SESSION['user_id'], $comment)) {
            http_response_code(500);
            $this->jsonResponse(false, 'Impossible d ajouter le commentaire.');
        }

        $this->notifications->send($photoId, (int) $_SESSION['user_id'], $comment);

        $this->jsonResponse(true, null, [
            'photo_id' => $photoId,
            'comments_count' => $this->photoModel->countComments($photoId),
            'comments' => array_map([$this, 'serializeComment'], $this->photoModel->getComments($photoId))
        ]);
    }

    private function readPayload() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw);
            $payload = json_decode($raw, true);

            if (!$decoded instanceof stdClass || json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('JSON invalide.');
            }

            return $payload;
        }

        return $_POST;
    }

    private function readStructuredField($payload, $key, $default) {
        if (!array_key_exists($key, $payload)) {
            return $default;
        }

        if (is_string($payload[$key])) {
            $decoded = json_decode($payload[$key], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $payload[$key];
    }

    private function validateRequestedHashtags($hashtags) {
        if (is_string($hashtags)) {
            Validation::text($hashtags, 129, 'Hashtags', true);
            $hashtags = preg_split('/[\s,]+/', $hashtags);
        }

        if (!is_array($hashtags) || !array_is_list($hashtags) || count($hashtags) > 5) {
            throw new InvalidArgumentException('Liste de 5 mots-clés maximum attendue.');
        }

        $clean = [];

        foreach ($hashtags as $hashtag) {
            $hashtag = trim(Validation::text($hashtag, 25, 'Hashtag', true));

            if ($hashtag === '') {
                continue;
            }

            if (mb_strlen($hashtag, 'UTF-8') > 25) {
                throw new InvalidArgumentException('Chaque mot-cle doit faire 25 caracteres maximum.');
            }

            if (!preg_match('/^\p{L}+(?:-\p{L}+)*$/u', $hashtag)) {
                throw new InvalidArgumentException('Mots-cles invalides : lettres et tirets uniquement.');
            }

            $clean[] = mb_strtolower($hashtag, 'UTF-8');
        }

        $clean = array_values(array_unique($clean));

        if (count($clean) > 5) {
            throw new InvalidArgumentException('5 mots-cles maximum sont autorises.');
        }

        return $clean;
    }

    private function buildRenderLayers($requestedLayers, $frameId, $requestedStickers) {
        if ($requestedLayers !== null) {
            if (!is_array($requestedLayers) || !array_is_list($requestedLayers) || count($requestedLayers) > 11) {
                throw new InvalidArgumentException('Liste de 11 calques maximum attendue.');
            }
            return $this->normalizeRequestedLayers($requestedLayers);
        }

        $layers = [];

        if ($frameId !== null && $frameId !== '') {
            $layers[] = [
                'type' => 'frame',
                'frame_id' => $frameId
            ];
        }

        foreach ($this->buildStickerRenderData($requestedStickers) as $sticker) {
            $sticker['type'] = 'sticker';
            $layers[] = $sticker;
        }

        return $layers;
    }

    private function normalizeRequestedLayers($requestedLayers) {
        $layers = [];
        $frameCount = 0;
        $stickerCount = 0;

        foreach ($requestedLayers as $requestedLayer) {
            if (!is_array($requestedLayer)) {
                throw new InvalidArgumentException('Calque invalide.');
            }

            $type = $requestedLayer['type'] ?? null;

            if ($type === 'frame') {
                $frameCount += 1;

                if ($frameCount > 1) {
                    throw new InvalidArgumentException('Un seul cadre maximum est autorise.');
                }

                $frameId = $requestedLayer['frame_id'] ?? $requestedLayer['id'] ?? null;

                Validation::identifier($frameId, 'Cadre');
                if (!$frameId) {
                    throw new InvalidArgumentException('Cadre invalide.');
                }

                $layers[] = [
                    'type' => 'frame',
                    'frame_id' => $frameId
                ];
                continue;
            }

            if ($type === 'sticker') {
                $stickerCount += 1;

                if ($stickerCount > 10) {
                    throw new InvalidArgumentException('Dix stickers maximum sont autorises.');
                }

                $sticker = $this->buildStickerRenderData([$requestedLayer])[0] ?? null;

                if (!$sticker) {
                    throw new InvalidArgumentException('Sticker invalide.');
                }

                $sticker['type'] = 'sticker';
                $layers[] = $sticker;
                continue;
            }

            throw new InvalidArgumentException('Type de calque invalide.');
        }

        return $layers;
    }

    private function buildStickerRenderData($requestedStickers) {
        if ($requestedStickers === []) {
            return [];
        }

        if (is_string($requestedStickers)) {
            $requestedStickers = [$requestedStickers];
        }

        if (!is_array($requestedStickers)) {
            throw new InvalidArgumentException('Stickers invalides.');
        }

        if (isset($requestedStickers['sticker_id']) || isset($requestedStickers['id'])) {
            $requestedStickers = [$requestedStickers];
        }

        if (!array_is_list($requestedStickers) || count($requestedStickers) > 10) {
            throw new InvalidArgumentException('Dix stickers maximum sont autorises.');
        }

        $stickers = [];

        foreach ($requestedStickers as $requestedSticker) {
            if (is_string($requestedSticker)) {
                $requestedSticker = ['sticker_id' => $requestedSticker];
            }

            if (!is_array($requestedSticker)) {
                throw new InvalidArgumentException('Sticker invalide.');
            }

            $stickerId = $requestedSticker['sticker_id'] ?? $requestedSticker['id'] ?? null;
            foreach (['center_x_percent' => [-100, 200], 'x_percent' => [-100, 200],
                'center_y_percent' => [-100, 200], 'y_percent' => [-100, 200],
                'width_percent' => [0.001, 300], 'height_percent' => [0.001, 300],
                'rotation_degrees' => [-360, 360], 'rotation' => [-360, 360]] as $key => $range) {
                if (array_key_exists($key, $requestedSticker)) {
                    Validation::number($requestedSticker[$key], $range[0], $range[1], $key);
                }
            }
            Validation::identifier($stickerId, 'Sticker');
            $sticker = $this->photoModel->getActiveSticker($stickerId);

            if (!$sticker) {
                throw new InvalidArgumentException('Sticker invalide.');
            }

            $stickers[] = [
                'sticker_id' => $sticker['sticker_id'],
                'path' => $sticker['path'],
                'center_x_percent' => Validation::number($requestedSticker['center_x_percent'] ?? $requestedSticker['x_percent'] ?? 50, -100, 200, 'center_x_percent'),
                'center_y_percent' => Validation::number($requestedSticker['center_y_percent'] ?? $requestedSticker['y_percent'] ?? 50, -100, 200, 'center_y_percent'),
                'width_percent' => Validation::number($requestedSticker['width_percent'] ?? 25, 0.001, 300, 'width_percent'),
                'height_percent' => isset($requestedSticker['height_percent']) ? Validation::number($requestedSticker['height_percent'], 0.001, 300, 'height_percent') : null,
                'rotation_degrees' => Validation::number($requestedSticker['rotation_degrees'] ?? $requestedSticker['rotation'] ?? 0, -360, 360, 'rotation_degrees')
            ];
        }

        return $stickers;
    }

    private function deleteGeneratedFile($relativePath) {
        $relativePath = ltrim((string) $relativePath, '/');

        if (!preg_match('#^uploads/photos/[0-9]+/[0-9]+\.png$#', $relativePath)) {
            return;
        }

        $path = __DIR__ . '/../../public/' . $relativePath;

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function serializePhoto($photo) {
        return [
            'photo_id' => (int) $photo['photo_id'],
            'user_id' => (int) $photo['user_id'],
            'path' => $photo['path'],
            'created_at' => $photo['created_at'],
            'username' => $photo['username'] ?? '',
            'likes_count' => (int) ($photo['likes_count'] ?? 0),
            'comments_count' => (int) ($photo['comments_count'] ?? 0),
            'liked_by_user' => (bool) ($photo['liked_by_user'] ?? false)
        ];
    }

    private function serializeComment($comment) {
        return [
            'comment_id' => (int) $comment['comment_id'],
            'photo_id' => (int) $comment['photo_id'],
            'user_id' => (int) $comment['user_id'],
            'comment' => $comment['comment'],
            'created_at' => $comment['created_at'],
            'username' => $comment['username'] ?? ''
        ];
    }

    private function jsonResponse($success, $message = null, $extra = []) {
        header('Content-Type: application/json');
        $payload = array_merge(['success' => (bool) $success], $extra);

        if ($message !== null) {
            $payload['message'] = $message;
        }

        echo json_encode($payload);
        exit;
    }
}
