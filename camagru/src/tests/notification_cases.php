<?php
// Uses the isolated database and local mail capture from security.php.
$model->create('notification-owner', 'notifications@example.invalid', 'NotifyOwner123!', 'notify-token');
$model->confirmAccount('notify-token');
$notificationOwner = $model->getByUsername('notification-owner')['id'];
$notificationClient = [];
[, $html] = request('/?page=login', $notificationClient);
request('/?page=login', $notificationClient, ['username' => 'notification-owner', 'password' => 'NotifyOwner123!', 'csrf_token' => csrf($html)]);
[, $html] = request('/?page=profil', $notificationClient);
$notificationCsrf = csrf($html);
check((int) $model->getById($notificationOwner)['email_notifications'] === 1 && str_contains($html, 'Désactiver les notifications'), 'Notifications activées par défaut et bouton profil affiché');
$pdo->prepare('INSERT INTO photos (user_id, path) VALUES (?, ?)')->execute([$notificationOwner, 'test-notification-no-file']);
$notificationPhoto = (int) $pdo->lastInsertId();
$notificationHeaders = ['X-CSRF-Token: ' . $loggedCsrf];
$mailPath = $temp . '/mail.txt';
$sendActivity = function ($kind, $comment = null) use (&$visitor, $notificationPhoto, $notificationHeaders) {
    $payload = ['photo_id' => $notificationPhoto];
    if ($comment !== null) $payload['comment'] = $comment;
    return request('/?page=' . ($kind === 'like' ? 'photo_like_toggle' : 'photo_comment_add'), $visitor, json_encode($payload), $notificationHeaders);
};
file_put_contents($mailPath, 'no-mail');
$notificationComment = '<img src=x onerror=alert(1)> "Bonjour"';
[$code, $body] = $sendActivity('comment', $notificationComment);
$mail = file_get_contents($mailPath);
check($code === 200 && json_decode($body, true)['success'] && str_contains($mail, 'To: notifications@example.invalid') && str_contains($mail, 'nouveau commentaire'), 'Commentaire enregistré et email adressé au propriétaire');
check(str_contains($mail, htmlspecialchars($notificationComment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) && !str_contains($mail, $notificationComment), 'Commentaire échappé dans l’email HTML');
check(str_contains($mail, 'http://localhost:8081/?page=gallery') && str_contains($mail, 'http://localhost:8081/?page=profil'), 'Liens de galerie et préférences présents dans l’email');
file_put_contents($mailPath, 'no-mail');
[$code, $body] = $sendActivity('like');
check($code === 200 && json_decode($body, true)['liked_by_user'] && str_contains(file_get_contents($mailPath), 'nouveau like'), 'Nouveau like déclenche un email');
file_put_contents($mailPath, 'no-mail');
[$code, $body] = $sendActivity('like');
check($code === 200 && !json_decode($body, true)['liked_by_user'] && file_get_contents($mailPath) === 'no-mail', 'Retrait du like sans email');
check(request('/?page=notifications_update', $notificationClient)[0] === 405, 'Préférence refuse GET');
check(request('/?page=notifications_update', $notificationClient, ['email_notifications' => '0'])[0] === 403, 'Préférence exige CSRF');
check(request('/?page=notifications_update', $profileAnonymous, ['email_notifications' => '0', 'csrf_token' => $profileAnonCsrf], ['X-Requested-With: XMLHttpRequest'])[0] === 403, 'Préférence exige une connexion');
foreach ([[], 'true', '2', ''] as $invalid) {
    [$code, $body] = request('/?page=notifications_update', $notificationClient, ['email_notifications' => $invalid, 'csrf_token' => $notificationCsrf], ['X-Requested-With: XMLHttpRequest']);
    check($code === 400 && !json_decode($body, true)['success'], 'Préférence invalide refusée');
}
[$code, $body] = request('/?page=notifications_update', $notificationClient, ['email_notifications' => '0', 'user_id' => $userId, 'csrf_token' => $notificationCsrf], ['X-Requested-With: XMLHttpRequest']);
check($code === 200 && json_decode($body, true)['success'] && (int) $model->getById($notificationOwner)['email_notifications'] === 0 && (int) $model->getById($userId)['email_notifications'] === 1, 'Désactivation AJAX limitée au propriétaire de la session');
$pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/002_email_notifications.sql'));
check((int) $model->getById($notificationOwner)['email_notifications'] === 0, 'Migration réexécutée conserve une préférence désactivée');
[, $html] = request('/?page=profil', $notificationClient);
check(str_contains($html, 'Activer les notifications') && str_contains($html, 'Notifications par email désactivées.'), 'Profil reflète la préférence enregistrée');
file_put_contents($mailPath, 'no-mail');
$sendActivity('comment', 'Notifications désactivées');
$sendActivity('like');
check(file_get_contents($mailPath) === 'no-mail', 'Commentaires et likes sans email après désactivation');
[$code, , $headers] = request('/?page=notifications_update', $notificationClient, ['email_notifications' => '1', 'csrf_token' => $notificationCsrf]);
check($code === 303 && str_contains($headers, 'Location: /?page=profil') && (int) $model->getById($notificationOwner)['email_notifications'] === 1, 'Réactivation via formulaire sans AJAX');
$sendActivity('comment', 'Notifications réactivées');
check(str_contains(file_get_contents($mailPath), 'Notifications réactivées'), 'Emails repris après réactivation');
// Force sendmail to fail: the action must still succeed and remain stored exactly once.
file_put_contents($mailPath . '.fail', '1');
$commentsBeforeFailure = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
[$code, $body] = $sendActivity('comment', 'Envoi indisponible');
unlink($mailPath . '.fail');
check($code === 200 && json_decode($body, true)['success'] && (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn() === $commentsBeforeFailure + 1, 'Échec SMTP ne fait pas échouer ni répéter le commentaire');
