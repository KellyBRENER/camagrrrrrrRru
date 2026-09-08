# Validation serveur — 2026-09-08

Périmètre initial : routes existantes. Complété le 2026-09-08 par les routes de profil décrites ci-dessous.

- Compte : textes correctement typés et UTF-8, sans caractère nul ; pseudo 50
  caractères maximum, email valide de 100 caractères maximum (inscription limitée
  à 100 octets comme auparavant), mot de passe 72 octets maximum. La création et la
  réinitialisation conservent la complexité minimale et les 8 caractères minimum.
  La connexion conserve la compatibilité avec les pseudos historiques sans leur
  imposer rétroactivement la nouvelle liste de caractères de l'inscription.
- Photos : identifiants entiers décimaux de 1 à 2147483647 ; refus des tableaux,
  booléens, flottants, suffixes et débordements plutôt qu'une conversion implicite.
- Pagination API : limit de 1 à 50, offset de 0 à 2147483647. Cela ne remplace pas la
  tâche distincte de pagination de l'interface avec au moins 5 éléments par page.
- Commentaire : chaîne UTF-8 sans nul, non vide après trim, au plus 500 caractères
  avant trim. Aucun échappement HTML ajouté au stockage.
- Hashtags : liste de 5 valeurs maximum, 25 caractères par valeur, règles existantes
  lettres/tirets conservées. Formats historiques texte séparé ou champ JSON acceptés.
- Cadres/stickers : identifiants simples de 50 caractères maximum, catalogue serveur
  toujours autoritaire, au plus 1 cadre et 10 stickers. Les calques doivent être une liste.
- Recadrage : objet de valeurs numériques finies ; positions de 0 à 100 %, dimensions
  de 0,001 à 100 %. Les paramètres absents ont les valeurs par défaut existantes.
- Stickers : centres de -100 à 200 %, dimensions de 0,001 à 300 %, rotation de -360
  à 360 degrés. Les valeurs hors bornes sont refusées et non ramenées silencieusement
  aux limites. Ces bornes incluent toutes celles utilisées par l'interface actuelle.
- Corps JSON : objet requis, syntaxe invalide/tableau racine/scalaire refusés avec
  JSON 400. Les erreurs de validation des contrôleurs sont prises en charge par Router.
- Images : contrôle de taille du base64 avant décodage, métadonnées d'upload typées,
  image PNG/JPEG valide requise ; données corrompues refusées sans warning PHP brut.

Fichiers principaux : Core/Validation.php, Core/Security.php, Core/Router.php,
Controllers/AuthController.php, Controllers/PhotoController.php, Services/PhotoComposer.php.

Tests : depuis camagru, `docker compose exec -T app php tests/security.php`.
Résultat : 190 vérifications réussies, base de test isolée, aucun email externe envoyé.
Les tests vérifient aussi les limites Unicode, les entrées mal formées, la compatibilité
JSON/formulaire des montages et l'absence de ligne photo résiduelle en cas de refus.
Syntaxe PHP vérifiée. La recette interactive du site reste une tâche distincte.

## Complément compte utilisateur

Routes privées profile_update/password_update : pseudo 1–50 caractères (lettres,
chiffres, _, . ou -), email valide de 100 caractères maximum, mot de passe actuel
requis. Nouveau mot de passe conforme à Security::validPassword et confirmation
identique obligatoire. Types invalides, doublons et mauvais mots de passe refusés
sans modification partielle. Compte sélectionné par la session uniquement.
Tests/profile_cases.php ajoute 42 vérifications ; suite complète : 232 réussites.
