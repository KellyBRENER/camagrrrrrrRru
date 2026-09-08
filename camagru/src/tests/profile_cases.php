<?php
// Integration checks in the temporary database owned by security.php.
$model->create('profile-tester', 'profile@example.invalid', 'ProfileOld123!', 'profile-token');
$model->confirmAccount('profile-token');
$profileUserId = $model->getByUsername('profile-tester')['id'];
$profileClient = [];
$profileOtherSession = [];
foreach (['profileClient', 'profileOtherSession'] as $clientName) {
    [, $html] = request('/?page=login', $$clientName);
    request('/?page=login', $$clientName, ['csrf_token' => csrf($html), 'username' => 'profile-tester', 'password' => 'ProfileOld123!']);
}
[, $html] = request('/?page=profil', $profileClient);
$profileCsrf = csrf($html);
check(str_contains($html, 'value="profile-tester"') && str_contains($html, 'value="profile@example.invalid"'), 'Profil privé prérempli depuis la BDD');
check(!str_contains($html, 'ProfileOld123!') && str_contains($html, 'password_confirmation'), 'Profil sans secret affiché, confirmation du mot de passe présente');
[$code, $html] = request('/?page=profil', $profileClient, null, ['X-Requested-With: XMLHttpRequest']);
check($code === 200 && str_contains($html, 'updateProfileForm') && !str_contains($html, '<!doctype'), 'Profil accessible par navigation AJAX');
$profileAnonymous = [];
[, $html] = request('/?page=login', $profileAnonymous);
$profileAnonCsrf = csrf($html);
check(request('/?page=profil', $profileAnonymous)[0] === 302, 'Profil inaccessible sans connexion');
foreach (['profile_update', 'password_update'] as $route) {
    check(request('/?page=' . $route, $profileClient)[0] === 405, $route . ' refuse GET');
    check(request('/?page=' . $route, $profileClient, [])[0] === 403, $route . ' exige CSRF');
    check(request('/?page=' . $route, $profileAnonymous, ['csrf_token' => $profileAnonCsrf], ['X-Requested-With: XMLHttpRequest'])[0] === 403, $route . ' exige une session connectée');
}
$profileValid = ['username' => 'profile-updated', 'email' => 'updated@example.invalid', 'current_password' => 'ProfileOld123!', 'csrf_token' => $profileCsrf];
$profileOriginal = $model->getById($profileUserId);
foreach ([['username' => []], ['username' => str_repeat('a', 51)], ['username' => '<img src=x onerror=alert(1)>'],
    ['email' => []], ['email' => 'invalid'], ['email' => str_repeat('a', 95) . '@example.invalid'],
    ['current_password' => []], ['current_password' => 'wrong'], ['username' => 'tester'], ['email' => 'tester@example.invalid']] as $override) {
    [$code, $body] = request('/?page=profile_update', $profileClient, array_replace($profileValid, $override), ['X-Requested-With: XMLHttpRequest']);
    check($code === 400 && json_decode($body, true)['success'] === false && $model->getById($profileUserId) === $profileOriginal, 'Profil invalide ou doublon refusé sans modification partielle');
}
$resetBeforeEmail = hash('sha256', 'profile-email-link');
$model->createPasswordReset($profileUserId, $resetBeforeEmail);
// Client-supplied user ID must never select the account to update.
$profileValid['user_id'] = $userId;
[$code, $body] = request('/?page=profile_update', $profileClient, $profileValid, ['X-Requested-With: XMLHttpRequest']);
check($code === 200 && json_decode($body, true)['success'], 'Pseudo et email mis à jour en AJAX');
check($model->getById($profileUserId)['username'] === 'profile-updated' && $model->getById($userId)['username'] === 'tester', 'Modification limitée au propriétaire de la session');
check(!$model->hasValidPasswordReset($resetBeforeEmail), 'Changement email annule les anciens liens de récupération');
[, $html] = request('/?page=profil', $profileClient);
$profileCsrf = csrf($html);
check(str_contains($html, 'Vos informations ont été mises à jour.') && str_contains($html, 'value="updated@example.invalid"'), 'Profil et confirmation rechargés après succès');
[, $html] = request('/?page=home', $profileOtherSession);
check(str_contains($html, 'profile-updated'), 'Pseudo actualisé dans les autres sessions');
$profileValid = ['username' => 'profile-updated', 'email' => 'updated@example.invalid', 'current_password' => 'ProfileOld123!', 'csrf_token' => $profileCsrf];
[$code, , $headers] = request('/?page=profile_update', $profileClient, $profileValid);
check($code === 303 && str_contains($headers, 'Location: /?page=profil'), 'Formulaire sans AJAX et mise à jour inchangée fonctionnels');
[, $html] = request('/?page=profil', $profileClient);
$profileCsrf = csrf($html);
$passwordValid = ['old_password' => 'ProfileOld123!', 'new_password' => 'ProfileNew456!', 'password_confirmation' => 'ProfileNew456!', 'csrf_token' => $profileCsrf];
foreach ([['old_password' => []], ['old_password' => 'wrong'], ['new_password' => []], ['new_password' => 'short1'],
    ['new_password' => str_repeat('a', 72) . '1'], ['password_confirmation' => []], ['password_confirmation' => 'different']] as $override) {
    [$code, $body] = request('/?page=password_update', $profileClient, array_replace($passwordValid, $override), ['X-Requested-With: XMLHttpRequest']);
    check($code === 400 && json_decode($body, true)['success'] === false && password_verify('ProfileOld123!', $model->getByUsername('profile-updated')['password']), 'Changement de mot de passe invalide refusé');
}
$resetBeforePassword = hash('sha256', 'profile-password-link');
$model->createPasswordReset($profileUserId, $resetBeforePassword);
$oldProfileSessionId = $profileClient['PHPSESSID'];
[$code, , $headers] = request('/?page=password_update', $profileClient, $passwordValid);
check($code === 303 && str_contains($headers, 'Location: /?page=profil'), 'Changement de mot de passe par formulaire réussi');
check($profileClient['PHPSESSID'] !== $oldProfileSessionId, 'Session courante renouvelée');
$profileHash = $model->getByUsername('profile-updated')['password'];
check(password_verify('ProfileNew456!', $profileHash) && !password_verify('ProfileOld123!', $profileHash), 'Nouveau mot de passe haché, ancien refusé');
check(!$model->hasValidPasswordReset($resetBeforePassword), 'Changement de mot de passe annule les liens de récupération');
check(request('/?page=profil', $profileOtherSession)[0] === 302, 'Ancienne session révoquée après changement de mot de passe');
[$code, $html] = request('/?page=profil', $profileClient);
check($code === 200 && str_contains($html, 'Mot de passe modifié.'), 'Session courante reste connectée et affiche le succès');
$profileCsrf = csrf($html);
check($profileCsrf !== $passwordValid['csrf_token'], 'Jeton CSRF renouvelé et formulaire synchronisé');
[$code, $body] = request('/?page=password_update', $profileClient,
    ['old_password' => 'ProfileNew456!', 'new_password' => 'ProfileFinal789!', 'password_confirmation' => 'ProfileFinal789!', 'csrf_token' => $profileCsrf], ['X-Requested-With: XMLHttpRequest']);
check($code === 200 && json_decode($body, true)['success'], 'Changement de mot de passe AJAX fonctionnel');
// Escaping applies even to historical usernames that predate current validation.
$profileAttack = '"><img src=x onerror=alert(1)>';
$pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$profileAttack, $profileUserId]);
[, $html] = request('/?page=profil', $profileClient);
check(!str_contains($html, $profileAttack) && str_contains($html, htmlspecialchars($profileAttack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'Pseudo historique échappé dans les attributs du profil');
