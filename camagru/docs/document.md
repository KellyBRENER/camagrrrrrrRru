# Camagru — documentation et contrôle du sujet

Mise à jour : **9 septembre 2026**. Référence : [camagru.pdf](camagru.pdf), **version 4.1**. Les numéros de page ci-dessous sont ceux imprimés dans le sujet.

Ce document décrit l’état du répertoire de travail, y compris les modifications non encore commitées. Il remplace les anciens constats contradictoires : le profil, les notifications, la composition serveur, la sauvegarde, la remise à zéro du studio et le défilement infini sont maintenant implémentés.

**Verdict : les parcours fonctionnels principaux sont implémentés, mais la conformité intégrale n’est pas acquise.** Les traces de debug et le tri strict par date restent à traiter. Certains contrôles matériels, SMTP et de compatibilité restent à terminer. Les bonus ne sont évaluables que si le mandatory fonctionne intégralement, conformément au chapitre VI.

## 1. Carte rapide

Depuis la racine du dépôt :

| Emplacement | Rôle |
| --- | --- |
| `readme.md` | Présentation et commandes de démarrage ; certains liens de documentation y utilisent encore les anciens chemins. |
| `camagru/docs/camagru.pdf` | Sujet officiel 4.1. |
| `camagru/docs/document.md` | État actuel, architecture, commandes, conformité et travaux avant rendu. |
| `camagru/docs/audit-concurrence.md` | Garanties SQL/fichiers, concurrence, sessions et limites de reprise. |
| `camagru/docs/audit-xss.md`, `validation-serveur.md` | Analyses complémentaires ; le code et les tests actuels priment sur leurs anciens résultats chiffrés. |
| `camagru/docs/studio_reference.md`, `register.txt` | Notes détaillées historiques sur le studio et l’inscription. |
| `camagru/docker-compose.yml` | Services `app` et `db`, ports et volume MariaDB. |
| `camagru/docker/php/` | Image PHP/Apache, extensions GD/PDO/mbstring, msmtp et HTTPS local. |
| `camagru/Makefile` | Démarrage, initialisation, migrations, tests et arrêt. |
| `camagru/src/public/` | Seule racine publiée par Apache : entrée HTTP, JS, CSS, assets et montages. |
| `camagru/src/app/` | Contrôleurs, modèles, services, vues et classes communes. |
| `camagru/src/config/` | Routes, configuration environnement, connexion, setup et migrations. |
| `camagru/src/database/` | Schéma initial et migrations. |
| `camagru/src/tests/` | Tests PHP/HTTP/SQL, processus concurrents et tests DOM. |

Les chemins `src/...`, `docker/...` et `docs/...` utilisés ensuite sont relatifs à `camagru/`.

## 2. Architecture et règles de modification

Le serveur utilise PHP avec PDO MySQL, GD, mbstring, les sessions et `mail()` via msmtp. MariaDB stocke les données dans InnoDB. Le navigateur utilise HTML, CSS et JavaScript natif, sans framework JS. Bootstrap CSS et Bootstrap Icons sont chargés depuis un CDN ; aucun bundle JavaScript Bootstrap n’est inclus.

Le chemin habituel d’une requête est :

1. `public/index.php` démarre la session et configure les en-têtes de sécurité.
2. Tous les POST applicatifs passent par la vérification CSRF centralisée.
3. `config/database.php` ouvre PDO ; `Security::validateSession()` revérifie le compte et la version de son mot de passe.
4. `config/routes.php` et `app/Core/Router.php` choisissent le contrôleur et bloquent les routes privées sans connexion.
5. Un contrôleur renvoie du JSON ou une vue PHP. Une requête AJAX de navigation reçoit la vue seule ; une navigation complète reçoit `layout.php`, avec header, main et footer.
6. `public/js/router.js` charge les modules de page. Le routeur navigateur refuse d’insérer une réponse JSON comme du HTML.

`public/logout.php` est une entrée distincte, limitée au POST avec CSRF. Les scripts de setup, migration et suppression de base sont réservés à la CLI. Une route inconnue revient actuellement à l’accueil, sans page 404 dédiée.

| Modification | Fichiers principaux |
| --- | --- |
| Compte, profil, reset | `AuthController.php`, `UserModel.php`, `Core/Security.php`, vues associées et `js/pages/profil.js`. |
| Validation d’entrées | `Core/Validation.php`, `Core/Security.php` et contrôleur du parcours. |
| Studio et caméra | `Views/studio.php`, `js/pages/studio.js`, `js/router.js`, `css/style.css`. |
| Composition et stockage | `PhotoController.php`, `PhotoModel.php`, `Services/PhotoComposer.php`, `js/image-paths.js`. |
| Galerie et interactions | `Views/gallery.php`, `js/pages/gallery.js`, `PhotoController.php`, `PhotoModel.php`. |
| Emails de notification | `Services/PhotoNotifications.php`, préférence utilisateur et configuration msmtp. |
| Schéma | `database/init.sql`, migrations réexécutables dans `database/migrations/`. |
| Navigation et adaptation écran | `Views/layout.php`, `header.php`, `footer.php`, `js/app.js`, `css/style.css`. |

Règles à conserver : génération finale côté serveur ; pas de framework JS ; filtres PNG alpha ; identité et autorisations issues de la session ; validation côté serveur ; PDO préparé pour les données utilisateur ; encodage au moment de l’affichage ; aucune confiance dans un chemin fourni par le navigateur. Les pages privées doivent être déclarées `public => false`.

## 3. Comptes, sécurité et sessions

### Inscription et activation

- Pseudo de 1 à 50 caractères : lettres/chiffres Unicode, `_`, `.` et `-`.
- Email validé côté serveur, limité à 100 caractères ; pseudo et email sont uniques en base.
- Mot de passe : au moins 8 caractères, une lettre ASCII et un chiffre, au plus 72 octets. Stockage par `password_hash()`, vérification par `password_verify()`.
- Token d’activation généré avec `random_bytes(32)`. Le GET du lien email affiche une confirmation ; seul un POST avec CSRF consomme le token et active le compte par UPDATE atomique.
- Le renvoi d’un lien remplace le token précédent. La connexion exige un compte activé.

### Mot de passe oublié

- Demande par email, réponse neutre pour les comptes absents, non activés ou limités en fréquence.
- Token aléatoire de 32 octets, seul son SHA-256 est stocké dans `password_resets`.
- Expiration après 30 minutes, usage unique, au plus une nouvelle demande par minute et par compte activé tant que sa demande existe.
- Le secret du lien est retiré de l’URL avant l’affichage du formulaire. Nouveau mot de passe et confirmation sont contrôlés côté serveur.
- Demande, consommation du reset et changement sensible de compte utilisent le même ordre de verrouillage : utilisateur puis reset. Une adresse devenue obsolète ne permet pas de créer un nouveau token envoyé à cette ancienne adresse.

### Profil et révocation

Le profil privé permet de modifier pseudo/email avec le mot de passe actuel, de changer le mot de passe et d’activer/désactiver les emails. Chaque formulaire transporte son CSRF. Les formulaires du profil fonctionnent aussi en POST classique avec redirection.

Les valeurs originales du pseudo et de l’email accompagnent le formulaire. Sous verrou, le serveur applique uniquement les champs modifiés : deux changements indépendants ne s’écrasent pas. Deux modifications incompatibles du même champ produisent HTTP 409. Un doublon pseudo/email produit une erreur fonctionnelle 400.

Un changement de mot de passe renouvelle la session courante et invalide les autres sessions à leur prochaine requête grâce à `password_version`, dérivée du hash stocké. Un reset déconnecte également les anciennes sessions. Les changements d’email ou de mot de passe invalident les anciens liens de reset. Le changement d’email est immédiat : il n’existe pas de second parcours de validation de nouvelle adresse, que le sujet ne demande pas.

Les cookies sont HttpOnly et SameSite=Lax, et Secure sur HTTPS. La session est renouvelée à la connexion et lors d’une modification sensible. Les données personnelles ne doivent pas être stockées dans des logs de debug ou dans des dumps versionnés. Le dump local est désormais ignoré et absent des historiques local et distant réécrits.

## 4. Studio et montage

Le studio est privé. Il contient l’aperçu webcam, les cadres/stickers, les actions de capture/import/création et la bibliothèque personnelle avec miniatures, visionneuse et suppression.

- **Capture désactivée sans sticker ou cadre sélectionné**, même si la caméra fonctionne. La présence d’une source vidéo exploitable est aussi nécessaire.
- Caméra via `getUserMedia`, avec repli legacy ; un flux actif est réutilisé. La sortie du studio arrête les pistes. Un flux dont l’autorisation arrive après la sortie est immédiatement arrêté.
- Import PNG/JPEG, contrôles de type/taille côté client puis validation réelle du contenu côté serveur. Limites métier : 10 Mio et 20 millions de pixels. Les plafonds PHP de requête s’ajoutent à ces limites.
- Recadrage carré avec déplacement et zoom ; stickers déplaçables, redimensionnables et orientables ; un cadre maximum et dix stickers maximum.
- Aperçu immédiat dans le navigateur. Le navigateur transmet l’image source recadrée et les paramètres ; **GD superpose les calques côté serveur** et produit un PNG de 1024 × 1024 pixels.
- Après sauvegarde réussie : image source, cadre, stickers, mots-clés, upload et recadrage sont remis à zéro. Le flux vidéo reste visible s’il était actif.
- La bibliothèque est rechargée depuis `photo_mine` et reçoit immédiatement les nouvelles créations. Seul leur propriétaire peut les supprimer.

### Catalogue de filtres

`public/images/filters/filters.json` contient actuellement **2 cadres et 9 stickers**. Le contrôle des en-têtes des 11 fichiers PNG confirme un canal alpha pour chacun.

Pour ajouter un cadre : placer le PNG dans `public/images/filters/frames/` et l’inscrire au manifeste avec `type: "frame"`.

Pour ajouter un sticker : placer le PNG dans `public/images/filters/stickers/`, l’inscrire au manifeste avec `type: "sticker"`, puis ajouter la même référence à la table `stickers` : identifiant, chemin relatif, dimensions et hashtag. Mettre à jour `init.sql` pour les nouvelles installations et prévoir une migration/upsert pour une base existante. Modifier uniquement le JSON ne suffit pas à rendre un sticker acceptable par le serveur.

Les hashtags saisis sont limités à cinq, avec 25 caractères maximum par mot-clé, lettres Unicode et tirets internes uniquement. Les hashtags des stickers utilisés sont ajoutés automatiquement.

### Stockage et concurrence

Nouveaux chemins : `public/uploads/photos/{user_id}/{nom_aleatoire_32_hex}.png`, où le nom vient de `bin2hex(random_bytes(16))`. Les anciens chemins numériques restent acceptés.

Le fichier est créé exclusivement avec `fopen(..., 'xb')`, sans possibilité d’écraser un fichier existant. La composition se termine avant publication de la photo en BDD. Photo et hashtags sont ensuite enregistrés en transaction ; un échec SQL déclenche le nettoyage du fichier. Les hashtags partagés utilisent un upsert atomique.

La suppression exprime l’autorisation dans `DELETE ... WHERE photo_id = ? AND user_id = ? RETURNING path`. Une photo absente ou appartenant à un autre compte donne 404. Si le nettoyage disque échoue, les suppressions SQL et cascades sont annulées. Un fichier déjà absent n’est pas une erreur.

Cela ne constitue pas une transaction distribuée disque/SQL : arrêt brutal, perte de connexion pendant COMMIT ou nettoyage impossible peuvent demander une réconciliation. Les limites précises et tests sont décrits dans [audit-concurrence.md](audit-concurrence.md).

## 5. Galerie, interactions et notifications

La galerie est publique. Elle affiche les créations de tous les utilisateurs. Les likes et commentaires sont réservés aux comptes connectés, avec contrôle serveur et CSRF.

- Défilement infini : **lots de 5 photos**, ajoutés à la liste existante par IntersectionObserver. Un bouton de chargement manuel reste disponible.
- Requête : `GET /?page=photo_public_list&cursor=1&limit=5`, puis ajout de `before_created_at` et `before_id` au lot suivant.
- Réponse : `photos` et `pagination: {has_more, next_cursor: {created_at, photo_id}}`. Le filtre `hashtag` reste actif entre les lots.
- Une nouvelle recherche annule la requête précédente et réinitialise les résultats. Les réponses obsolètes sont ignorées. Une erreur conserve les cartes déjà affichées et permet de réessayer.
- La sortie de page annule le chargement et déconnecte l’observateur. La visionneuse parcourt toutes les photos déjà chargées.
- L’URL conserve le filtre ; les anciens paramètres `gallery_page` sont retirés au profit du flux depuis son début.
- L’API conserve également son ancien mode `limit/offset`, trié par `created_at DESC, photo_id DESC`, pour compatibilité.

Le mode infini utilise `ORDER BY created_at DESC, photo_id DESC`. Son curseur composé compare d’abord la date, puis l’identifiant lorsque les dates sont identiques. Il reste valable si la ligne ayant fourni le curseur est supprimée entre deux lots.

Les toggles de like sont sérialisés par verrou de la photo ; la PK `(photo_id, user_id)` empêche les doublons. Les commentaires restent protégés par une FK. Une photo supprimée pendant une interaction est traitée proprement sans créer de ligne orpheline.

`PhotoNotifications::send()` envoie les emails après enregistrement d’un commentaire ou ajout effectif d’un like. Aucun email lors du retrait d’un like. `users.email_notifications` est **vrai par défaut**, y compris pour les comptes existants après migration 002, et modifiable dans le profil. Une panne SMTP ne défait pas l’interaction enregistrée. L’acheminement réel est à confirmer avant rendu ; les tests capturent uniquement les messages localement.

## 6. Schéma et routes

| Table | Rôle et principales garanties |
| --- | --- |
| `users` | PK `id`, UNIQUE pseudo/email, hash de mot de passe, activation, token, préférence email. |
| `photos` | PK `photo_id`, propriétaire, chemin, date ; FK utilisateur avec cascade. |
| `stickers` | Catalogue des stickers serveur et hashtags par défaut. |
| `photo_likes` | PK `(photo_id, user_id)` ; FK photo et utilisateur avec cascade. |
| `comments` | Texte limité à 500 caractères ; FK photo et utilisateur avec cascade. |
| `hashtags` | Nom UNIQUE. |
| `photo_hashtags` | PK composée ; FK vers photo/hashtag avec cascade. |
| `password_resets` | PK utilisateur, token_hash UNIQUE, expiration et date de demande ; FK utilisateur avec cascade. |

Les positions des calques ne sont pas conservées après composition. Les contraintes ont été vérifiées dans le schéma et dans MariaDB installé ; l’audit de concurrence n’a pas nécessité de migration supplémentaire.

| Routes | Accès et usage |
| --- | --- |
| `home`, `gallery`, `registerinprogress` | Pages publiques. |
| `register`, `login`, `resend_validation`, `verify`, `forgot_password`, `reset_password` | Parcours publics de compte, avec mutations limitées au POST protégé. |
| `studio`, `profil` | Pages privées. |
| `profile_update`, `password_update`, `notifications_update` | POST privés. |
| `photo_create`, `photo_delete`, `photo_like_toggle`, `photo_comment_add` | POST privés. |
| `photo_mine` | GET privé. |
| `photo_public_list`, `photo_hashtag_list`, `photo_comments` | GET publics. |
| `/logout.php` | POST avec CSRF, bouton accessible dans le header. |

## 7. Installation et exploitation

Prérequis : Docker, Docker Compose, Make et un fichier local `camagru/.env`. Copier `.env.example` et remplacer ses exemples par les paramètres locaux DB/SMTP. Ne pas publier les valeurs réelles.

Depuis `camagru/`, la commande complète existante est :

```bash
make start
```

Elle lance Compose, attend dix secondes, prépare les permissions, initialise le schéma et les migrations, puis suit les logs. Le terminal reste donc occupé. Le lancement détaillé est `make up`, `make permissions`, `make setup`. Sur une installation existante, `make migrate` applique les migrations sans réinitialiser les données.

| Élément | Configuration actuelle |
| --- | --- |
| Application | Image construite sur `php:8.2-apache`, bind mount `./src:/var/www/html`. |
| Base | `mariadb:latest`, volume nommé Compose `db_data`, généralement `camagru_db_data`. |
| HTTP | `http://localhost:8081`. |
| HTTPS local | `https://localhost:8443`, certificat auto-signé généré à la construction. |
| Base exposée | Port hôte 3306. |
| Variables | `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `SMTP_USER`, `SMTP_PASS`, `APP_URL`. |
| Migrations | `001_password_resets.sql`, `002_email_notifications.sql`, appliquées par setup/migrate. |

`APP_URL` doit être l’adresse visible par le destinataire des emails ; les liens ne sont pas construits à partir du header client Host. La caméra fonctionne sur localhost ou une origine HTTPS acceptée. Une adresse LAN HTTP ne suffit généralement pas ; le certificat local n’est pas une configuration de production prête à publier.

Limites d’exploitation à connaître : `make start` utilise une attente fixe sans healthcheck DB ; le premier démarrage sur volume vierge n’a pas été rejoué durant cet audit. `make permissions` donne actuellement des permissions 777 à l’arborescence d’uploads. Les tags d’images ne sont pas figés par digest. Ces points méritent un durcissement mais n’impliquent pas l’absence de conteneurisation.

Commandes utiles :

```bash
make ps
make migrate
make test-security
make down
```

`make down` conserve le volume ; `make resetdb`, `make reset` et `make finaldown` sont destructifs. Les montages sur disque et la base doivent être sauvegardés/restaurés ensemble. Une cascade SQL sur un utilisateur ne supprime pas ses fichiers sur disque ; aucune route applicative de suppression de compte n’est actuellement proposée.

Pour consulter la base sans placer le secret dans la commande :

```bash
docker compose exec db mariadb -u root -p
```

Saisir le mot de passe local, puis sélectionner la base configurée et exécuter `SHOW TABLES;`. Conserver les sauvegardes hors Git. Le dump a été retiré de tous les commits locaux réécrits puis restauré comme copie privée ignorée, avec permissions 600. Les montages générés sont également ignorés ; seul `public/uploads/photos/.gitkeep` reste versionné.

## 8. Contrôle de chaque exigence du sujet 4.1

**Légende :** « Vérifié » indique une preuve par lecture/test pour ce périmètre ; « Partiel » indique des contrôles complémentaires nécessaires ; « Écart » indique un point qui empêche de déclarer le projet intégralement conforme. Les tests ne constituent pas une preuve d’absence absolue de vulnérabilité.

### Instructions générales — chapitre IV, page 5

| Exigence | État | Preuve ou réserve |
| --- | --- | --- |
| Organisation libre, cohérente | Vérifié | MVC maison, routeur central, services et documentation. |
| Aucune erreur, warning ou ligne de log dans les consoles client/serveur, hors exception HTTPS/getUserMedia | Vérifié | Traces retirées du routeur, de l’inscription, du studio, de la connexion et de la galerie ; `flowLog()` et les `error_log()` applicatifs ont été supprimés. PHP n’affiche ni ne journalise publiquement ses erreurs. Les 322 vérifications PHP et le scénario Chrome de galerie infinie passent sans warning, erreur fatale ou trace applicative. Une panne PDO simulée renvoie uniquement une réponse générique HTML/JSON. |
| Fonctions serveur avec équivalent PHP standard | Vérifié par inspection | PHP, PDO, GD, mbstring, sessions et mail ; pas de framework serveur tiers identifié. |
| Client HTML/CSS/JavaScript, APIs natives seulement | Vérifié par inspection | Modules du projet, fetch, canvas, caméra, IntersectionObserver ; aucun JS tiers importé. |
| Framework CSS autorisé sans JS interdit | Vérifié | Bootstrap CSS et icônes uniquement. Dépendance réseau CDN à garder en tête pour la soutenance. |
| Conteneurisation et déploiement en une commande | **Partiel** | Dockerfile, Compose, `make start` présents ; conteneurs opérationnels. Démarrage à froid sur une machine vierge à rejouer, attente DB fixe. |
| Absence de fuite de sécurité | **Garanties partielles** | Les scénarios applicatifs testés passent. Les historiques local et distant sont assainis, le secret DB a été renouvelé et les erreurs techniques ne sont plus exposées. Les autres contrôles finaux de cette liste restent à terminer. |
| Firefox ≥41 et Chrome ≥46 | **Partiel ; minima historiques non supportés par le code actuel** | Galerie rendue sur Firefox 154.0.1 et Chrome 152.0.7977.82. Modules ES, import dynamique, async/await, chaînage optionnel et autres fonctionnalités modernes empêchent de revendiquer Firefox 41/Chrome 46. Aucun test des versions minimales. |
| Secrets dans `.env` local ignoré, aucun identifiant publié | Vérifié après correction | `.env`, sauvegardes et montages sont ignorés ; l’ancien secret DB a été remplacé et retiré des 12 commits locaux, puis l’historique a été publié avec `--force-with-lease`. Aucun secret n’est reproduit dans ce rapport. |

### Fonctionnalités communes — V.1, page 6

| Exigence | État | Preuve ou réserve |
| --- | --- | --- |
| Header, main, footer | Vérifié | `layout.php`, header/footer communs. |
| Mobile et petites résolutions | Partiel | CSS responsive et adaptation du shell ; galerie inspectée à 390×844 sur Chrome et 1280×900 sur Firefox. Studio connecté, formulaires et rotations sur appareil tactile à compléter. |
| Validation correcte des formulaires | Vérifié sur les cas testés | `Validation`, `Security`, contrôleurs ; tests de types, formats, tailles, mots de passe et paramètres invalides. |
| Pas de mots de passe en clair en BDD | Vérifié pour les comptes applicatifs | Hash `password_hash`, vérification dédiée ; ne pas confondre avec les anciens secrets de connexion DB dans Git. |
| Protection contre HTML/JS injecté | Vérifié sur les cas testés | Encodage PHP, JSON sécurisé, textContent et contrôle des URLs ; suite DOM XSS. |
| Pas d’upload indésirable | Vérifié sur les cas testés | Contenu PNG/JPEG contrôlé, limites de taille/dimensions et réencodage GD vers un nom serveur. |
| Pas d’altération de requête SQL | Vérifié par inspection et cas invalides | Données liées par PDO, limites/cursors validés, fragments SQL construits depuis des choix serveur. |
| Pas de modification privée depuis un formulaire externe | Vérifié | Sessions, contrôle des routes, propriétaire côté SQL et CSRF de tous les POST. |

### Utilisateurs — V.2, page 7

| Exigence | État | Preuve ou réserve |
| --- | --- | --- |
| Inscription avec email valide, pseudo et mot de passe complexe | Vérifié | Contrôleur, contraintes UNIQUE et tests HTTP. |
| Confirmation par lien unique envoyé par email | Partiel | Création/envoi capturé, confirmation POST et usage unique testés ; réception SMTP réelle à vérifier. |
| Connexion pseudo/mot de passe | Vérifié | Compte activé requis, hash vérifié et session renouvelée. |
| Email de réinitialisation en cas d’oubli | Partiel | Parcours, expiration, remplacement et usage unique testés ; livraison SMTP réelle à vérifier. |
| Déconnexion en un clic depuis chaque page | Vérifié par code et test HTTP | Bouton POST permanent dans le header des pages connectées ; CSRF sans étape de confirmation. |
| Modifier pseudo/email/mot de passe | Vérifié | Profil privé, tests AJAX/POST classique, doublons et concurrence. |

### Galerie — V.3, page 7

| Exigence | État | Preuve ou réserve |
| --- | --- | --- |
| Galerie publique de toutes les créations | Vérifié | Route publique, pas de filtre propriétaire, lots parcourables jusqu’à la fin. |
| Classement par date de création | Vérifié | Modes OFFSET et infini triés par `created_at DESC, photo_id DESC`. Le curseur composé date/identifiant est couvert lorsque l’ordre des dates diffère de celui des IDs et lorsque plusieurs dates sont identiques. |
| Likes/commentaires réservés aux connectés | Vérifié | Vérification serveur, CSRF, contraintes FK/PK et tests. |
| Email à l’auteur lorsqu’un commentaire est ajouté | Partiel | Message capturé au bon destinataire et tests de panne SMTP ; réception réelle non testée. |
| Préférence email vraie par défaut et désactivable | Vérifié | Schéma, migration 002, profil et tests opt-out/opt-in. |
| Pagination avec au moins cinq éléments par page | Vérifié | Lots de cinq dans le flux infini ; dernier lot éventuellement incomplet. Le défilement infini est explicitement proposé comme bonus par le sujet. |

### Édition — V.4, page 8 et objectif page 4

| Exigence | État | Preuve ou réserve |
| --- | --- | --- |
| Studio privé, refus poli pour visiteur | Vérifié | Redirection login ou réponse AJAX 403, tests d’accès. |
| Section principale : aperçu webcam, filtres, capture | Vérifié par code | Vue studio et module de capture ; test caméra matérielle restant. |
| Section latérale de miniatures des créations précédentes | Vérifié par code | `photo_mine`, bibliothèque et visionneuse du studio. Présentation responsive à valider sur appareil réel. |
| Images superposables sélectionnables avec canal alpha | Vérifié | Manifeste, sélection des calques et en-têtes alpha des 11 PNG. |
| Capture non cliquable tant qu’aucun filtre n’est sélectionné | Vérifié par code | Bouton initialement disabled ; condition caméra + dimensions vidéo + filtre ; garde supplémentaire dans l’événement de capture. |
| Montage final effectué côté serveur | Vérifié | GD dans `PhotoComposer`, tests de création de PNG 1024×1024. |
| Upload possible sans webcam | Vérifié par code et traitement serveur | Sélecteur de fichier indépendant, recadrage et validation PNG/JPEG ; parcours tactile à compléter. |
| Supprimer uniquement ses propres créations | Vérifié | DELETE autorisé par propriétaire, tests autres comptes/double suppression/concurrence. |

### Contraintes, bonus et rendu — V.5, VI et VII, pages 9–12

| Exigence ou bonus | État |
| --- | --- |
| Langages/frameworks autorisés et conteneur de déploiement | Voir contrôles généraux ; pas de framework JS interdit trouvé. |
| Échanges AJAX | Implémentés et testés pour navigation, galerie, interactions, profil et studio. |
| Aperçu en direct du résultat sur la webcam | Implémenté côté navigateur ; validation matérielle finale restante. |
| Pagination infinie | Implémentée et testée ; corriger son tri pour satisfaire le mandatory. |
| Partage sur réseaux sociaux | Non implémenté ; optionnel. |
| GIF animé | Non implémenté ; optionnel. |
| Autres bonus cohérents | Recherche par hashtag, recadrage, transformation et empilement de stickers ; valeur à apprécier par l’évaluateur. |
| Bonus seulement si mandatory parfait | **Pas encore validable**, compte tenu des écarts ci-dessus. |
| Remise dans le dépôt Git | **À finaliser** : modifications non commitées et nouveaux fichiers non suivis, notamment les tests de concurrence/curseur et le rapport associé au moment du contrôle. Rien n’a été commité ni poussé par cette mise à jour documentaire. |

## 9. Vérifications exécutées et limites

- Lecture complète du sujet PDF 4.1, en particulier chapitres IV à VII, puis confrontation au code.
- Nouvelle exécution de `docker compose exec -T app php tests/security.php` : **322 vérifications réussies**, base temporaire distincte et aucun email externe envoyé.
- Suites DOM Chrome XSS et défilement infini validées lors de la mise en place du flux actuel. Elles couvrent affichage sécurisé, curseurs, ajout des lots, erreurs/reprise, filtres, visionneuse et sortie de page.
- Galerie réelle inspectée visuellement sur Chrome 152.0.7977.82 à 390×844 et Firefox 154.0.1 à 1280×900. Cela confirme ce rendu public, pas l’ensemble des parcours privés sur ces navigateurs.
- Contrôle alpha des 11 PNG du manifeste ; inspection des imports : Bootstrap CSS/icônes, modules locaux, pas de bibliothèque JS tierce.
- Réécriture des 12 commits locaux : dump, montages générés et anciens identifiants DB retirés. Références de sauvegarde/reflogs supprimés, objets élagués et ancien secret MariaDB renouvelé. Analyse des 203 blobs encore accessibles et recherche de motifs courants de clés privées/tokens sans résultat. Ce contrôle ne remplace pas un outil spécialisé ni la révocation d’un secret publié.
- `camagru/backups/camagru-before-db-volume-2026-09-07.sql` existe uniquement comme copie privée locale ignorée, avec permissions 600. `public/uploads/photos` est ignoré sauf `.gitkeep`. La branche GitHub distante a été remplacée par l’historique assaini avec `--force-with-lease`.
- Conteneurs `app`/`db` en fonctionnement ; pas de destruction/recréation du volume utilisateur pour simuler une première installation.

Les tests de concurrence démarrent plusieurs processus PHP/PDO indépendants. Les tests HTTP utilisent un serveur local avec capture des mails. Ils ne prouvent pas la réception dans une boîte externe, le fonctionnement d’une webcam réelle, la compatibilité des anciens navigateurs, ni une sécurité absolue.

## 10. Travaux avant rendu, par priorité

1. **Informer les éventuels collaborateurs de la réécriture.** Les historiques local et GitHub sont assainis et la rotation MariaDB est terminée. Les autres clones doivent repartir de cette nouvelle branche avant de contribuer. Le secret SMTP actuel n’a pas été retrouvé dans l’historique analysé et n’a pas été renouvelé.
2. **Fixer et vérifier la cible navigateur.** Les versions actuelles rendent la galerie ; les versions minimales citées ne peuvent pas être revendiquées avec la syntaxe actuelle. Le périmètre demandé doit être respecté lors du rendu, pas déduit des seuls tests Chrome récents.
3. **Effectuer les essais matériels et SMTP finaux** : inscription reçue, reset reçu, notification de commentaire avec préférence activée puis désactivée ; caméra/import, capture bloquée sans filtre, enregistrement puis studio vierge, arrêt caméra à la sortie ; navigation et formulaires sur téléphone portrait/paysage et Firefox/Chrome.
4. **Rejouer le démarrage sur environnement vierge isolé**, vérifier DB prête et uploads accessibles, puis vérifier les fichiers du commit remis. Ne pas utiliser `make reset` sur les données de travail pour cet essai.

Améliorations distinctes des écarts explicites au sujet : retirer le chmod 777, limiter l’exposition de MariaDB, figer les images Docker, maîtriser les dépendances CSS CDN et prévoir une reprise disque/BDD après incident. Elles ne remplacent pas les corrections mandatory ci-dessus.
