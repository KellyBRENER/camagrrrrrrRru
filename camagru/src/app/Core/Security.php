<?php

final class Security {
    public static function startSession() {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public static function requireCsrf() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Session du formulaire expirée. Rechargez la page.']);
            exit;
        }
    }

    public static function validText($value, $maxLength) {
        return is_string($value) && preg_match('//u', $value) === 1
            && strpos($value, "\0") === false && mb_strlen($value, 'UTF-8') <= $maxLength;
    }

    public static function validEmail($value) {
        return self::validText($value, 100) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validPassword($password) {
        return is_string($password) && strlen($password) <= 72 && preg_match('//u', $password) === 1
            && mb_strlen($password, 'UTF-8') >= 8
            && strpos($password, "\0") === false
            && preg_match('/[a-zA-Z]/', $password) && preg_match('/[0-9]/', $password);
    }

    public static function validateSession($pdo) {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        $stmt = $pdo->prepare('SELECT username, password, is_verified FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || !$user['is_verified'] || !hash_equals(hash('sha256', $user['password']), $_SESSION['password_version'] ?? '')) {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['password_version']);
            session_regenerate_id(true);
        } else {
            $_SESSION['username'] = $user['username'];
        }
    }

    public static function appUrl() {
        $url = rtrim(getenv('APP_URL') ?: 'http://localhost:8081', '/');
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('APP_URL invalide.');
        }
        return $url;
    }
}
