# Checklist de validation Camagru

Cette liste prépare une soutenance. Chaque case est **à exécuter** sur l’installation évaluée ; aucune case manuelle n’est présumée réussie. Les essais qui créent des comptes, photos ou commentaires modifient les données : utilisez des comptes et images de démonstration. Les vérifications automatisées décrites à la fin emploient une base temporaire distincte et capturent leurs emails localement.

## Installation et exploitation

- [ ] **Clone neuf et démarrage.** Prérequis : Docker, Compose avec `up --wait`, Make, ports `8081` et `8443` libres, paramètres MariaDB et Gmail SMTP de démonstration. Actions : cloner dans un emplacement isolé, `cd camagru`, copier `.env.example` vers `.env`, remplir les valeurs locales, lancer `make start`, puis `make ps`. Attendu : `app` et `db` démarrent, le schéma et les migrations s’appliquent, `http://localhost:8081` sert l’accueil et `https://localhost:8443` répond après acceptation éventuelle du certificat autosigné.
- [ ] **Persistance après arrêt.** Prérequis : une photo de démonstration publiée. Actions : `make down`, puis `make start`. Attendu : compte, photo et fichier PNG sont toujours accessibles. Ne lancez pas `make resetdb`, `make reset` ou `make finaldown` sur une base à conserver : ces commandes détruisent la base ou son volume et laissent les PNG du montage hôte.

## Comptes et profil

- [ ] **Inscription et activation.** Prérequis : boîte email de test et SMTP fonctionnel. Actions : créer un compte avec pseudo, email et mot de passe valides ; essayer de se connecter avant activation ; ouvrir le lien reçu, puis confirmer avec le bouton de la page ; réutiliser le lien. Attendu : connexion refusée avant confirmation, compte activé uniquement après le POST de confirmation, ancien lien inutilisable.
- [ ] **Renvoi du lien.** Prérequis : compte non activé. Actions : demander un nouvel email depuis « Renvoyer le lien », puis essayer l’ancien et le nouveau. Attendu : seul le nouveau lien permet la confirmation.
- [ ] **Connexion et déconnexion.** Prérequis : compte activé. Actions : se connecter, ouvrir Studio et Profil, se déconnecter depuis l’en-tête, puis tenter à nouveau l’accès privé. Attendu : pages privées disponibles avant la déconnexion ; retour à la connexion ensuite.
- [ ] **Mot de passe oublié.** Prérequis : compte activé et réception SMTP de test. Actions : demander une récupération, ouvrir le lien, choisir un mot de passe valide, essayer l’ancien puis le nouveau, et réutiliser le lien. Attendu : nouveau mot de passe seul accepté, lien utilisable une fois et périmé après 30 minutes. Vérifier aussi qu’une demande répétée immédiatement n’émet pas de nouveau lien pour le même compte.
- [ ] **Profil.** Prérequis : session connectée. Actions : changer le pseudo et l’email avec le mot de passe actuel, puis changer le mot de passe avec confirmation ; recharger le profil. Attendu : valeurs mises à jour, doublons refusés, ancien mot de passe refusé et anciennes sessions invalidées à leur prochaine requête. Le nouvel email est appliqué immédiatement, sans second lien de validation.
- [ ] **Préférence email.** Prérequis : compte possédant une photo. Actions : relever l’état par défaut, désactiver puis réactiver les notifications dans Profil et recharger la page. Attendu : préférence initiale activée et chaque changement conservé.

## Galerie, studio et interactions

- [ ] **Galerie publique et chargement.** Prérequis : au moins six photos de démonstration, dont plusieurs avec un même hashtag. Actions : ouvrir Galerie déconnecté ; faire défiler ou cliquer « Charger plus de photos » ; rechercher un hashtag puis effacer le filtre. Attendu : photos visibles par lots de cinq, ordre décroissant de date puis d’identifiant, absence de doublons, filtre conservé entre les lots et retour à la liste complète après effacement.
- [ ] **Studio avec webcam.** Prérequis : compte connecté, navigateur autorisant la caméra sur `localhost` ou une origine HTTPS acceptée, webcam présente. Actions : ouvrir Studio, démarrer la caméra, vérifier que « Capturer » est désactivé sans cadre ni sticker ; en choisir un, capturer, ajuster les calques, ajouter éventuellement des mots-clés et créer le montage. Attendu : capture possible avec superposition, aperçu cohérent, photo PNG finale dans « Mes créations » et dans la galerie publique, outils de montage remis à zéro après succès. Quitter Studio : le flux caméra doit s’arrêter.
- [ ] **Studio avec import.** Prérequis : compte connecté et PNG/JPEG de test. Actions : importer l’image sans démarrer la caméra, déplacer/zoomer le recadrage, valider, choisir au moins un cadre ou sticker, orienter et redimensionner un sticker, publier. Attendu : parcours utilisable sans webcam ; montage final composé côté serveur en PNG de 1 024 × 1 024 pixels, visible dans les deux galeries.
- [ ] **Likes, commentaires et notifications.** Prérequis : deux comptes activés, une photo du compte A, boîte email A accessible. Actions : avec B, liker, retirer le like, reliker et commenter ; vérifier les compteurs et le texte après rechargement ; avec A, désactiver les notifications, puis refaire un like/commentaire depuis B. Attendu : compteurs cohérents, texte affiché sans exécution HTML, email au propriétaire pour un nouveau like et un nouveau commentaire si la préférence est activée ; aucun email pour un retrait de like ni lorsque la préférence est désactivée. La réception externe doit être constatée dans la boîte de test.
- [ ] **Suppressions.** Prérequis : comptes A et B et une photo publiée par A. Actions : supprimer la photo depuis « Mes créations » avec A ; recharger la galerie, puis vérifier le lien de l’image. Attendu : photo et interactions associées absentes, fichier supprimé. B ne doit pas pouvoir supprimer la photo d’A. L’interface ne propose pas de suppression de commentaire : ne pas présenter cette fonction comme disponible.

## Erreurs et contrôle d’accès

- [ ] **Visiteur non connecté.** Actions : ouvrir Studio et Profil directement, puis essayer de liker, commenter ou publier sans session. Attendu : redirection vers la connexion pour les pages classiques, refus HTTP 403 pour les accès privés AJAX avec jeton CSRF valide ; aucune écriture.
- [ ] **Données d’un autre compte.** Prérequis : comptes A et B. Actions : connecté en B, tenter de supprimer une photo d’A en transmettant son `photo_id` ; consulter les photos personnelles de B. Attendu : suppression refusée comme photo introuvable et liste privée limitée aux photos de B.
- [ ] **Saisies invalides.** Actions : essayer un email mal formé, un pseudo trop long, un mot de passe sans lettre ou chiffre, un commentaire vide ou de plus de 500 caractères, un hashtag invalide et une requête POST sans CSRF. Attendu : refus côté serveur, sans modification de données ; POST sans CSRF renvoie 403.
- [ ] **Fichiers invalides.** Prérequis : session connectée. Actions : tenter un fichier non PNG/JPEG, un faux PNG, une image de plus de 10 Mio ou de plus de 20 millions de pixels, puis une création sans calque. Attendu : fichier ou création refusé, aucune photo publiée. La limite PHP `post_max_size=10M` peut interrompre une requête avant la validation métier.
- [ ] **Caméra absente ou refusée.** Prérequis : appareil sans webcam ou autorisation retirée. Actions : lancer la caméra, puis importer une image. Attendu : message d’impossibilité ou de permission, sans blocage de l’import.

## Navigateurs et appareils

- [ ] **Chrome ou Chromium récent.** Prérequis : version et système notés pendant l’essai. Actions : parcourir accueil, formulaires, galerie, studio webcam/import, interactions et profil ; tester la largeur d’un téléphone en portrait et paysage. Attendu : mise en page lisible, commandes utilisables et aucune erreur bloquante dans la console.
- [ ] **Firefox récent.** Prérequis : version et système notés. Actions : répéter les parcours et largeurs ci-dessus, notamment les permissions caméra et le chargement progressif. Attendu : mêmes fonctions disponibles. Le rendu d’une seule page ne prouve pas ces parcours complets.
- [ ] **Appareil mobile réel.** Prérequis : téléphone et origine HTTPS acceptée pour la caméra si l’accès se fait par réseau local. Actions : tester portrait/paysage, tactile, import et caméra si disponible. Attendu : aucun contrôle essentiel inaccessible. Le certificat fourni est autosigné pour `localhost` et `127.0.0.1`, pas pour l’IP du PC ; prévoir une origine sûre adaptée pour ce test.

Le sujet demande Firefox ≥ 41 et Chrome ≥ 46. Le code emploie des modules JavaScript et des fonctions modernes : ces minima historiques ne sont pas déclarés compatibles sans essai et adaptation spécifiques.

## Vérifications automatisées et résultats

Depuis `camagru/`, avec les conteneurs démarrés :

```sh
make ps
make test-security
```

`make test-security` exécute `src/tests/security.php` : création d’une base temporaire `camagru_test_*`, tests PHP/HTTP/SQL (sessions, CSRF, validation, photos, profil, notifications, pagination, concurrence, récupération), capture locale de `mail()` et nettoyage final. Aucun email SMTP externe n’est envoyé par cette suite.

Avec Node.js disponible sur l’hôte, lancez `node camagru/src/tests/xss-server.cjs` depuis la racine du dépôt, puis ouvrez `http://127.0.0.1:8793/` et `http://127.0.0.1:8793/pagination` dans un navigateur récent. Les deux pages de test DOM affichent `PASS` ou `FAIL` ; elles utilisent des fixtures et ne nécessitent ni base ni email externe. Leur présence n’est pas un résultat d’exécution.

**Résultats observés le 12 octobre 2026 dans cet environnement :** `docker compose config --quiet` a réussi ; `make start` a démarré deux services sains et exécuté `setup.php` ; les accès HTTP et HTTPS locaux ont répondu `200` (`curl` avec `-k` pour le certificat autosigné) ; `php -l` sur `public/index.php` a réussi ; `make test-security` s’est terminé avec **340 vérifications réussies et aucun email externe envoyé**. Cela ne valide pas un clone neuf isolé, la livraison SMTP réelle, la webcam ni les parcours manuels ci-dessus. Reporter les résultats de ces essais, la date, les versions des navigateurs et les écarts avant la soutenance.
