<?php
// Only invoked by security.php against its temporary database; never sends mail.
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../app/Models/PhotoModel.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/../app/Services/PhotoComposer.php';
[$host, $database, $user, $password] = databaseSettings();
if (!preg_match('/^camagru_test_[a-f0-9]{12}$/D', $database)) exit(2);
$pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$job = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 15;
while (!is_file($job['start'])) {
    if (microtime(true) > $deadline) exit(3);
    usleep(1000);
}
try {
    $photos = new PhotoModel($pdo);
    $users = new UserModel($pdo);
    switch ($job['operation']) {
        case 'create':
            $path = $photos->buildRelativePhotoPath($job['user_id']);
            (new PhotoComposer(__DIR__ . '/../public'))->compose($job['image'], [], 'leopard-frame', [], $path);
            try { $result = $photos->create($job['user_id'], $job['hashtags'], [], $path); }
            catch (Throwable $e) { unlink(__DIR__ . '/../public/' . $path); throw $e; }
            break;
        case 'like': $result = $photos->toggleLike($job['photo_id'], $job['user_id']); break;
        case 'comment': $result = $photos->addComment($job['photo_id'], $job['user_id'], 'Concurrent comment'); break;
        case 'delete':
            $result = $photos->deleteByOwner($job['photo_id'], $job['user_id'], function ($path) {
                $file = __DIR__ . '/../public/' . $path;
                if (is_file($file) && !unlink($file)) throw new RuntimeException('Test file deletion failed');
            });
            break;
        case 'reset': $result = $users->resetPassword($job['token'], $job['password']); break;
        case 'request_reset': $result = $users->createPasswordReset($job['user_id'], $job['token']); break;
        case 'profile':
            $result = $users->updateAccount($job['user_id'], $job['password'], $job['username'], $job['email'], null, $job['original']) !== false;
            break;
        case 'password':
            $result = $users->updateAccount($job['user_id'], $job['password'], null, null, $job['new_password']) !== false;
            break;
        case 'gallery': $result = $photos->getPublicPhotos(5); break;
        default: throw new RuntimeException('Unknown test operation');
    }
    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, $e->__toString());
    exit(1);
}
