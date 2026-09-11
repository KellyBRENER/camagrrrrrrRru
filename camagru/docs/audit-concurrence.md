# Audit de concurrence Camagru — 9 septembre 2026

Audit effectué avant modification des modèles, contrôleurs, composition des images, schéma et migrations, sessions, formulaires et pagination. Les contraintes ont également été lues dans la base installée (MariaDB 12.3.2, InnoDB). Les tests utilisent une base temporaire distincte et capturent les emails localement.

## Risques trouvés et corrections

| Zone | État initial | Correction et fichiers concernés |
| --- | --- | --- |
| Création de photo | Le commit rendait la photo visible avant sa composition. Une autre requête pouvait la lire ou la supprimer pendant son écriture. | `src/app/Controllers/PhotoController.php` compose le fichier avant de publier sa ligne. `src/app/Models/PhotoModel.php` conserve une transaction courte pour photo et hashtags. Une erreur SQL annule les écritures et déclenche la suppression du fichier créé. |
| Nommage et écriture | Le nom utilisait l'identifiant SQL auto-incrémenté, **pas un timestamp** : les identifiants concurrents étaient déjà distincts. En revanche, `imagepng` pouvait écraser un fichier existant et imposait de créer la ligne avant le fichier. | `PhotoModel.php` génère 16 octets aléatoires côté serveur. `src/app/Services/PhotoComposer.php` utilise `fopen(..., 'xb')`, vérifie l'encodage, le flush et la fermeture. Un fichier préexistant est refusé sans modification. |
| Dossier utilisateur | Le code revérifiait déjà le dossier après un échec de `mkdir`, mais pouvait émettre un avertissement lorsque deux processus le créaient ensemble. | L'avertissement est supprimé localement, puis le résultat est explicitement contrôlé. Un véritable échec reste une erreur. |
| Hashtags | Deux `SELECT` pouvaient constater la même absence puis tenter deux `INSERT` identiques. | Upsert atomique avec récupération de l'identifiant via `LAST_INSERT_ID`, ordre des hashtags stabilisé. Jusqu'à trois tentatives de la transaction SQL en cas d'interblocage InnoDB 1213, notamment pour les équivalences Unicode de la collation. Le fichier n'est pas recomposé. |
| Suppression | L'autorisation figurait déjà dans le `DELETE`, mais le contrôleur faisait un `SELECT` préalable et renvoyait 500 si une suppression concurrente gagnait. Les échecs de suppression disque n'étaient pas vérifiés. | `DELETE ... WHERE photo_id = ? AND user_id = ? RETURNING path` récupère seulement le chemin effectivement supprimé. Aucun résultat donne 404. Transaction avec annulation des suppressions SQL/cascades si le nettoyage disque échoue. Fichier déjà absent accepté. |
| Likes | La clé primaire empêchait les doublons, mais la décision du toggle reposait sur un `SELECT` non protégé. | `PhotoModel::toggleLike` verrouille la ligne de cette photo avec `FOR UPDATE` pendant la décision, l'écriture et le comptage. Deux toggles concurrents deviennent un ajout puis un retrait. Une photo supprimée donne 404. L'email part après le commit. |
| Commentaires | La FK empêchait les orphelins, mais une suppression entre le contrôle du contrôleur et l'insertion pouvait produire une exception SQL. | La violation FK 1452 est convertie en échec fonctionnel et HTTP 404. Pas de transaction supplémentaire autour de l'INSERT atomique. |
| Profil | Chaque formulaire renvoyait pseudo et email, écrasant potentiellement une modification indépendante faite ailleurs. | `src/app/Views/profil.php` transmet les valeurs originales de cet onglet. `UserModel::updateAccount` compare sous le verrou existant et ne met à jour que les champs modifiés. Une modification concurrente incompatible du même champ produit 409 via `src/app/Controllers/AuthController.php`. |
| Reset et compte | La consommation unique du token était déjà transactionnelle, mais verrouillait reset puis utilisateur, contrairement aux autres changements de compte. | Ordre commun utilisateur puis reset. La première lecture du propriétaire ne sert pas à autoriser le reset : le token et son expiration sont revérifiés sous verrou avant écriture. |
| Demande de reset et email | Une demande pouvait lire l'ancienne adresse, attendre un changement d'email, puis créer un token envoyé à l'ancienne adresse. | `createPasswordReset` reçoit l'adresse attendue du contrôleur et la revérifie sous verrou utilisateur. Un changement ultérieur d'adresse continue d'invalider le token. |

## Stratégie de fichiers

Les nouveaux montages suivent `public/uploads/photos/{user_id}/{bin2hex(random_bytes(16))}.png`, soit 32 caractères hexadécimaux pour le nom. La réservation exclusive du fichier protège aussi contre une collision hypothétique de la valeur aléatoire.

Les anciens fichiers numériques restent lisibles et supprimables. Les listes de chemins autorisés ont été adaptées dans `PhotoComposer.php`, `PhotoController.php` et `src/public/js/image-paths.js`. Aucun renommage des photos existantes n'est nécessaire. La recherche des usages de `time()` dans le code applicatif ne trouve que l'expiration du cookie de déconnexion, aucun nom de photo.

## Contraintes SQL et transactions

**Aucune contrainte ni migration ajoutée ou modifiée** : le schéma et la base installée garantissent déjà les invariants nécessaires.

- `users` : PK `id`, UNIQUE `username`, UNIQUE `email`. Les doublons email sont traduits en réponse fonctionnelle 400 par le code existant, conservé et retesté.
- `photos` : PK auto-incrémentée, FK vers le propriétaire et index utilisateur/date.
- `photo_likes` : PK composée `(photo_id, user_id)`, équivalente à l'unicité demandée dans l'ordre inverse.
- `comments`, `photo_likes`, `photo_hashtags` : FK vers la photo avec `ON DELETE CASCADE` et index associés.
- `hashtags` : UNIQUE sur le nom ; `photo_hashtags` : PK composée empêchant les associations doubles.
- `password_resets` : PK `user_id` pour un seul reset actif par compte, UNIQUE `token_hash`, FK utilisateur avec cascade.

Transactions ajoutées : toggle de like, pour sérialiser lecture et écriture ; suppression de photo, pour pouvoir annuler les suppressions SQL si le fichier ne peut pas être retiré. Transactions conservées et adaptées : création photo/hashtags, demande de reset, consommation de reset, modification sensible du compte. Aucun verrou applicatif ou transaction de lecture n'a été ajouté à la galerie. La composition GD et les emails restent hors des transactions SQL.

## Comptes, tokens et sessions : garanties conservées

Le changement d'email est immédiat après vérification du mot de passe actuel ; il n'existe pas de confirmation différée de nouvelle adresse. Il ne faut donc pas inventer un flux de token de changement d'email. Les tokens d'inscription existants sont remplacés par UPDATE conditionnel et consommés par un UPDATE atomique mettant `token` à NULL ; ils ne peuvent pas modifier l'adresse d'un compte actif.

Les tokens de reset sont aléatoires, stockés sous SHA-256, expirent après 30 minutes et sont consommés une seule fois. La limite existante de 60 secondes est vérifiée sous verrou utilisateur. Un nouveau lien autorisé remplace l'ancien ; un changement d'email ou de mot de passe invalide les liens existants.

`Security::validateSession` compare à chaque requête la version issue du hash du mot de passe avec celle de la session. Après changement de mot de passe ou reset, les autres appareils sont refusés à leur prochaine requête. Le changement authentifié renouvelle la session courante et son CSRF. La politique actuelle ne déconnecte pas les autres appareils lors d'un simple changement d'email.

## Limites laissées en place

- **Mise à jour après l’audit : galerie à défilement infini.** L’interface charge désormais des lots de 5 photos par curseur (`photo_id < before_id`, tri par identifiant décroissant), sans décalage des lots lors des ajouts ou suppressions. Les nouveaux montages apparaissent au prochain rechargement ou à une nouvelle recherche. L’ancien mode OFFSET reste disponible dans l’API pour compatibilité ; ses limites de décalage et de total non figé concernent uniquement les clients qui l’utilisent encore. Les anciens liens `gallery_page` ouvrent désormais le début du flux, en conservant leur filtre.
- **Le disque et SQL ne constituent pas une transaction distribuée.** Les erreurs ordinaires de création déclenchent un nettoyage explicite ; une erreur disque de suppression annule les suppressions SQL. Mais un arrêt brutal après écriture et avant commit peut laisser un fichier sans ligne. Un arrêt ou une perte de connexion pendant le commit de suppression, après `unlink`, peut laisser une ligne sans fichier. Une perte de connexion rendant le résultat d'un commit inconnu, ou l'échec du nettoyage lui-même, nécessite une réconciliation disque/BDD. Aucun journal durable ou service de reprise n'a été ajouté à cette architecture. Le rollback SQL ne prétend jamais restaurer un fichier supprimé.
- Une session déjà validée au début d'une requête en cours n'est pas interrompue rétroactivement par un changement de mot de passe. La révocation s'applique aux prochaines requêtes ; les changements sensibles revérifient eux-mêmes le mot de passe sous verrou.
- Les emails sont envoyés après les transactions. Leur ordre d'arrivée n'est pas garanti : un lien remplacé peut arriver tard, mais il ne redevient pas valide. Il n'y a pas de file SMTP transactionnelle.
- Les méthodes de modèle utilisées directement par les fixtures peuvent créer des lignes sans fichier ; le flux applicatif de création passe toujours par le contrôleur et le composeur. Aucun autre appel applicatif de création n'a été trouvé.

## Tests et résultats

**305 vérifications PHP réussies**, puis tests navigateur XSS et pagination réussis.

Commande depuis `camagru` : `docker compose exec -T app php tests/security.php`.

Fichiers ajoutés : `src/tests/concurrency_cases.php` et `src/tests/concurrency-worker.php`. Le lanceur démarre plusieurs processus PHP indépendants, chacun avec sa connexion PDO, puis libère une barrière de départ. Il teste réellement en parallèle :

- création de deux montages et de leur dossier, avec hashtags communs ;
- lecture de la galerie ;
- deux toggles du même utilisateur ;
- deux suppressions de la même photo ;
- suppression face à un like et à un commentaire ;
- deux demandes de reset et deux consommations du même token ;
- modifications indépendantes du profil et modifications concurrentes du même email ;
- deux changements de mot de passe et reset face au changement authentifié.

Les autres vérifications couvrent refus d'écrasement avec empreinte du fichier inchangée, unicité SQL email/like, autorisation propriétaire, cascades, fichier déjà absent, réponse HTTP 404/409, token obsolète, rollback sur erreur disque injectée, rollback photo/hashtags et suppression du fichier après erreur SQL injectée, et reprise sur erreur 1213 injectée. Les tests existants d'expiration, remplacement des tokens, révocation des sessions, doublons email en HTTP et notifications restent actifs.

`src/tests/security.php` intègre ces cas ; `src/tests/profile_cases.php` transmet les instantanés du formulaire ; `src/tests/xss-dom.html` valide aussi les nouveaux chemins aléatoires. Les migrations existantes sont exécutées deux fois dans la base temporaire. Les suites navigateur confirment l'affichage des photos, les protections contre les URL malveillantes et la pagination.

Les processus parallèles ne garantissent pas tous les entrelacements possibles de l'ordonnanceur. Les tests de panne sont des injections contrôlées, pas des coupures électriques, pertes réseau pendant COMMIT ou tests de charge prolongés. Les workers ne passent pas par HTTP ; les comportements HTTP sont vérifiés séparément pour éviter que le serveur PHP de test mono-processus ne masque la concurrence. Aucun email externe n'est envoyé.

## Validation du défilement infini

La suite PHP compte désormais **317 vérifications réussies**. `src/tests/cursor_cases.php` couvre les lots de cinq, l’insertion entre deux lectures, la suppression de la photo utilisée comme curseur, la fin du flux et les curseurs invalides. `src/tests/pagination-dom.html` couvre le déclenchement par intersection, l’ajout sans remplacement des cartes, la prévention des requêtes doubles, les recherches, les erreurs et reprises, la visionneuse et l’annulation des requêtes à la sortie. Un bouton « Charger plus de photos » reste disponible pour le clavier et les navigateurs sans IntersectionObserver. Les tests navigateur de défilement infini et XSS passent.
