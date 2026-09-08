<?php
// Included by security.php, using its isolated database and authenticated test session.
$validationHeaders = ['X-CSRF-Token: ' . $loggedCsrf];
$expectInvalid = function ($route, $payload) use (&$visitor, $validationHeaders) {
    [$code, $body] = request('/?page=' . $route, $visitor, is_string($payload) ? $payload : json_encode($payload), $validationHeaders);
    $result = json_decode($body, true);
    check($code === 400 && is_array($result) && $result['success'] === false, $route . ' refuse une entrée invalide avec JSON 400');
};
foreach (['photo_create', 'photo_delete', 'photo_like_toggle', 'photo_comment_add'] as $route) {
    foreach (['{', 'null', 'true', '[]', '"text"'] as $raw) { $expectInvalid($route, $raw); }
}
foreach (['photo_delete', 'photo_like_toggle', 'photo_comment_add'] as $route) {
    foreach ([[], true, 1.5, '1abc', '-1', '0', '2147483648', str_repeat('9', 40)] as $id) {
        $expectInvalid($route, ['photo_id' => $id, 'comment' => 'test']);
    }
}
foreach ([[], true, 42, "nul\0byte", str_repeat('é', 501)] as $comment) {
    $expectInvalid('photo_comment_add', ['photo_id' => $otherPhoto, 'comment' => $comment]);
}
// UTF-8 malformed in a form body, rather than rejected earlier by the JSON decoder.
[$code, $body] = request('/?page=photo_comment_add', $visitor, ['photo_id' => $otherPhoto, 'comment' => "\xFF", 'csrf_token' => $loggedCsrf]);
check($code === 400 && json_decode($body, true)['success'] === false, 'Commentaire UTF-8 invalide refusé');
$boundaryComment = str_repeat('😀', 500);
[$code, $body] = request('/?page=photo_comment_add', $visitor, json_encode(['photo_id' => $otherPhoto, 'comment' => $boundaryComment]), $validationHeaders);
$result = json_decode($body, true);
check($code === 200 && in_array($boundaryComment, array_column($result['comments'], 'comment'), true), 'Commentaire de 500 caractères Unicode accepté sans troncature');

foreach (['photo_public_list&limit[]=1', 'photo_public_list&limit=51', 'photo_public_list&offset=-1',
    'photo_public_list&offset=1foo', 'photo_public_list&hashtag[]=chat', 'photo_public_list&hashtag=' . str_repeat('a', 26),
    'photo_hashtag_list&limit=true', 'photo_comments&photo_id[]=1', 'photo_comments&photo_id=1.5'] as $query) {
    [$code, $body] = request('/?page=' . $query, $visitor);
    check($code === 400 && json_decode($body, true)['success'] === false, 'Paramètre GET invalide refusé : ' . $query);
}
check(request('/?page=photo_public_list&limit=50&offset=0', $visitor)[0] === 200, 'Pagination aux bornes valides acceptée');

$validMontage = ['image' => 'data:image/png;base64,' . base64_encode($sourcePng), 'frame_id' => 'leopard-frame'];
$photosBefore = (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
foreach ([
    ['crop' => 'bad'], ['crop' => null], ['crop' => ['x_percent' => []]], ['crop' => ['width_percent' => 0]],
    ['crop' => ['height_percent' => 101]], ['crop' => ['y_percent' => '1e309']], ['crop' => ['x_percent' => null]],
    ['frame_id' => []], ['frame_id' => str_repeat('a', 51)], ['frame_id' => '../leopard-frame'],
    ['hashtags' => true], ['hashtags' => [['nested']]], ['hashtags' => [str_repeat('a', 26)]],
    ['hashtags' => ['un', 'deux', 'trois', 'quatre', 'cinq', 'six']],
    ['layers' => 'bad'], ['layers' => [false]], ['layers' => [['type' => 'frame', 'frame_id' => []]]],
    ['layers' => array_fill(0, 12, ['type' => 'frame', 'frame_id' => 'leopard-frame'])],
    ['stickers' => false], ['stickers' => [['sticker_id' => []]]],
    ['stickers' => [['sticker_id' => 'leopard-sticker', 'width_percent' => []]]],
    ['stickers' => [['sticker_id' => 'leopard-sticker', 'height_percent' => null]]],
    ['stickers' => [['sticker_id' => 'leopard-sticker', 'rotation_degrees' => 'NaN']]],
    ['stickers' => [['sticker_id' => 'leopard-sticker', 'center_x_percent' => 201]]],
    ['image' => []], ['image' => true], ['image' => base64_encode('not an image')],
] as $override) {
    $expectInvalid('photo_create', array_replace($validMontage, $override));
}
check((int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn() === $photosBefore, 'Montages invalides sans ligne photo résiduelle');

// Exercise both the current JSON layers format and the form/JSON-field fallback.
foreach ([
    json_encode(['image' => $validMontage['image'], 'layers' => [
        ['type' => 'frame', 'frame_id' => 'leopard-frame'],
        ['type' => 'sticker', 'sticker_id' => 'leopard-sticker', 'center_x_percent' => 50.5, 'width_percent' => 25, 'rotation_degrees' => -180],
    ], 'hashtags' => ['été']]),
    ['image' => $validMontage['image'], 'frame_id' => 'leopard-frame', 'crop' => '{"width_percent":100}',
        'stickers' => '[{"sticker_id":"leopard-sticker","width_percent":"25.5"}]', 'hashtags' => '["été"]', 'csrf_token' => $loggedCsrf],
] as $payload) {
    [$code, $body] = request('/?page=photo_create', $visitor, $payload, $validationHeaders);
    $createdPhoto = json_decode($body, true);
    check($code === 200 && $createdPhoto['success'], 'Montage cadre/sticker valide accepté en JSON et formulaire');
    request('/?page=photo_delete', $visitor, ['photo_id' => $createdPhoto['photo_id'], 'csrf_token' => $loggedCsrf]);
}

foreach ([['username' => ['bad']], ['username' => str_repeat('a', 51)], ['password' => ['bad']],
    ['password' => str_repeat('a', 73)], ['password' => "Original123!\0"], ['username' => "\xFF"]] as $override) {
    [$code, $body] = request('/?page=login', $visitor, array_replace(['username' => 'tester', 'password' => 'Original123!', 'csrf_token' => $loggedCsrf], $override));
    check($code === 200 && json_decode($body, true)['success'] === false, 'Connexion avec type ou longueur invalide refusée');
}
foreach ([['username' => ['bad']], ['email' => ['bad']], ['email' => str_repeat('a', 90) . '@example.com'],
    ['password' => ['bad']], ['password' => str_repeat('a', 72) . '1'], ['username' => "\xFF"]] as $override) {
    [$code, $body] = request('/?page=register', $visitor, array_replace(['username' => 'valid', 'email' => 'valid@example.invalid', 'password' => 'Original123!', 'csrf_token' => $loggedCsrf], $override), ['X-Requested-With: XMLHttpRequest']);
    check($code === 200 && json_decode($body, true)['success'] === false, 'Inscription avec type ou longueur invalide refusée');
}
foreach ([['bad'], str_repeat('a', 90) . '@example.com'] as $email) {
    [, , $headers] = request('/?page=resend_validation', $visitor, ['email' => $email, 'csrf_token' => $loggedCsrf]);
    check(str_contains($headers, 'Adresse+email+invalide'), 'Renvoi de validation : email invalide refusé');
}
check(!preg_match('/PHP (Warning|Fatal error|Notice)|Uncaught (TypeError|ValueError)/', file_get_contents($temp . '/server.log')), 'Aucune erreur PHP durant les cas de validation');
