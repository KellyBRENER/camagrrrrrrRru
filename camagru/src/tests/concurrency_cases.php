<?php
// Real concurrent processes with separate PDO connections and a start barrier.
require_once __DIR__ . '/../app/Models/PhotoModel.php';
require_once __DIR__ . '/../app/Services/PhotoComposer.php';
function concurrentJobs($jobs) {
    global $temp;
    $start = $temp . '/start-' . bin2hex(random_bytes(4));
    $processes = [];
    try {
        foreach ($jobs as $i => $job) {
            $job += ['start' => $start, 'ready' => $start . '-' . $i];
            $process = proc_open([PHP_BINARY, __DIR__ . '/concurrency-worker.php', json_encode($job)],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $temp . '/concurrency.log', 'a']], $pipes);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes[1], $job['ready']];
        }
        $deadline = microtime(true) + 15;
        foreach ($processes as [, , $ready]) {
            while (!is_file($ready)) {
                if (microtime(true) > $deadline) throw new RuntimeException('Worker startup timed out');
                usleep(1000);
            }
        }
        file_put_contents($start, 'start');
        $results = [];
        foreach ($processes as [$process, $output]) {
            stream_set_timeout($output, 20);
            $json = stream_get_contents($output);
            if (stream_get_meta_data($output)['timed_out']) throw new RuntimeException('Worker timed out');
            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !array_key_exists('result', $decoded)) {
                throw new RuntimeException('Concurrent worker failed: ' . file_get_contents($temp . '/concurrency.log'));
            }
            $results[] = $decoded['result'];
        }
        return $results;
    } finally {
        foreach ($processes as [$process, $output, $ready]) {
            fclose($output);
            proc_terminate($process);
            proc_close($process);
            if (is_file($ready)) unlink($ready);
        }
        if (is_file($start)) unlink($start);
    }
}
$racePhotos = new PhotoModel($pdo);
if (is_dir($photoDir) && glob($photoDir . '/*') === []) rmdir($photoDir);
$createJob = ['operation' => 'create', 'user_id' => $userId,
    'image' => 'data:image/png;base64,' . base64_encode($sourcePng), 'hashtags' => ['concurrence', 'simultane']];
$created = concurrentJobs([$createJob, array_replace($createJob, ['hashtags' => ['simultane', 'concurrence']])]);
check($created[0]['path'] !== $created[1]['path'], 'Créations PHP parallèles : chemins différents');
foreach ($created as $photo) {
    check(preg_match('#^uploads/photos/[0-9]+/[a-f0-9]{32}\.png$#D', $photo['path']) &&
        is_file(__DIR__ . '/../public/' . $photo['path']), 'Nom aléatoire serveur et fichier présent après commit');
    check(count($racePhotos->getHashtags($photo['photo_id'])) === 2, 'Hashtags communs créés sans collision en parallèle');
}
$first = $created[0];
$file = __DIR__ . '/../public/' . $first['path'];
$before = hash_file('sha256', $file);
$collisionRejected = false;
try { (new PhotoComposer(__DIR__ . '/../public'))->compose($createJob['image'], [], 'leopard-frame', [], $first['path']); }
catch (RuntimeException $e) { $collisionRejected = true; }
check($collisionRejected && hash_file('sha256', $file) === $before, 'Écriture exclusive refuse tout écrasement du fichier existant');
$galleries = concurrentJobs([['operation' => 'gallery'], ['operation' => 'gallery']]);
check($galleries[0] === $galleries[1], 'Deux lectures de galerie concurrentes sans verrou applicatif');
$likeJob = ['operation' => 'like', 'user_id' => $userId, 'photo_id' => $first['photo_id']];
$likes = concurrentJobs([$likeJob, $likeJob]);
check(count(array_filter(array_column($likes, 'liked_by_user'))) === 1 && $racePhotos->countLikes($first['photo_id']) === 0,
    'Deux toggles parallèles se succèdent : ajout puis retrait');
$racePhotos->like($first['photo_id'], $userId);
$duplicateRejected = false;
try { $pdo->prepare('INSERT INTO photo_likes (photo_id, user_id) VALUES (?, ?)')->execute([$first['photo_id'], $userId]); }
catch (PDOException $e) { $duplicateRejected = ($e->errorInfo[1] ?? null) === 1062; }
check($duplicateRejected && $racePhotos->countLikes($first['photo_id']) === 1, 'Clé primaire interdit deux likes identiques en SQL');
$racePhotos->addComment($first['photo_id'], $userId, 'Before delete');
$deleteJob = ['operation' => 'delete', 'user_id' => $userId, 'photo_id' => $first['photo_id']];
$deleted = concurrentJobs([$deleteJob, $deleteJob]);
check(count(array_filter($deleted)) === 1 && !is_file($file), 'Deux suppressions concurrentes : un seul succès, fichier retiré');
foreach (['photo_likes', 'comments', 'photo_hashtags'] as $table) {
    check((int) $pdo->query("SELECT COUNT(*) FROM $table WHERE photo_id = " . (int) $first['photo_id'])->fetchColumn() === 0,
        "Cascade supprime les lignes liées dans $table");
}
check(!$racePhotos->deleteByOwner($first['photo_id'], $userId), 'Suppression répétée renvoie false sans exception');
check($racePhotos->toggleLike($first['photo_id'], $userId) === null && !$racePhotos->addComment($first['photo_id'], $userId, 'Too late'),
    'Like et commentaire sur photo disparue refusés proprement');
foreach (['photo_delete', 'photo_like_toggle', 'photo_comment_add'] as $route) {
    check(request('/?page=' . $route, $visitor, ['photo_id' => $first['photo_id'], 'comment' => 'Too late', 'csrf_token' => $loggedCsrf])[0] === 404,
        "$route sur photo supprimée retourne HTTP 404");
}
$second = $created[1];
$secondFile = __DIR__ . '/../public/' . $second['path'];
$deniedCallback = false;
check(!$racePhotos->deleteByOwner($second['photo_id'], $pendingId, function () use (&$deniedCallback) { $deniedCallback = true; }) &&
    !$deniedCallback && is_file($secondFile), 'DELETE atomique refuse un autre propriétaire sans toucher au fichier');
$racePhotos->addComment($second['photo_id'], $userId, 'Rollback comment');
$deleteFailed = false;
try { $racePhotos->deleteByOwner($second['photo_id'], $userId, function () { throw new RuntimeException('Injected filesystem failure'); }); }
catch (RuntimeException $e) { $deleteFailed = true; }
check($deleteFailed && $racePhotos->getById($second['photo_id']) && is_file($secondFile) && $racePhotos->countComments($second['photo_id']) === 1,
    'Erreur filesystem de suppression : rollback photo et cascades, fichier conservé');
$racingDelete = ['operation' => 'delete', 'user_id' => $userId, 'photo_id' => $second['photo_id']];
concurrentJobs([$racingDelete, array_replace($racingDelete, ['operation' => 'like']), array_replace($racingDelete, ['operation' => 'comment'])]);
check(!$racePhotos->getById($second['photo_id']) && $racePhotos->countLikes($second['photo_id']) === 0 &&
    $racePhotos->countComments($second['photo_id']) === 0 && !is_file($secondFile), 'Suppression face à like/commentaire : aucune ligne ni fichier orphelin');
// Inject SQL failure after INSERT photos, inside its hashtag transaction.
$filesBefore = glob($photoDir . '/*.png');
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
$pdo->exec("CREATE TRIGGER fail_photo_hashtag BEFORE INSERT ON photo_hashtags FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected insert failure'");
try {
    [$code] = request('/?page=photo_create', $visitor, json_encode([
        'image' => $createJob['image'], 'frame_id' => 'leopard-frame', 'hashtags' => ['rollback-test']]), ['X-CSRF-Token: ' . $loggedCsrf]);
    check($code === 500 && (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn() === $countBefore && glob($photoDir . '/*.png') === $filesBefore,
        'Échec SQL de création : transaction annulée et fichier composé supprimé');
} finally { $pdo->exec('DROP TRIGGER fail_photo_hashtag'); }
// A transient deadlock rolls back the whole SQL unit and reuses the completed file.
$pdo->exec("CREATE TRIGGER deadlock_once BEFORE INSERT ON photo_hashtags FOR EACH ROW BEGIN
    IF @injected_deadlock IS NULL THEN
        SET @injected_deadlock = 1;
        SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'Injected deadlock';
    END IF;
END");
try {
    [$code, $body] = request('/?page=photo_create', $visitor, json_encode([
        'image' => $createJob['image'], 'frame_id' => 'leopard-frame', 'hashtags' => ['retry-test']]), ['X-CSRF-Token: ' . $loggedCsrf]);
    $retried = json_decode($body, true);
    check($code === 200 && $retried['success'] && (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn() === $countBefore + 1,
        'Interblocage SQL transitoire : nouvelle tentative sans double création');
    $retriedFile = __DIR__ . '/../public/' . $retried['path'];
    check(is_file($retriedFile), 'Nouvelle tentative SQL conserve le fichier composé');
    unlink($retriedFile);
    check(request('/?page=photo_delete', $visitor, ['photo_id' => $retried['photo_id'], 'csrf_token' => $loggedCsrf])[0] === 200,
        'Suppression avec fichier déjà absent réussit proprement');
} finally { $pdo->exec('DROP TRIGGER deadlock_once'); }
$model->create('race-account', 'race@example.invalid', 'RacePassword123!', 'race-verify');
$model->confirmAccount('race-verify');
$raceUser = $model->getByUsername('race-account')['id'];
$tokenA = hash('sha256', 'race-token-a');
$tokenB = hash('sha256', 'race-token-b');
$requestJob = ['operation' => 'request_reset', 'user_id' => $raceUser, 'token' => $tokenA];
$requests = concurrentJobs([$requestJob, array_replace($requestJob, ['token' => $tokenB])]);
check(count(array_filter($requests)) === 1 && (int) $pdo->query('SELECT COUNT(*) FROM password_resets WHERE user_id = ' . (int) $raceUser)->fetchColumn() === 1,
    'Demandes reset parallèles : un seul token actif et rate limiting respecté');
$activeToken = $requests[0] ? $tokenA : $tokenB;
$resetJob = ['operation' => 'reset', 'token' => $activeToken, 'password' => 'RaceReset123!'];
$resets = concurrentJobs([$resetJob, $resetJob]);
check(count(array_filter($resets)) === 1 && !$model->hasValidPasswordReset($activeToken), 'Même token consommé en parallèle : exactement un succès');
check(!$model->resetPassword($activeToken, 'Replay123!'), 'Token consommé immédiatement inutilisable');
$original = ['username' => 'race-account', 'email' => 'race@example.invalid'];
$profileJob = ['operation' => 'profile', 'user_id' => $raceUser, 'password' => 'RaceReset123!', 'original' => $original] + $original;
$profiles = concurrentJobs([array_replace($profileJob, ['username' => 'race-renamed']), array_replace($profileJob, ['email' => 'race-new@example.invalid'])]);
$raceProfile = $model->getById($raceUser);
check($profiles === [true, true] && $raceProfile['username'] === 'race-renamed' && $raceProfile['email'] === 'race-new@example.invalid',
    'Deux profils parallèles fusionnent les changements indépendants pseudo/email');
check(!$model->updateAccount($raceUser, 'RaceReset123!', 'race-account', 'race-stale@example.invalid', null, $original) && $model->getLastError() === 'profile_conflict',
    'Ancien formulaire modifiant le même email détecté comme conflit');
check(!$model->createPasswordReset($raceUser, hash('sha256', 'old-email'), 'race@example.invalid'), 'Demande reset utilisant une adresse devenue obsolète refusée sous verrou');
$raceClient = [];
[, $html] = request('/?page=login', $raceClient);
request('/?page=login', $raceClient, ['csrf_token' => csrf($html), 'username' => 'race-renamed', 'password' => 'RaceReset123!']);
[, $html] = request('/?page=profil', $raceClient);
$raceCsrf = csrf($html);
check(str_contains($html, 'name="original_username" value="race-renamed"') && str_contains($html, 'name="original_email" value="race-new@example.invalid"'),
    'Formulaire profil transporte les valeurs originales pour chaque onglet');
[$code, $body] = request('/?page=profile_update', $raceClient, ['csrf_token' => $raceCsrf,
    'username' => 'race-account', 'email' => 'race-stale@example.invalid', 'current_password' => 'RaceReset123!',
    'original_username' => $original['username'], 'original_email' => $original['email']], ['X-Requested-With: XMLHttpRequest']);
check($code === 409 && json_decode($body, true)['success'] === false, 'Formulaire profil obsolète renvoie un conflit HTTP 409');
$currentOriginal = ['username' => $raceProfile['username'], 'email' => $raceProfile['email']];
$sameFieldJob = array_replace($profileJob, $currentOriginal, ['original' => $currentOriginal]);
$sameField = concurrentJobs([array_replace($sameFieldJob, ['email' => 'race-first@example.invalid']),
    array_replace($sameFieldJob, ['email' => 'race-second@example.invalid'])]);
check(count(array_filter($sameField)) === 1 && $model->getById($raceUser)['email'] === ($sameField[0] ? 'race-first@example.invalid' : 'race-second@example.invalid'),
    'Deux emails concurrents depuis le même formulaire : première modification conservée, autre refusée');
$uniqueEmail = false;
try { $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute(['tester@example.invalid', $raceUser]); }
catch (PDOException $e) { $uniqueEmail = ($e->errorInfo[1] ?? null) === 1062; }
check($uniqueEmail, 'Unicité email imposée par MariaDB');
$passwordJob = ['operation' => 'password', 'user_id' => $raceUser, 'password' => 'RaceReset123!', 'new_password' => 'RaceFinal123!'];
$passwords = concurrentJobs([$passwordJob, array_replace($passwordJob, ['new_password' => 'RaceOther123!'])]);
check(count(array_filter($passwords)) === 1, 'Deux changements mot de passe concurrents : ancien secret accepté une seule fois');
// A reset and an authenticated password update used to lock their rows in reverse order.
$currentPassword = $passwords[0] ? 'RaceFinal123!' : 'RaceOther123!';
$mixToken = hash('sha256', 'mixed-reset');
$model->createPasswordReset($raceUser, $mixToken);
$mixed = concurrentJobs([['operation' => 'reset', 'token' => $mixToken, 'password' => 'MixedReset123!'],
    array_replace($passwordJob, ['password' => $currentPassword, 'new_password' => 'MixedProfile123!'])]);
check(count(array_filter($mixed)) === 1 && !$model->hasValidPasswordReset($mixToken), 'Reset face au changement authentifié : ordre de verrouillage commun, un seul succès');
