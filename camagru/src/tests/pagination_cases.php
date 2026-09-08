<?php
require_once __DIR__ . "/../app/Models/PhotoModel.php";
$paginationModel = new PhotoModel($pdo);
$paginationIds = [];
for ($i = 0; $i < 31; $i++) {
    $photo = $paginationModel->create($userId, ['pagination-test', 'pagination-testing']);
    $paginationIds[] = $photo['photo_id'];
    $pdo->prepare('UPDATE photos SET created_at = ? WHERE photo_id = ?')->execute(['2030-01-01 12:00:00', $photo['photo_id']]);
}
$paginationGuest = [];
$paginationFetch = function ($offset, $hashtag = '') use (&$paginationGuest) {
    [$code, $body] = request('/?page=photo_public_list&limit=12&offset=' . $offset . '&hashtag=' . urlencode($hashtag), $paginationGuest);
    check($code === 200, 'Page de galerie accessible sans connexion');
    return json_decode($body, true);
};
$paginationAll = $paginationFetch(0);
check(count($paginationAll['photos']) === 12 && $paginationAll['pagination']['total'] === (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn(), 'Galerie limitée à 12 avec total complet');
$paginationSeen = [];
foreach ([0 => 12, 12 => 12, 24 => 7] as $offset => $expectedCount) {
    $result = $paginationFetch($offset, 'pagination-test');
    check(count($result['photos']) === $expectedCount && $result['pagination']['offset'] === $offset && $result['pagination']['total'] === 31, 'Pagination filtrée compte chaque photo une seule fois');
    $paginationSeen = array_merge($paginationSeen, array_column($result['photos'], 'photo_id'));
}
check($paginationSeen === array_reverse($paginationIds), '31 photos accessibles sans doublon, dates égales triées par identifiant décroissant');
$result = $paginationFetch(1200, 'pagination-test');
check($result['pagination']['offset'] === 24 && count($result['photos']) === 7, 'Page hors limites ramenée à la dernière page');
$result = $paginationFetch(12, 'pagination-absent');
check($result['pagination']['offset'] === 0 && $result['pagination']['total'] === 0 && $result['photos'] === [], 'Recherche vide sans page fantôme');
// Simulate removing all photos on the last page, then following an old bookmark.
foreach (array_slice($paginationSeen, 24) as $id) $paginationModel->deleteByOwner($id, $userId);
$result = $paginationFetch(24, 'pagination-test');
check($result['pagination']['offset'] === 12 && count($result['photos']) === 12, 'Après suppression de la dernière page, retour à la dernière page existante');
foreach ($paginationIds as $id) $paginationModel->deleteByOwner($id, $userId);
