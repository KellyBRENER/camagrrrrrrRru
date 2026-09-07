# Camagru - document de reference du depot

Derniere analyse : 2026-08-06.

Ce document sert de repere pour les prochains prompts. Avant de modifier le projet,
relire au moins les sections "Carte rapide", "Ou modifier quoi" et "Regles absolues du
sujet".

Source sujet : `camagru.pdf`, version 4.1.


## 1. Resume du projet

Camagru est une application web de photo/montage.

Objectif du sujet :

- permettre a un utilisateur de creer un compte ;
- confirmer ce compte par un lien unique envoye par email ;
- se connecter / se deconnecter ;
- acceder a un studio prive ;
- choisir une image superposable avec canal alpha ;
- capturer une photo webcam ou uploader une image ;
- generer l'image finale cote serveur ;
- rendre les images publiques, likables et commentables.

Etat actuel du depot :

- architecture PHP maison proche MVC ;
- routage centralise par `Router.php` et `routes.php` ;
- session PHP classique ;
- inscription, validation email, login, logout et renvoi de lien de validation presents ;
- studio prive avec webcam, upload image, recadrage, filtres cadres/stickers en apercu navigateur ;
- cadres fixes et stickers deplacables/redimensionnables ;
- galerie, profil, stockage des creations, likes/commentaires et generation serveur finale encore a construire.


## 2. Carte rapide du depot

Racine :

- `camagru.pdf` : sujet officiel a respecter.
- `document.md` : ce document de reference.
- `register.txt` : documentation detaillee du parcours inscription / validation / connexion.
- `readme.md` : notes rapides pour consulter la DB.

Application :

- `camagru/docker-compose.yml` : services Docker `app` et `db`.
- `camagru/Makefile` : commandes pratiques Docker et DB.
- `camagru/docker/php/Dockerfile` : image PHP 8.2 Apache, GD, PDO MySQL, msmtp, SSL local.
- `camagru/docker/php/custom.ini` : config PHP, logs, uploads, mail.
- `camagru/docker/php/msmtprc.template` : configuration SMTP.
- `camagru/docker/php/default-ssl.conf` : VirtualHost HTTPS local.

Backend PHP :

- `camagru/src/public/index.php` : point d'entree HTTP.
- `camagru/src/public/logout.php` : destruction de session.
- `camagru/src/config/routes.php` : table de routage.
- `camagru/src/config/database.php` : connexion PDO a MariaDB.
- `camagru/src/config/setup.php` : initialise la base avec `init.sql`.
- `camagru/src/config/dropdb.php` : supprime la base.
- `camagru/src/database/init.sql` : schema SQL actuel.
- `camagru/src/app/Core/Router.php` : routeur PHP.
- `camagru/src/app/Controllers/AuthController.php` : auth, inscription, verification, renvoi, login, studio.
- `camagru/src/app/Controllers/PhotoController.php` : endpoint prive de creation du montage photo.
- `camagru/src/app/Controllers/PageController.php` : pages publiques simples.
- `camagru/src/app/Models/UserModel.php` : acces table `users`.
- `camagru/src/app/Models/PhotoModel.php` : acces backend photos, likes, commentaires, hashtags.
- `camagru/src/app/Services/PhotoComposer.php` : generation GD du PNG final cote serveur.

Vues PHP :

- `camagru/src/app/Views/layout.php` : squelette HTML, header, footer, JS principal.
- `camagru/src/app/Views/header.php` : barre haute, login/logout.
- `camagru/src/app/Views/footer.php` : navigation basse.
- `camagru/src/app/Views/home.php` : accueil.
- `camagru/src/app/Views/register.php` : formulaire inscription.
- `camagru/src/app/Views/registerinprogress.php` : compte cree, mail envoye.
- `camagru/src/app/Views/verify_success.php` : compte valide.
- `camagru/src/app/Views/verify_failed.php` : lien invalide/deja utilise.
- `camagru/src/app/Views/resend_validation.php` : renvoi lien de validation.
- `camagru/src/app/Views/login.php` : formulaire login.
- `camagru/src/app/Views/studio.php` : studio webcam/upload/filtres.
- `camagru/src/app/Views/gallery.php` : squelette galerie.
- `camagru/src/app/Views/profil.php` : squelette profil.

Frontend :

- `camagru/src/public/js/app.js` : responsive shell + interception liens `data-page`.
- `camagru/src/public/js/router.js` : navigation AJAX, chargement des scripts de page.
- `camagru/src/public/js/pages/register.js` : validation et POST AJAX inscription.
- `camagru/src/public/js/pages/login.js` : POST AJAX login.
- `camagru/src/public/js/pages/studio.js` : camera, upload, crop, filtres, stickers.
- `camagru/src/public/js/pages/home.js`, `gallery.js`, `profil.js` : actuellement vides.
- `camagru/src/public/css/style.css` : tout le theme et l'interface.

Assets :

- `camagru/src/public/images/leopard.jpg` : texture header/footer.
- `camagru/src/public/images/favicon.png` : favicon.
- `camagru/src/public/images/filters/filters.json` : manifeste des filtres.
- `camagru/src/public/images/filters/leopard-frame.png` : cadre PNG transparent.
- `camagru/src/public/images/filters/leopard-sticker.png` : sticker PNG transparent.
- `camagru/src/public/uploads/photos/` : dossier prevu pour les images finales generees.


## 3. Fonctionnement global des requetes

1. Toute requete applicative arrive sur `src/public/index.php`.
2. `index.php` demarre la session PHP.
3. Il charge `database.php`, `Router.php` et `routes.php`.
4. Il lit `$_GET['page']`, par defaut `home`.
5. Il detecte l'etat connecte avec `$_SESSION['user_id']`.
6. Il appelle `Router->handleRequest($page, $isLoggedIn)`.
7. `Router.php` :
   - verifie si la route existe ;
   - redirige vers `home` si la route est inconnue ;
   - bloque les pages privees si l'utilisateur n'est pas connecte ;
   - instancie le controleur ;
   - appelle la methode ;
   - recupere le nom de vue ;
   - renvoie soit la vue seule en AJAX, soit `layout.php`.

Important :

- Les pages AJAX sont chargees avec l'en-tete `X-Requested-With: XMLHttpRequest`.
- `layout.php` n'est charge que pour les requetes completes.
- Les scripts de page sont charges dynamiquement par `router.js`.
- Pour ajouter une page, il faut en general modifier :
  - `src/config/routes.php`
  - un controleur
  - une vue dans `src/app/Views/`
  - eventuellement `src/public/js/router.js` si un script de page est necessaire.


## 4. Authentification et comptes

Fichiers principaux :

- `src/app/Controllers/AuthController.php`
- `src/app/Models/UserModel.php`
- `src/app/Views/register.php`
- `src/app/Views/login.php`
- `src/app/Views/resend_validation.php`
- `src/public/js/pages/register.js`
- `src/public/js/pages/login.js`
- `src/database/init.sql`

Table actuelle :

```sql
users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    token VARCHAR(255) NULL
)
```

Tables backend photos ajoutees :

```sql
photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP
)

stickers (
    sticker_id VARCHAR(50) PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    hashtag VARCHAR(25),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

photo_likes (
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP,
    PRIMARY KEY (photo_id, user_id)
)

comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    comment VARCHAR(500) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

hashtags (
    hashtag_id INT AUTO_INCREMENT PRIMARY KEY,
    hashtag VARCHAR(25) NOT NULL UNIQUE,
    created_at TIMESTAMP
)

photo_hashtags (
    photo_id INT NOT NULL,
    hashtag_id INT NOT NULL,
    created_at TIMESTAMP,
    PRIMARY KEY (photo_id, hashtag_id)
)
```

Relations importantes :

- `photos.user_id` pointe vers `users.id`.
- `photo_likes.photo_id`, `comments.photo_id`, `photo_hashtags.photo_id` pointent vers `photos.photo_id`.
- Suppression d'une photo : suppression automatique des likes, commentaires et liens hashtags avec `ON DELETE CASCADE`.
- Suppression d'un user : suppression automatique de ses photos, likes et commentaires.
- La suppression d'une photo cote app passe par `POST /?page=photo_delete`, verifie que le user connecte est proprietaire, supprime la ligne DB puis le PNG genere.
- Pour les hashtags, ne pas stocker une liste de photos dans une colonne : utiliser `hashtags` + `photo_hashtags`.
- Chemin final attendu pour une photo : `uploads/photos/{user_id}/{photo_id}.png`.
- Le serveur doit generer la photo finale en PNG, meme si l'image client source est PNG, JPEG ou autre format accepte.
- Les donnees de crop et de placement sticker sont temporaires : elles servent uniquement a generer le montage final cote serveur.
- Ne pas stocker la position, taille ou rotation des stickers dans la base apres generation.
- La table `stickers` sert de catalogue serveur : validation des stickers actifs, chemin source et hashtag par defaut.
- A la creation d'une photo, stocker les hashtags envoyes par l'utilisateur, 5 maximum, plus les hashtags par defaut des stickers utilises.

Inscription :

- formulaire `register.php` ;
- validation navigateur dans `register.js` ;
- POST AJAX vers `/?page=register` ;
- validation serveur dans `AuthController::register()` ;
- hash du mot de passe avec `password_hash()` ;
- creation utilisateur via `UserModel::create()` ;
- token aleatoire avec `random_bytes(32)` ;
- email de validation via `mail()` et msmtp.

Validation :

- lien : `/?page=verify&token=...` ;
- `AuthController::verify()` lit le token ;
- `UserModel::confirmAccount()` cherche `token = ? AND is_verified = 0` ;
- si trouve : `is_verified = 1`, `token = NULL`.

Connexion :

- formulaire `login.php` ;
- POST AJAX via `login.js` ;
- `AuthController::login()` cherche par username ;
- verifie `password_verify()` et `is_verified` ;
- remplit `$_SESSION['user_id']` et `$_SESSION['username']`.

Renvoi du lien :

- page `resend_validation.php` ;
- route `resend_validation` ;
- cherche le compte par email ;
- refuse si deja verifie ;
- genere un nouveau token ;
- remplace le token en base ;
- renvoie un email.

Logout :

- `src/public/logout.php` ;
- `session_unset()`, `session_destroy()`, redirection home.

Attention sujet :

- Le sujet demande aussi le mail de reinitialisation de mot de passe oublie. Ce n'est pas encore implemente.
- Le sujet demande la modification username/email/password une fois connecte. La vue existe, mais pas encore la logique backend.
- Le sujet ne demande pas d'interdire les connexions simultanees sur mobile + ordinateur.


## 5. Studio, filtres et images

Fichiers principaux :

- `src/app/Views/studio.php`
- `src/public/js/pages/studio.js`
- `src/public/css/style.css`
- `src/public/images/filters/filters.json`
- `src/public/images/filters/frames/*.png`
- `src/public/images/filters/stickers/*.png`

Acces :

- route `studio` ;
- controleur `AuthController::studio()` ;
- route privee (`public => false`) ;
- non connecte : redirection login ou 403 AJAX.

Apercu :

- `#cameraPreview` est un carre CSS (`aspect-ratio: 1 / 1`).
- Toute la chaine studio doit rester carree : preview webcam, capture webcam, recadrage upload, montage final et visionneuse de montage.
- La webcam est dans `<video id="webcam">`.
- Le canvas technique est `<canvas id="overlay">`.
- Les images capturees/upload recadrees sont affichees avec `#capturedPhotoPreview`.

Camera :

- demarrage via `navigator.mediaDevices.getUserMedia()`.
- fallback legacy `getUserMedia`.
- `window.currentStream` sert a couper la camera lors du changement de page AJAX.

Upload et recadrage :

- bouton upload ouvre `<input type="file" id="uploadPhoto">`.
- accepte uniquement PNG/JPEG cote front.
- ouvre une modale de crop carree.
- crop avec drag + zoom.
- export du crop depuis les pixels source pour eviter une perte de qualite inutile.

Filtres :

- charges depuis `/images/filters/filters.json`.
- fichiers cadres dans `public/images/filters/frames/`.
- fichiers stickers dans `public/images/filters/stickers/`.
- deux types :
  - `frame` : cadre fixe, plein format, non modifiable ;
  - `sticker` : image transparente deplacable et redimensionnable.
- l'interface separe les cadres et les stickers.
- Stickers chats disponibles : `black-cat-sticker`, `angry-cat-sticker`, `scared-kitten-sticker`, `meowing-cat-sticker`, tous en PNG avec canal alpha.
- le bouton capture devient disponible des que la camera est active, meme sans filtre selectionne.

Manifest actuel :

```json
[
  {
    "id": "leopard-frame",
    "name": "Cadre leopard",
    "file": "/images/filters/frames/leopard-frame.png",
    "type": "frame"
  },
  {
    "id": "leopard-sticker",
    "name": "Leopard",
    "file": "/images/filters/stickers/leopard-sticker.png",
    "type": "sticker"
  }
]
```

Important sujet :

- Les images superposables doivent avoir un canal alpha.
- Le rendu live navigateur est un bonus utile, mais la creation finale doit etre faite cote serveur.
- Actuellement, l'aperçu existe cote navigateur et le backend de generation/sauvegarde est prepare via `PhotoController.php` + `PhotoComposer.php`.

Pour ajouter un filtre :

1. Mettre un PNG transparent dans `src/public/images/filters/frames/` pour un cadre ou `src/public/images/filters/stickers/` pour un sticker.
2. Ajouter une entree dans `filters.json`.
3. Choisir `type: "frame"` pour un cadre fixe plein format.
4. Choisir `type: "sticker"` pour un element modifiable.


## 6. Galerie et creations

Etat actuel :

- `gallery.php` est un squelette public.
- `gallery.js` est vide.
- Les tables SQL `photos`, `photo_likes`, `comments`, `hashtags`, `photo_hashtags` existent dans `init.sql`.
- `PhotoModel.php` fournit les methodes backend de base : creation photo, suppression par proprietaire, listing, likes, commentaires, hashtags.
- `PhotoModel.php` prepare aussi le chemin relatif `uploads/photos/{user_id}/{photo_id}.png`.
- `PhotoController.php` expose la route privee `POST /?page=photo_create` pour generer et enregistrer le montage final.
- Les placements de stickers restent temporaires : ils sont consommes par `PhotoComposer.php`, puis jetes.
- Pas encore de pagination.
- Pas encore d'email de notification de commentaire.
- Le controleur de creation photo existe ; les autres actions galerie/likes/commentaires restent a exposer au navigateur.

Sujet obligatoire :

- galerie publique ;
- toutes les images editees par tous les users ;
- tri par date de creation ;
- likes/commentaires seulement pour utilisateurs connectes ;
- notification email a l'auteur lors d'un nouveau commentaire ;
- preference notification activee par defaut mais desactivable ;
- pagination avec au moins 5 elements par page.

Pour construire cette partie, fichiers probables :

- `src/database/init.sql` : tables deja ajoutees, eventuellement preference notification.
- `src/app/Models/PhotoModel.php` : modele de base deja ajoute.
- controleur existant ou nouveau controleur galerie.
- `gallery.php` + `gallery.js`.
- routes AJAX/API dediees.
- `profil.php` pour preference email.


## 7. Profil

Etat actuel :

- `profil.php` affiche des formulaires username/email/password et une preference notification.
- `profil.js` est vide.
- Il n'y a pas de route `profil` dans `routes.php` actuellement.
- Le footer affiche pourtant un lien `data-page="profil"` si l'utilisateur est connecte.

Sujet obligatoire :

- une fois connecte, l'utilisateur doit pouvoir modifier username, email ou password.
- la preference de notification email doit etre vraie par defaut et desactivable.

Pour construire :

- ajouter route `profil` privee dans `routes.php`.
- ajouter methode controleur.
- ajouter methodes `UserModel` pour update username/email/password/preferences.
- ajouter colonnes SQL utiles (`email_notifications`, eventuellement `updated_at`).
- brancher `profil.js` ou POST classiques.


## 8. Docker, mail et base

Lancement :

```bash
cd camagru
docker compose up -d
```

Arret :

```bash
cd camagru
docker compose down
```

Makefile :

- `make up` : lance Docker.
- `make down` : arrete Docker.
- `make setup` : initialise DB.
- `make resetdb` : drop + setup.
- `make logs` : logs.
- `make shell` : shell app.

Ports :

- HTTP : `8081:80`.
- HTTPS local : `8443:443`.
- MariaDB : `3306:3306`.

Mail :

- PHP `mail()` passe par msmtp.
- `custom.ini` configure `sendmail_path="/usr/bin/msmtp -t"`.
- `msmtprc.template` utilise `SMTP_USER` et `SMTP_PASS`.
- Ces variables viennent de `.env` via `docker-compose.yml`.

Attention securite :

- Le sujet dit que credentials/env/API keys doivent etre dans `.env` ignore par git.
- Actuellement plusieurs identifiants DB sont hardcodes (`rootpassword`) dans `database.php`, `setup.php`, `dropdb.php`, `readme.md`.
- Avant rendu final, il faudra nettoyer cela ou au minimum verifier les exigences d'evaluation.


## 9. Regles absolues du sujet Camagru

Ces points doivent guider toutes les futures modifications.

### Regles techniques

- Client autorise : HTML, CSS, JavaScript avec APIs natives du navigateur.
- Pas de framework JS, pas de bibliotheque JS externe.
- Framework CSS tolere seulement s'il n'ajoute pas de JS interdit.
- Serveur : langage libre, mais limite a ce qui a un equivalent en librairie standard PHP.
- Containerisation obligatoire : au moins un container deployable en une commande type docker-compose.
- Compatible Firefox >= 41 et Chrome >= 46.
- L'application ne doit produire aucune erreur, warning ou ligne de log console/server/client.
- Exception toleree : erreurs `getUserMedia()` liees au manque de HTTPS.

### Regles securite

- Ne jamais stocker de mots de passe en clair ou non haches.
- Protections HTML/JS : ne jamais injecter de contenu user non echappe.
- Upload : refuser tout contenu indesirable.
- SQL : utiliser requetes preparees, ne jamais concatener des entrees utilisateur dans SQL.
- Ne pas permettre a un formulaire externe de manipuler des donnees privees.
- Credentials/API/env : stocker localement dans `.env` ignore par git.
- Penser CSRF/CORS/privacy : le sujet les cite comme notions importantes.

### Regles utilisateur

- Inscription avec email valide, username, password assez complexe.
- Confirmation du compte par lien unique envoye par email.
- Connexion par username + password.
- Mot de passe oublie : l'utilisateur doit pouvoir demander un email de reinitialisation.
- Deconnexion en un clic sur n'importe quelle page.
- Utilisateur connecte : doit pouvoir modifier username, email, password.

### Regles galerie

- Galerie publique.
- Affiche toutes les images editees de tous les utilisateurs.
- Tri par date de creation.
- Likes/commentaires seulement pour utilisateurs connectes.
- Commentaire : notifier l'auteur par email.
- Preference notification activee par defaut, desactivable.
- Pagination obligatoire avec au moins 5 elements par page.

### Regles studio/edition

- Page studio accessible seulement aux utilisateurs authentifies.
- Rejet poli des visiteurs non connectes.
- Deux zones principales :
  - section principale : preview webcam, liste images superposables, bouton capture ;
  - section laterale : miniatures des photos precedentes de l'utilisateur.
- Images superposables selectionnables.
- Bouton capture inactif tant qu'aucune image superposable n'est selectionnee.
- Images superposables avec canal alpha.
- Creation finale de l'image cote serveur, y compris la superposition.
- Upload image utilisateur obligatoire pour ceux qui n'ont pas de webcam.
- Suppression : l'utilisateur peut supprimer ses propres images, jamais celles des autres.

### Bonus

Les bonus ne comptent que si la partie obligatoire est parfaite.

Idees du sujet :

- AJAXifier les echanges.
- Live preview du resultat directement sur webcam.
- Pagination infinie.
- Partage social.
- GIF anime.


## 10. Ou modifier quoi

Nouvelle route/page :

- `src/config/routes.php`
- controleur dans `src/app/Controllers/`
- vue dans `src/app/Views/`
- si navigation AJAX : ajouter le script dans `router.js` mapping `pageScripts`.

Auth/login/register/verify/resend/password :

- `src/app/Controllers/AuthController.php`
- `src/app/Models/UserModel.php`
- vues auth dans `src/app/Views/`
- scripts `src/public/js/pages/login.js` ou `register.js`
- schema `src/database/init.sql` si nouvelles colonnes.

Profil :

- `routes.php`
- controleur auth ou nouveau controleur profil
- `UserModel.php`
- `profil.php`
- `profil.js`
- `init.sql`.

Studio visuel :

- `studio.php`
- `studio.js`
- `style.css`
- `public/images/filters/filters.json`
- `public/images/filters/frames/*.png`
- `public/images/filters/stickers/*.png`.

Generation image finale cote serveur :

- route privee : `POST /?page=photo_create`.
- controleur : `PhotoController.php`.
- rendu image : `PhotoComposer.php`.
- modele DB : `PhotoModel.php`.
- utiliser PHP GD (`imagecreatefromstring`, `imagecreatefrompng`, `imagecopy`, `imagecopyresampled`, `imagepng`).
- valider cote serveur l'id de filtre, ne jamais faire confiance au chemin envoye par client.
- sauvegarder fichier final dans `src/public/uploads/photos/`.
- creer le sous-dossier `src/public/uploads/photos/{user_id}/` avant d'ecrire le PNG.
- enregistrer en base uniquement la photo finale, son chemin, son auteur et ses hashtags.
- ordre obligatoire : creer l'entree DB pour obtenir `photo_id`, generer le PNG sous `uploads/photos/{user_id}/{photo_id}.png`, supprimer l'entree DB si le rendu ou l'ecriture fichier echoue.

Donnees attendues pour la future creation serveur :

- `image` ou `source_image` : image source utilisateur en data URL base64, base64 brut, ou fichier multipart `image`/`photo` ;
- `frame_id` : id du cadre fixe, valide depuis `public/images/filters/filters.json` ;
- donnees de crop en pourcentage ou coordonnees normalisees par rapport a l'image source ;
- `stickers` : un ou plusieurs sticker ids, ou objets avec `sticker_id`, `center_x_percent`, `center_y_percent`, `width_percent`, `height_percent`, `rotation_degrees` ;
- pour chaque sticker, donnees temporaires de rendu : taille, centre X/Y dans le cadre carre final et orientation ;
- `hashtags` : hashtags utilisateur, 5 maximum ;
- hashtags par defaut des stickers utilises, ajoutes automatiquement cote serveur.
- formats image acceptes pour upload/source : PNG, JPEG ;
- taille source maximum : 10 Mo ;
- dimensions source maximum : 20 millions de pixels ;
- sortie finale serveur : PNG 1024x1024.

Branchement studio actuel :

- bouton front : `Créer le montage sauvage` ;
- fichier : `public/js/pages/studio.js` ;
- la requete part vers `POST /?page=photo_create` en JSON ;
- la route privee `GET /?page=photo_mine` recharge les montages du user dans `#myGallery` ;
- le front ajoute la miniature retournee dans `#myGallery` apres succes ;
- cliquer une miniature ouvre une visionneuse modale carree avec fermeture, clic hors modale, fleches precedent/suivant et navigation clavier gauche/droite ;
- le front envoie l'image actuellement affichee dans l'apercu. Pour l'upload recadre, l'image envoyee est donc deja le resultat du recadrage navigateur actuel.
- le front envoie `layers` pour conserver l'ordre des ajouts utilisateur ;
- un seul cadre maximum est autorise ; cliquer de nouveau sur le cadre le retire ;
- 10 stickers maximum sont autorises ;
- chaque sticker affiche une croix de suppression dans son cadre ;
- sur desktop, le cadre/les poignees/la croix d'un sticker sont invisibles hors survol ; sur tactile, le sticker actif garde les controles visibles ;
- chaque sticker peut etre deplace, redimensionne et tourne ; le front envoie `rotation_degrees`, et `PhotoComposer.php` applique la rotation avec GD.
- la creation bloque au-dela de 5 mots-cles utilisateur avec un message dans la modale ; les hashtags automatiques des stickers peuvent s'ajouter cote serveur.
- les mots-cles utilisateur sont separes par virgules ou espaces, normalises en minuscules, et doivent contenir uniquement des lettres Unicode et des tirets internes ; pas de chiffre, underscore, `#`, espace interne, tiret au debut/a la fin, ni mot-cle de plus de 25 caracteres.
- les miniatures de `#myGallery` ont une icone poubelle avec confirmation avant suppression ;
- le backend valide les calques et les rend dans le meme ordre.

Regle importante :

- L'ecran du user ne doit jamais determiner la qualite ou le rendu final.
- Le client peut afficher en CSS, mais les donnees envoyees au serveur doivent etre normalisees en pourcentage.
- Le serveur reconstruit l'image finale en PNG dans la dimension imposee par le cadre.
- Les donnees de crop et placement sticker ne sont pas persistantes : elles sont jetees apres generation du fichier final.

Galerie :

- `gallery.php`
- `gallery.js`
- nouveaux modeles images/likes/comments
- routes API
- `init.sql`.
- route publique `GET /?page=photo_public_list` : liste les montages avec auteur, compteurs likes/commentaires et `liked_by_user` si le visiteur est connecte.
- route publique `GET /?page=photo_public_list&hashtag=...` : filtre la galerie sur les hashtags contenant le terme valide recherche, du plus recent au plus ancien, avec le meme format que la liste publique.
- route publique `GET /?page=photo_hashtag_list` : liste les hashtags existants en base pour les suggestions de recherche.
- route publique `GET /?page=photo_comments&photo_id=...` : liste les commentaires d'une photo.
- route privee `POST /?page=photo_like_toggle` : ajoute ou retire le like du user connecte.
- route privee `POST /?page=photo_comment_add` : ajoute un commentaire de 500 caracteres maximum.
- visiteurs non connectes : lecture galerie/commentaires OK, like/commentaire interdits.
- cliquer une photo de la galerie ouvre une visionneuse modale carree avec fermeture, clic hors modale, fleches precedent/suivant et navigation clavier gauche/droite.
- la galerie propose une recherche par hashtag : saisie puis Entree, suggestions cliquables, message vide specifique si aucune photo ne correspond, bouton pour revenir a l'affichage complet.

CSS/layout :

- `style.css`.
- Attention mobile : verifier textes, boutons, cadres et preview carre.
- Dans le studio, la taille de l'apercu ne doit pas etre fixee par breakpoint : le container central change selon le format, puis le carre d'apercu prend la plus grande taille possible dans ce container en reservant seulement les marges minimales et la colonne de boutons.
- Dans ce container central, l'apercu reste carre, centre verticalement et pousse le plus a gauche possible ; les boutons de capture restent dans une colonne minimale le plus a droite possible.
- Desktop : `body.app-shell` garde header/main/footer visibles avec le contenu central contraint.
- Mobile tactile, portrait ou paysage : ne pas forcer header/footer fixes ; laisser la page scroller pour garder une experience utilisable avec la barre navigateur.
- Studio mobile portrait : ordre vertical filtres -> apercu -> creations, avec scroll interne limite dans les panneaux filtres/creations.
- Studio mobile paysage : garder une disposition en ligne si la largeur le permet ; sinon deux colonnes filtres + apercu, puis creations dessous, avec panneaux limites et scroll interne.

Docker/mail/base :

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/php/custom.ini`
- `docker/php/msmtprc.template`
- `config/database.php`
- `config/setup.php`
- `config/dropdb.php`.
- `make start` lance les conteneurs, applique les permissions `public/uploads`, initialise la base, puis suit les logs Docker.


## 11. Etat des exigences sujet

Fait ou partiellement fait :

- layout header/main/footer : fait.
- responsive : partiellement fait.
- Docker compose : fait.
- inscription : fait.
- hash password : fait.
- verification email : fait.
- login : fait.
- logout : fait.
- studio prive : fait.
- webcam : fait.
- upload image : fait.
- filtres alpha : assets presents.
- selection filtre + bouton capture conditionne : fait cote front.
- live preview filtre : fait cote front.

Manquant ou a finaliser :

- page 404 au lieu de retour silencieux home.
- suppression des logs console/server avant rendu final.
- gestion `.env` propre pour credentials DB/SMTP.
- mot de passe oublie + reinitialisation email.
- route et logique profil.
- modification username/email/password.
- preference notification email en base.
- generation finale serveur avec superposition.
- route/controller de sauvegarde des creations.
- miniatures "Mes creations".
- suppression uniquement de ses images.
- galerie publique reelle.
- likes/commentaires.
- notification email de commentaire.
- pagination galerie minimum 5 par page.
- protections CSRF plus solides.
- durcissement upload serveur.


## 12. Points d'attention avant toute modification

- Ne pas ajouter de framework JS.
- Ne pas faire la generation finale uniquement en JS : le sujet exige le serveur.
- Ne pas faire confiance aux chemins de fichiers envoyes par le navigateur.
- Les filtres doivent rester des PNG avec transparence.
- Pour les filtres :
  - `frame` = fixe plein cadre ;
  - `sticker` = deplacable/redimensionnable.
- Si on ajoute une page privee, mettre `public => false` dans `routes.php`.
- Si on ajoute une page dans le footer/header, verifier qu'une route existe.
- Si on touche la camera, verifier mobile + HTTPS local.
- Si on touche aux mails, verifier msmtp + `.env`.
- Si on touche aux formulaires, valider cote client ET cote serveur.
- Si on touche a SQL, utiliser PDO prepare.
- Si on touche aux images upload, verifier MIME, taille, extension et traitement serveur.
- Si on touche a la session, ne pas casser l'acces AJAX du routeur.
- Si on touche a `style.css`, verifier le rendu mobile : header, footer, boutons, studio carre.


## 13. Notes pratiques recuperees depuis l'ancien README

Cette section regroupe les notes operationnelles qui etaient dans `readme.md`, afin que
le README reste une synthese courte et que les details techniques restent ici.

### Consulter la DB

Depuis `camagru/` :

```bash
docker compose exec db mariadb -u root -p
```

Mot de passe local actuel :

```text
rootpassword
```

Commandes SQL utiles :

```sql
USE camagru;
SHOW TABLES;
SELECT id, username, email, is_verified, token FROM users;
SELECT photo_id, user_id, path, created_at FROM photos ORDER BY photo_id DESC;
EXIT;
```

En une commande :

```bash
docker compose exec -T db mariadb -uroot -prootpassword camagru -e "SHOW TABLES;"
```

### Backups DB

Creer un dump :

```bash
cd camagru
mkdir -p backups
docker compose exec -T db mariadb-dump -uroot -prootpassword camagru > backups/camagru.sql
```

Restaurer un dump :

```bash
cd camagru
docker compose exec -T db mariadb -uroot -prootpassword camagru < backups/camagru.sql
```

### Volume MariaDB persistant

Le service MariaDB utilise un volume Docker nomme :

```yaml
volumes:
  - db_data:/var/lib/mysql
```

Compose le nomme effectivement `camagru_db_data`.

Avant cet ajout, les donnees MariaDB etaient stockees dans `/var/lib/mysql` via des
volumes Docker anonymes crees par l'image MariaDB. Ces volumes pouvaient survivre a un
simple `docker compose down`, mais ils etaient difficiles a identifier et pouvaient etre
remplaces par un nouveau volume au prochain cycle Docker Compose.

Les fichiers PNG generes sont, eux, stockes dans le dossier projet :

```text
camagru/src/public/uploads/photos/
```

Ils peuvent donc survivre meme si les lignes correspondantes de la DB ont disparu.

### Ajouter un sticker

Un sticker doit exister a deux endroits :

- dans `camagru/src/public/images/filters/filters.json`, pour etre affiche dans la page `studio` ;
- dans la table `stickers`, pour etre accepte par le backend au moment de creer le montage.

#### 1. Ajouter le fichier image

Placer le PNG transparent dans :

```text
camagru/src/public/images/filters/stickers/
```

Regles conseillees :

- format PNG avec canal alpha ;
- fond transparent reel, pas un damier dessine dans l'image ;
- nom en kebab-case, par exemple `chat-noir-sticker.png` ;
- dimensions connues, car elles sont stockees en base pour garder le ratio du sticker.

#### 2. Ajouter l'entree dans `filters.json`

Exemple :

```json
{
  "id": "chat-noir-sticker",
  "name": "Chat noir",
  "file": "/images/filters/stickers/chat-noir-sticker.png",
  "type": "sticker"
}
```

Important :

- `id` doit etre unique ;
- `type` doit valoir `sticker` ;
- `file` est le chemin public utilise par le front ;
- ce fichier sert seulement au chargement visuel des filtres dans le studio.

#### 3. Ajouter le sticker en base

Ajouter aussi le sticker dans le bloc `INSERT INTO stickers` de :

```text
camagru/src/database/init.sql
```

Exemple :

```sql
('chat-noir-sticker', 'images/filters/stickers/chat-noir-sticker.png', 1222, 886, 'chat-noir')
```

Champs :

- `sticker_id` : meme valeur que le `id` du JSON ;
- `path` : chemin relatif cote serveur, sans slash au debut ;
- `width` et `height` : dimensions reelles du PNG ;
- `hashtag` : hashtag ajoute automatiquement aux photos qui utilisent ce sticker.

Le hashtag doit respecter les regles actuelles :

- lettres Unicode et tirets uniquement ;
- pas de chiffre, pas de `_`, pas de `#` ;
- pas de tiret au debut ou a la fin ;
- 25 caracteres maximum.

#### 4. Synchroniser la base deja lancee

Modifier `init.sql` suffit pour une base neuve, mais pas pour la base Docker deja en cours.

Pour ajouter le sticker sans reset la DB :

```sql
INSERT INTO stickers (sticker_id, path, width, height, hashtag)
VALUES ('chat-noir-sticker', 'images/filters/stickers/chat-noir-sticker.png', 1222, 886, 'chat-noir')
ON DUPLICATE KEY UPDATE
    path = VALUES(path),
    width = VALUES(width),
    height = VALUES(height),
    hashtag = VALUES(hashtag),
    is_active = TRUE;
```

Depuis le terminal :

```bash
cd camagru
docker compose exec db mariadb -u root -p
```

Puis entrer le mot de passe, selectionner la base et executer la requete :

```sql
USE camagru;
```

#### 5. Verifier

Verifier que le sticker est actif :

```sql
SELECT sticker_id, path, width, height, hashtag, is_active
FROM stickers
WHERE sticker_id = 'chat-noir-sticker';
```

Verifier aussi que `filters.json` est un JSON valide avant de relancer/tester le studio.

### Camera et HTTPS

Les navigateurs modernes autorisent `getUserMedia()` seulement depuis une origine
securisee.

OK en local :

- `http://localhost:8081`
- `http://127.0.0.1:8081`
- `https://localhost:8443`

Souvent bloque :

- `http://192.168.x.x:8081`
- toute IP reseau en HTTP simple.

Pour tester depuis un telephone ou un autre appareil du reseau, utiliser HTTPS :

```text
https://<ip-du-pc>:8443
```

Le certificat local peut devoir etre accepte manuellement dans le navigateur.
