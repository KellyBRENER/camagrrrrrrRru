<?php
$cursorModel = new PhotoModel($pdo);
$cursorIds = [];
$cursorDateIds = [];
$sameDate = '2035-01-01 12:00:00';
for ($i = 0; $i < 12; $i++) {
    $photoId = $cursorModel->create($userId, ['cursor-test'])['photo_id'];
    $cursorIds[] = $photoId;
    $pdo->prepare('UPDATE photos SET created_at = ? WHERE photo_id = ?')->execute([$sameDate, $photoId]);
}
$cursorGuest = [];
$cursorFetch = function ($before = null, $hashtag = 'cursor-test') use (&$cursorGuest) {
    $query = '/?page=photo_public_list&cursor=1&limit=5&hashtag=' . urlencode($hashtag);
    if ($before !== null) {
        $query .= '&before_created_at=' . urlencode($before['created_at'])
            . '&before_id=' . $before['photo_id'];
    }
    [$code, $body] = request($query, $cursorGuest);
    check($code === 200, 'Lot par curseur accessible publiquement');
    return json_decode($body, true);
};
try {
    $first = $cursorFetch();
    check(array_column($first['photos'], 'photo_id') === array_reverse(array_slice($cursorIds, -5))
        && $first['pagination']['has_more'], 'Premier lot de cinq photos, dates identiques départagées par identifiant');

    $inserted = $cursorModel->create($userId, ['cursor-test'])['photo_id'];
    $cursorIds[] = $inserted;
    $pdo->prepare('UPDATE photos SET created_at = ? WHERE photo_id = ?')->execute(['2040-01-01 12:00:00', $inserted]);

    // La ligne du curseur peut disparaître : sa date et son identifiant voyagent dans la requête suivante.
    $cursorModel->deleteByOwner($first['photos'][0]['photo_id'], $userId);
    $cursorModel->deleteByOwner($first['pagination']['next_cursor']['photo_id'], $userId);
    $second = $cursorFetch($first['pagination']['next_cursor']);
    check(array_column($second['photos'], 'photo_id') === array_reverse(array_slice($cursorIds, 2, 5)),
        'Insertion et suppression du curseur ne décalent pas le lot suivant');
    $third = $cursorFetch($second['pagination']['next_cursor']);
    check(array_column($third['photos'], 'photo_id') === array_reverse(array_slice($cursorIds, 0, 2))
        && !$third['pagination']['has_more'] && $third['pagination']['next_cursor'] === null,
        'Dernier lot incomplet signale la fin');
    $empty = $cursorFetch(['created_at' => $sameDate, 'photo_id' => $cursorIds[0]]);
    check($empty['photos'] === [] && !$empty['pagination']['has_more'], 'Curseur épuisé retourne un lot vide');

    $dates = ['2031-01-03 12:00:00', '2031-01-01 12:00:00', '2031-01-04 12:00:00', '2031-01-02 12:00:00'];
    foreach ($dates as $date) {
        $photoId = $cursorModel->create($userId, ['cursor-date-order'])['photo_id'];
        $cursorDateIds[] = $photoId;
        $pdo->prepare('UPDATE photos SET created_at = ? WHERE photo_id = ?')->execute([$date, $photoId]);
    }
    $dateOrder = $cursorFetch(null, 'cursor-date-order');
    check(array_column($dateOrder['photos'], 'photo_id') === [
        $cursorDateIds[2], $cursorDateIds[0], $cursorDateIds[3], $cursorDateIds[1]
    ], 'Le flux suit la date même lorsque les identifiants suivent un autre ordre');

    $invalidCursors = [
        'before_id=1',
        'before_created_at=' . urlencode($sameDate),
        'before_created_at[]=x&before_id=1',
        'before_created_at=2035-02-30%2012%3A00%3A00&before_id=1',
        'before_created_at=' . urlencode($sameDate) . '&before_id=0',
        'before_created_at=' . urlencode($sameDate) . '&before_id=abc',
        'cursor=2'
    ];
    foreach ($invalidCursors as $invalid) {
        check(request('/?page=photo_public_list&cursor=1&' . $invalid, $cursorGuest)[0] === 400,
            'Curseur invalide refusé : ' . $invalid);
    }
} finally {
    foreach (array_merge($cursorIds, $cursorDateIds) as $id) $cursorModel->deleteByOwner($id, $userId);
}
