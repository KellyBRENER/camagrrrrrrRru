<?php

class PhotoComposer {
    private const OUTPUT_SIZE = 1024;
    private const MAX_SOURCE_BYTES = 10485760;
    private const MAX_SOURCE_PIXELS = 20000000;

    private $publicRoot;
    private $filtersManifestPath;

    public function __construct($publicRoot) {
        $resolvedPublicRoot = realpath($publicRoot);

        if (!$resolvedPublicRoot || !is_dir($resolvedPublicRoot)) {
            throw new InvalidArgumentException('Dossier public invalide.');
        }

        $this->publicRoot = rtrim($resolvedPublicRoot, '/');
        $this->filtersManifestPath = $this->publicRoot . '/images/filters/filters.json';
    }

    public function compose($sourceImage, $crop, $frameId, $stickers, $relativeOutputPath, $layers = null) {
        $source = $this->loadSourceImage($sourceImage);
        $canvas = $this->createTransparentCanvas(self::OUTPUT_SIZE, self::OUTPUT_SIZE);

        try {
            $cropBox = $this->normalizeCrop($crop, imagesx($source), imagesy($source));
            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                $cropBox['x'],
                $cropBox['y'],
                self::OUTPUT_SIZE,
                self::OUTPUT_SIZE,
                $cropBox['width'],
                $cropBox['height']
            );

            $layers = $this->normalizeLayers($frameId, $stickers, $layers);

            foreach ($layers as $layer) {
                if (($layer['type'] ?? '') === 'frame') {
                    $this->drawFrame($canvas, $layer['frame_id'] ?? null);
                    continue;
                }

                if (($layer['type'] ?? '') === 'sticker') {
                    $this->drawSticker($canvas, $layer);
                }
            }

            $absoluteOutputPath = $this->resolveOutputPath($relativeOutputPath);
            $this->ensureOutputDirectory(dirname($absoluteOutputPath));

            if (!imagepng($canvas, $absoluteOutputPath)) {
                throw new RuntimeException('Impossible d\'ecrire le fichier final.');
            }

            return true;
        } finally {
            imagedestroy($source);
            imagedestroy($canvas);
        }
    }

    public function getOutputSize() {
        return self::OUTPUT_SIZE;
    }

    private function normalizeLayers($frameId, $stickers, $layers) {
        if (is_array($layers)) {
            return $layers;
        }

        $normalized = [];

        if ($frameId !== null && $frameId !== '') {
            $normalized[] = [
                'type' => 'frame',
                'frame_id' => $frameId
            ];
        }

        foreach ($stickers as $sticker) {
            $sticker['type'] = 'sticker';
            $normalized[] = $sticker;
        }

        return $normalized;
    }

    private function loadSourceImage($sourceImage) {
        if (is_array($sourceImage) && isset($sourceImage['tmp_name'])) {
            return $this->loadUploadedFile($sourceImage);
        }

        if (!is_string($sourceImage) || trim($sourceImage) === '') {
            throw new InvalidArgumentException('Image source manquante.');
        }

        if (strpos($sourceImage, 'data:image/') === 0) {
            return $this->loadDataUrl($sourceImage);
        }

        return $this->loadRawBase64($sourceImage);
    }

    private function loadUploadedFile($file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload image invalide.');
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_SOURCE_BYTES) {
            throw new InvalidArgumentException('Image source trop volumineuse.');
        }

        $bytes = file_get_contents($file['tmp_name']);

        return $this->createImageFromBytes($bytes);
    }

    private function loadDataUrl($dataUrl) {
        if (!preg_match('#^data:image/(png|jpe?g);base64,#i', $dataUrl, $matches)) {
            throw new InvalidArgumentException('Format image source non supporte.');
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);

        return $this->loadRawBase64($base64);
    }

    private function loadRawBase64($base64) {
        $bytes = base64_decode($base64, true);

        if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > self::MAX_SOURCE_BYTES) {
            throw new InvalidArgumentException('Image source invalide.');
        }

        return $this->createImageFromBytes($bytes);
    }

    private function createImageFromBytes($bytes) {
        $size = getimagesizefromstring($bytes);

        if (!$size || empty($size[0]) || empty($size[1])) {
            throw new InvalidArgumentException('Image source illisible.');
        }

        if ($size[0] * $size[1] > self::MAX_SOURCE_PIXELS) {
            throw new InvalidArgumentException('Image source trop grande.');
        }

        $image = imagecreatefromstring($bytes);

        if (!$image) {
            throw new InvalidArgumentException('Image source illisible.');
        }

        return $image;
    }

    private function normalizeCrop($crop, $sourceWidth, $sourceHeight) {
        if (!is_array($crop)) {
            $crop = [];
        }

        $xPercent = $this->clampFloat($crop['x_percent'] ?? 0, 0, 100);
        $yPercent = $this->clampFloat($crop['y_percent'] ?? 0, 0, 100);
        $widthPercent = $this->clampFloat($crop['width_percent'] ?? 100, 0.001, 100);
        $heightPercent = $this->clampFloat($crop['height_percent'] ?? 100, 0.001, 100);

        $x = (int) round($sourceWidth * $xPercent / 100);
        $y = (int) round($sourceHeight * $yPercent / 100);
        $width = (int) round($sourceWidth * $widthPercent / 100);
        $height = (int) round($sourceHeight * $heightPercent / 100);

        $x = min(max(0, $x), max(0, $sourceWidth - 1));
        $y = min(max(0, $y), max(0, $sourceHeight - 1));
        $width = min(max(1, $width), $sourceWidth - $x);
        $height = min(max(1, $height), $sourceHeight - $y);

        return [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height
        ];
    }

    private function resolveFramePath($frameId) {
        $filters = $this->readFiltersManifest();

        foreach ($filters as $filter) {
            if (($filter['id'] ?? null) === $frameId && ($filter['type'] ?? 'frame') === 'frame') {
                return $this->resolvePublicPath($filter['file'] ?? '');
            }
        }

        throw new InvalidArgumentException('Cadre invalide.');
    }

    private function drawFrame($canvas, $frameId) {
        $framePath = $this->resolveFramePath($frameId);
        $frame = $this->loadPng($framePath);
        imagecopyresampled(
            $canvas,
            $frame,
            0,
            0,
            0,
            0,
            self::OUTPUT_SIZE,
            self::OUTPUT_SIZE,
            imagesx($frame),
            imagesy($frame)
        );
        imagedestroy($frame);
    }

    private function readFiltersManifest() {
        if (!is_file($this->filtersManifestPath)) {
            throw new RuntimeException('Manifeste des filtres introuvable.');
        }

        $filters = json_decode(file_get_contents($this->filtersManifestPath), true);

        if (!is_array($filters)) {
            throw new RuntimeException('Manifeste des filtres invalide.');
        }

        return $filters;
    }

    private function drawSticker($canvas, $sticker) {
        if (empty($sticker['path'])) {
            throw new InvalidArgumentException('Sticker invalide.');
        }

        $source = $this->loadPng($this->resolvePublicPath($sticker['path']));
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $widthPercent = $this->clampFloat($sticker['width_percent'] ?? 25, 0.001, 300);
        $targetWidth = max(1, (int) round(self::OUTPUT_SIZE * $widthPercent / 100));

        if (isset($sticker['height_percent'])) {
            $heightPercent = $this->clampFloat($sticker['height_percent'], 0.001, 300);
            $targetHeight = max(1, (int) round(self::OUTPUT_SIZE * $heightPercent / 100));
        } else {
            $targetHeight = max(1, (int) round($targetWidth * $sourceHeight / $sourceWidth));
        }

        $resized = $this->createTransparentCanvas($targetWidth, $targetHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        $rotation = $this->clampFloat($sticker['rotation_degrees'] ?? 0, -360, 360);
        if (abs($rotation) > 0.001) {
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            $rotated = imagerotate($resized, -$rotation, $transparent);
            imagesavealpha($rotated, true);
            imagedestroy($resized);
            $resized = $rotated;
        }

        $centerX = self::OUTPUT_SIZE * $this->clampFloat($sticker['center_x_percent'] ?? 50, -100, 200) / 100;
        $centerY = self::OUTPUT_SIZE * $this->clampFloat($sticker['center_y_percent'] ?? 50, -100, 200) / 100;
        $destX = (int) round($centerX - imagesx($resized) / 2);
        $destY = (int) round($centerY - imagesy($resized) / 2);

        imagecopy($canvas, $resized, $destX, $destY, 0, 0, imagesx($resized), imagesy($resized));
        imagedestroy($resized);
    }

    private function loadPng($path) {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Fichier PNG introuvable.');
        }

        $image = imagecreatefrompng($path);

        if (!$image) {
            throw new InvalidArgumentException('Fichier PNG illisible.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function createTransparentCanvas($width, $height) {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);

        return $image;
    }

    private function resolvePublicPath($path) {
        $path = ltrim((string) $path, '/');
        $absolutePath = realpath($this->publicRoot . '/' . $path);

        if (!$absolutePath || strpos($absolutePath, $this->publicRoot . '/') !== 0) {
            throw new InvalidArgumentException('Chemin asset invalide.');
        }

        return $absolutePath;
    }

    private function resolveOutputPath($relativeOutputPath) {
        $relativeOutputPath = ltrim((string) $relativeOutputPath, '/');

        if (!preg_match('#^uploads/photos/[0-9]+/[0-9]+\.png$#', $relativeOutputPath)) {
            throw new InvalidArgumentException('Chemin de sortie invalide.');
        }

        return $this->publicRoot . '/' . $relativeOutputPath;
    }

    private function ensureOutputDirectory($directory) {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de creer le dossier photo.');
        }
    }

    private function clampFloat($value, $min, $max) {
        $value = (float) $value;

        return min($max, max($min, $value));
    }
}
