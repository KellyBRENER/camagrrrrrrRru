<?php
// Exécuter avec : docker compose exec -T app php tests/security.php
// Base temporaire dédiée ; mails capturés localement, aucun envoi SMTP.
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/../app/Core/Security.php';
[$host, , $user, $password] = databaseSettings();
$dbName = 'camagru_test_' . bin2hex(random_bytes(6));
$pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$temp = sys_get_temp_dir() . '/' . $dbName;
mkdir($temp, 0700);
$server = null;
$photoDir = null;
$checks = 0;
function check($condition, $description) {
    global $checks;
    if (!$condition) { throw new RuntimeException($description); }
    $checks++;
    echo "OK $description\n";
}
function request($path, &$cookies, $data = null, $headers = []) {
    global $port;
    $headers[] = 'Cookie: ' . http_build_query($cookies, '', '; ');
    if ($data !== null) { $headers[] = is_string($data) ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded'; }
    $context = stream_context_create(['http' => [
        'method' => $data === null ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $data === null ? '' : (is_string($data) ? $data : http_build_query($data)),
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
    ]]);
    $body = file_get_contents("http://127.0.0.1:$port" . $path, false, $context);
    $responseHeaders = $http_response_header;
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/', $header, $cookie)) { $cookies[$cookie[1]] = $cookie[2]; }
    }
    return [(int) $match[1], $body, implode("\n", $responseHeaders)];
}
function csrf($html) {
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $match)) { throw new RuntimeException('Jeton CSRF absent'); }
    return $match[1];
}
try {
    $sql = file_get_contents(__DIR__ . '/../database/init.sql');
    $sql = str_replace(['CREATE DATABASE IF NOT EXISTS camagru', 'USE camagru;'], ["CREATE DATABASE `$dbName`", "USE `$dbName`;"], $sql);
    $sql = str_replace("    email_notifications BOOLEAN NOT NULL DEFAULT TRUE,\n", '', $sql);
    $pdo->exec($sql);
    $pdo->exec("INSERT INTO users (username, email, password) VALUES ('legacy-notifications', 'legacy@example.invalid', 'not-a-login-hash')");
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migration) { $pdo->exec(file_get_contents($migration)); }
    check((int) $pdo->query("SELECT email_notifications FROM users WHERE username = 'legacy-notifications'")->fetchColumn() === 1, 'Migration active les notifications des comptes existants');
    // Migration réexécutable, sans effacement des données.
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migration) { $pdo->exec(file_get_contents($migration)); }
    do {
        $firstUserId = random_int(100000000, 2000000000);
        $candidate = __DIR__ . '/../public/uploads/photos/' . $firstUserId;
    } while (file_exists($candidate));
    $photoDir = $candidate;
    $pdo->exec('ALTER TABLE users AUTO_INCREMENT = ' . $firstUserId);
    $model = new UserModel($pdo);
    $model->create('tester', 'tester@example.invalid', 'Original123!', 'verification');
    $model->confirmAccount('verification');
    $userId = $model->getByUsername('tester')['id'];
    $model->create('pending', 'pending@example.invalid', 'Original123!', 'pending');
    file_put_contents($temp . '/capture.php', '<?php if (is_file(getenv("TEST_MAIL_PATH") . ".fail")) exit(1); file_put_contents(getenv("TEST_MAIL_PATH"), stream_get_contents(STDIN));');
    putenv('MYSQL_DATABASE=' . $dbName);
    putenv('APP_URL=http://localhost:8081');
    putenv('TEST_MAIL_PATH=' . $temp . '/mail.txt');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . PHP_BINARY . ' ' . $temp . '/capture.php', '-S', "127.0.0.1:$port", '-t', realpath(__DIR__ . '/../public')],
        [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes);
    fclose($pipes[0]);
    for ($i = 0; $i < 100; $i++) {
        $connection = @fsockopen('127.0.0.1', $port);
        if ($connection) { fclose($connection); break; }
        usleep(50000);
    }
    $verificationToken = bin2hex(random_bytes(32));
    $model->create('activation', 'activation@example.invalid', 'Original123!', $verificationToken);
    $activationClient = [];
    [$code, $html] = request('/?page=verify&token=' . $verificationToken, $activationClient);
    check($code === 200 && str_contains($html, 'Activer mon compte'), 'Lien email GET affiche la confirmation');
    check(!$model->getByUsername('activation')['is_verified'] && $model->hasPendingVerification($verificationToken), 'GET ne valide pas le compte et ne consomme pas le token');
    $activationCsrf = csrf($html);
    request('/?page=verify&token=' . $verificationToken, $activationClient);
    check(!$model->getByUsername('activation')['is_verified'], 'Ouverture répétée du lien reste sans effet sur le compte');
    check(request('/?page=verify', $activationClient, ['token' => $verificationToken])[0] === 403, 'Activation POST sans CSRF refusée');
    check(request('/?page=verify', $activationClient, ['token' => $verificationToken, 'csrf_token' => 'invalid'])[0] === 403, 'Activation POST avec mauvais CSRF refusée');
    request('/?page=verify&token=' . $verificationToken, $activationClient, ['csrf_token' => $activationCsrf]);
    check(!$model->getByUsername('activation')['is_verified'], 'POST exige le token email dans le formulaire, sans repli sur GET');
    request('/?page=verify', $activationClient, ['token' => str_repeat('0', 64), 'csrf_token' => $activationCsrf]);
    check(!$model->getByUsername('activation')['is_verified'], 'CSRF valide ne remplace pas un token email valide');
    [$code, $html] = request('/?page=verify', $activationClient, ['token' => $verificationToken, 'csrf_token' => $activationCsrf]);
    check($code === 200 && str_contains($html, 'Inscription confirmée') && $model->getByUsername('activation')['is_verified'], 'Confirmation POST valide active le compte');
    check(!$model->hasPendingVerification($verificationToken) && !$model->confirmAccount($verificationToken), 'Activation atomique consomme le token une seule fois');
    [, $html] = request('/?page=verify', $activationClient, ['token' => $verificationToken, 'csrf_token' => $activationCsrf]);
    check(!str_contains($html, 'Inscription confirmée'), 'Réutilisation POST du lien refusée');
    [, $html] = request('/?page=verify&token=' . $verificationToken, $activationClient);
    check(!str_contains($html, 'Activer mon compte'), 'Lien déjà utilisé ne propose plus de confirmation');
    $visitor = [];
    [$status, $html, $headers] = request('/?page=login', $visitor);
    $csrf = csrf($html);
    check($status === 200 && stripos($headers, 'HttpOnly') !== false && stripos($headers, 'SameSite=Lax') !== false, 'Cookies de session protégés');
    check(request('/?page=login', $visitor, ['username' => 'tester', 'password' => 'Original123!'])[0] === 403, 'Connexion sans CSRF refusée');
    foreach (['photo_create', 'photo_delete', 'photo_like_toggle', 'photo_comment_add', 'register', 'forgot_password', 'reset_password', 'resend_validation'] as $route) {
        check(request('/?page=' . $route, $visitor, [])[0] === 403, "$route sans CSRF refusé");
    }
    $beforeLogin = $visitor['PHPSESSID'];
    [$status, $body] = request('/?page=login', $visitor, ['csrf_token' => $csrf, 'username' => 'tester', 'password' => 'Original123!']);
    check(json_decode($body, true)['success'] && $visitor['PHPSESSID'] !== $beforeLogin, 'Connexion valide et renouvellement de session');
    [, $html] = request('/?page=home', $visitor);
    $loggedCsrf = csrf($html);
    $image = imagecreatetruecolor(8, 8);
    ob_start(); imagepng($image); $sourcePng = ob_get_clean();
    imagedestroy($image);
    [$code, $body] = request('/?page=photo_create', $visitor, json_encode([
        'image' => 'data:image/png;base64,' . base64_encode($sourcePng),
        'frame_id' => 'leopard-frame', 'hashtags' => [], 'stickers' => [],
    ]), ['X-CSRF-Token: ' . $loggedCsrf]);
    $montage = json_decode($body, true);
    check($code === 200 && !empty($montage['success']), 'Création complète du montage avec image et CSRF valide');
    $savedImage = __DIR__ . '/../public/' . $montage['path'];
    $size = getimagesize($savedImage);
    check($size[0] === 1024 && $size[1] === 1024 && $size[2] === IMAGETYPE_PNG, 'Montage final enregistré en PNG 1024 × 1024');
    request('/?page=photo_delete', $visitor, ['photo_id' => $montage['photo_id'], 'csrf_token' => $loggedCsrf]);
    check(!file_exists($savedImage), 'Montage de test supprimé après vérification');

    $pdo->prepare('INSERT INTO photos (user_id, path) VALUES (?, ?)')->execute([$userId, 'test-only-no-file']);
    $photoId = (int) $pdo->lastInsertId();
    foreach ([['photo_like_toggle', ['photo_id' => $photoId]], ['photo_comment_add', ['photo_id' => $photoId, 'comment' => '<script>test</script>']]] as [$route, $payload]) {
        [$code, $body] = request('/?page=' . $route, $visitor, json_encode($payload), ['X-CSRF-Token: ' . $loggedCsrf]);
        check($code === 200 && json_decode($body, true)['success'], $route . ' fonctionne avec un jeton transmis en en-tête');
    }
    [$code, $body] = request('/?page=photo_delete', $visitor, json_encode(['photo_id' => $photoId]), ['X-CSRF-Token: ' . $loggedCsrf]);
    check($code === 200 && json_decode($body, true)['success'], 'Suppression du propriétaire fonctionne avec CSRF');
    $pendingId = $model->getByUsername('pending')['id'];
    $pdo->prepare('INSERT INTO photos (user_id, path) VALUES (?, ?)')->execute([$pendingId, 'test-only-no-file']);
    $otherPhoto = (int) $pdo->lastInsertId();
    check(request('/?page=photo_delete', $visitor, ['csrf_token' => $loggedCsrf, 'photo_id' => $otherPhoto])[0] === 404, 'Suppression de la photo d’un autre utilisateur refusée');
    check(request('/?page=photo_create', $visitor, json_encode([]), ['X-CSRF-Token: ' . $loggedCsrf])[0] === 400, 'Création sans superposition refusée après validation CSRF');
    $anonymous = [];
    [, $html] = request('/?page=login', $anonymous);
    $anonCsrf = csrf($html);
    check(request('/?page=photo_like_toggle', $anonymous, ['csrf_token' => $anonCsrf, 'photo_id' => $otherPhoto], ['X-Requested-With: XMLHttpRequest'])[0] === 403, 'CSRF valide ne donne pas accès aux actions privées');
    foreach (['<script>bad</script>', str_repeat('a', 51)] as $badName) {
        [, $body] = request('/?page=register', $anonymous, ['csrf_token' => $anonCsrf, 'username' => $badName, 'email' => 'bad@example.invalid', 'password' => 'ValidPassword123!'], ['X-Requested-With: XMLHttpRequest']);
        check(!json_decode($body, true)['success'], 'Pseudo invalide rejeté côté serveur');
    }
    foreach (['photo_create', 'photo_delete', 'photo_like_toggle', 'photo_comment_add'] as $route) {
        check(request('/?page=' . $route, $visitor)[0] === 405, $route . ' refuse les modifications par GET');
    }
    require __DIR__ . '/validation_cases.php';
    require __DIR__ . '/profile_cases.php';
    require __DIR__ . '/notification_cases.php';
    require __DIR__ . '/pagination_cases.php';
    $oldSession = $visitor;
    $resetClient = [];
    [, $html] = request('/?page=forgot_password', $resetClient);
    $resetCsrf = csrf($html);
    [$status, , $headers] = request('/?page=forgot_password', $resetClient, ['csrf_token' => $resetCsrf, 'email' => 'tester@example.invalid'], ['Host: attacker.invalid']);
    check($status === 303 && str_contains($headers, 'sent=1'), 'Demande de réinitialisation acceptée');
    $mail = file_get_contents($temp . '/mail.txt');
    check(str_contains($mail, 'http://localhost:8081/') && !str_contains($mail, 'attacker.invalid'), 'Lien email construit depuis APP_URL, pas depuis Host');
    preg_match('/token=([a-f0-9]{64})/', $mail, $match);
    $token = $match[1];
    $stored = $pdo->query('SELECT token_hash, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS lifetime FROM password_resets')->fetch();
    check($stored['token_hash'] === hash('sha256', $token) && $stored['lifetime'] > 1700 && $stored['lifetime'] <= 1800, 'Token haché en base et expiration à 30 minutes');
    request('/?page=forgot_password', $resetClient, ['csrf_token' => $resetCsrf, 'email' => 'tester@example.invalid']);
    check(file_get_contents($temp . '/mail.txt') === $mail, 'Demandes rapprochées limitées');
    foreach (['absent@example.invalid', 'pending@example.invalid'] as $email) {
        [$code, , $responseHeaders] = request('/?page=forgot_password', $resetClient, ['csrf_token' => $resetCsrf, 'email' => $email]);
        check($code === 303 && str_contains($responseHeaders, 'sent=1') && file_get_contents($temp . '/mail.txt') === $mail, 'Réponse neutre et aucun mail pour ' . $email);
    }
    [$status, , $headers] = request('/?page=reset_password&token=' . $token, $resetClient);
    check($status === 303 && !str_contains($headers, $token), 'Secret retiré de l’URL');
    [, $html] = request('/?page=reset_password', $resetClient);
    check(str_contains($html, 'name="password_confirmation"'), 'Formulaire affiché pour un lien valide');
    foreach ([['weak', 'weak'], ['NewPassword123!', 'Different123!'], [str_repeat('a', 72) . '1', str_repeat('a', 72) . '1']] as [$pass, $confirmation]) {
        request('/?page=reset_password', $resetClient, ['csrf_token' => $resetCsrf, 'password' => $pass, 'password_confirmation' => $confirmation]);
        check(password_verify('Original123!', $model->getByUsername('tester')['password']), 'Mot de passe invalide ou confirmation différente refusé');
    }
    [$status, , $headers] = request('/?page=reset_password', $resetClient, ['csrf_token' => $resetCsrf, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!']);
    check($status === 303 && str_contains($headers, 'password_reset=1'), 'Réinitialisation complète');
    check(password_verify('NewPassword123!', $model->getByUsername('tester')['password']), 'Nouveau mot de passe haché et enregistré');
    check(!$model->hasValidPasswordReset(hash('sha256', $token)), 'Lien consommé une seule fois');
    check(request('/?page=studio', $oldSession)[0] === 302, 'Anciennes sessions révoquées après changement');
    request('/?page=reset_password&token=' . $token, $resetClient);
    [, $html] = request('/?page=reset_password', $resetClient);
    check(!str_contains($html, 'name="password_confirmation"'), 'Lien déjà utilisé refusé dans l’interface');
    $model->createPasswordReset($userId, hash('sha256', 'expired'));
    $pdo->exec('UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND)');
    check(!$model->resetPassword(hash('sha256', 'expired'), 'ShouldNotWork123'), 'Lien expiré refusé');
    $pdo->exec('UPDATE password_resets SET requested_at = DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
    check($model->createPasswordReset($userId, hash('sha256', 'replacement')) && !$model->hasValidPasswordReset(hash('sha256', 'expired')), 'Nouveau lien remplace le précédent');
    $model->revokePasswordReset(hash('sha256', 'replacement'));
    [, $html] = request('/?page=login', $resetClient);
    $freshCsrf = csrf($html);
    [, $body] = request('/?page=login', $resetClient, ['csrf_token' => $freshCsrf, 'username' => 'tester', 'password' => 'Original123!']);
    check(!json_decode($body, true)['success'], 'Ancien mot de passe refusé');
    [, $body] = request('/?page=login', $resetClient, ['csrf_token' => $freshCsrf, 'username' => 'tester', 'password' => 'NewPassword123!']);
    check(json_decode($body, true)['success'], 'Connexion avec le nouveau mot de passe');
    [, $html] = request('/?page=home', $resetClient);
    $authCsrf = csrf($html);
    check(request('/logout.php', $resetClient)[0] === 405 && request('/logout.php', $resetClient, [])[0] === 403, 'Déconnexion GET et POST sans CSRF refusés');
    check(request('/logout.php', $resetClient, ['csrf_token' => $authCsrf])[0] === 303, 'Déconnexion POST protégée fonctionnelle');
    // Régression XSS pour les pseudos déjà présents avant la nouvelle validation.
    $attack = '</script><script>alert(1)</script>';
    $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$attack, $userId]);
    [, $html] = request('/?page=login', $resetClient);
    request('/?page=login', $resetClient, ['csrf_token' => csrf($html), 'username' => $attack, 'password' => 'NewPassword123!']);
    [, $html] = request('/?page=home', $resetClient);
    check(!str_contains($html, $attack) && str_contains($html, '\\u003C'), 'Pseudo historique malveillant encodé dans HTML et JavaScript');
    foreach (["<img src=x onerror=alert('XSS')>", "\">alert('XSS')"] as $payload) {
        foreach (['register' => ['error'], 'resend_validation' => ['error', 'success']] as $page => $fields) {
            foreach ($fields as $field) {
                foreach ([[], ['X-Requested-With: XMLHttpRequest']] as $requestHeaders) {
                    [$code, $html] = request('/?page=' . $page . '&' . http_build_query([$field => $payload]), $resetClient, null, $requestHeaders);
                    check($code === 200 && str_contains($html, htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) && !str_contains($html, $payload), 'Message GET échappé en page complète et AJAX');
                }
            }
        }
        $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$payload, $userId]);
        $xssClient = [];
        [, $html] = request('/?page=login', $xssClient);
        request('/?page=login', $xssClient, ['csrf_token' => csrf($html), 'username' => $payload, 'password' => 'NewPassword123!']);
        [, $html] = request('/?page=home', $xssClient);
        check(!str_contains($html, $payload) && str_contains($html, htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'Pseudo BDD historique échappé sans modifier son stockage');
        check($model->getByUsername($payload)['username'] === $payload, 'Pseudo original conservé en BDD');
        require_once __DIR__ . '/../app/Models/PhotoModel.php';
        $photoModel = new PhotoModel($pdo);
        $commentId = $photoModel->addComment($otherPhoto, $userId, $payload);
        [, $body, $headers] = request('/?page=photo_comments&photo_id=' . $otherPhoto, $xssClient);
        $comments = json_decode($body, true)['comments'];
        check(stripos($headers, 'Content-Type: application/json') !== false && end($comments)['comment'] === $payload, 'Commentaire original conservé et renvoyé uniquement comme JSON');
    }
    foreach (['register', 'resend_validation'] as $page) {
        check(request('/?page=' . $page . '&error[]=x&success[]=x', $resetClient)[0] === 200, 'Paramètres de message non textuels ignorés');
    }
    check(!Security::validPassword("abcdefgh1\0") && !Security::validPassword(['invalid']), 'Validation robuste des types et du caractère nul');
    check(!Security::validPassword('éééa1'), 'Longueur minimale calculée en caractères');
    require_once __DIR__ . '/../app/Services/PhotoComposer.php';
    $composer = new PhotoComposer(realpath(__DIR__ . '/../public'));
    $decode = new ReflectionMethod(PhotoComposer::class, 'createImageFromBytes');
    $source = imagecreatetruecolor(2, 2);
    ob_start(); imagepng($source); $png = ob_get_clean();
    $decoded = $decode->invoke($composer, $png);
    check(imagesx($decoded) === 2, 'Contenu PNG valide accepté');
    imagedestroy($decoded);
    ob_start(); imagegif($source); $gif = ob_get_clean();
    imagedestroy($source);
    $rejected = false;
    try { $decode->invoke($composer, $gif); } catch (InvalidArgumentException $e) { $rejected = true; }
    check($rejected, 'Image GIF refusée même avec une extension ou un MIME client PNG');
    echo "$checks vérifications réussies. Aucun mail externe envoyé.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if ($photoDir !== null && is_dir($photoDir)) {
        foreach (glob($photoDir . '/*.png') as $file) { unlink($file); }
        rmdir($photoDir);
    }
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    foreach (glob($temp . '/*') as $file) { unlink($file); }
    rmdir($temp);
}
