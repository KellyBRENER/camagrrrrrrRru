# Camagru

Camagru est une application web de création et de partage de montages photo réalisée pour le sujet 42 [Camagru, version 4.1](camagru/docs/camagru.pdf). Un compte activé peut utiliser une webcam ou importer une image, lui superposer des cadres et des stickers, puis publier le PNG composé par le serveur. La galerie est publique.

## Fonctionnalités

- Inscription avec confirmation par email, renvoi du lien d’activation, connexion, déconnexion et récupération du mot de passe.
- Profil : modification du pseudonyme, de l’adresse email et du mot de passe ; activation ou désactivation des notifications par email.
- Studio privé : capture webcam ou import PNG/JPEG, recadrage, aperçu, un cadre et jusqu’à dix stickers déplaçables, redimensionnables et orientables. Au moins une superposition est nécessaire pour créer une photo.
- Composition PHP/GD en PNG de 1 024 × 1 024 pixels, ajout de mots-clés et gestion de ses propres photos.
- Galerie publique avec recherche partielle par hashtag et chargement progressif par lots de cinq ; likes et commentaires pour les comptes connectés. Un nouveau like ou commentaire peut notifier l’auteur par email selon sa préférence.

## Technologies et prérequis

L’application utilise PHP 8.2 avec GD, PDO MySQL et mbstring, Apache, MariaDB 12.3.2, HTML, CSS et JavaScript natif. Bootstrap CSS 5.3.0 et Bootstrap Icons 1.11.3 sont fournis localement ; aucun framework JavaScript n’est utilisé. L’envoi d’emails passe par `mail()` et msmtp, configuré pour Gmail SMTP (`smtp.gmail.com:587`).

Sur la machine hôte, installez Docker, Docker Compose avec `up --wait`, et Make. Les ports hôtes `8081` et `8443` doivent être libres. Une connexion SMTP Gmail et un mot de passe d’application valides sont nécessaires pour recevoir les emails d’activation et de récupération. Aucun runtime PHP ou MariaDB n’est requis sur l’hôte.

## Installation depuis un clone neuf

Depuis la racine du dépôt :

```sh
cd camagru
cp .env.example .env
```

Éditez `camagru/.env` avec vos valeurs locales : `MYSQL_ROOT_PASSWORD` pour le compte administrateur MariaDB, `MYSQL_DATABASE` pour le nom de la base, `SMTP_USER` et `SMTP_PASS` pour le compte Gmail SMTP, `APP_URL` pour les liens envoyés par email et, si besoin, `APP_ALLOWED_ORIGINS` pour les autres origines autorisées. Le fichier est ignoré par Git. Le modèle contient uniquement des valeurs fictives.

`APP_ALLOWED_ORIGINS` contient des origines complètes séparées par des virgules, avec protocole et port. Si l’origine de la requête figure dans cette liste, elle est utilisée dans les liens envoyés ; sinon le code utilise `APP_URL`. Indiquez une adresse réellement accessible au destinataire. Le serveur SMTP est fixé dans `docker/php/msmtprc.template` ; changer de fournisseur exige une modification de ce fichier.

Initialisez et démarrez les deux services :

```sh
make start
```

La commande lance `app` et `db`, attend que MariaDB soit saine, puis exécute `config/setup.php`. Ce script crée le schéma, initialise le catalogue des stickers et applique les migrations de `src/database/migrations/`. Les permissions de `src/public/uploads` sont préparées au démarrage du conteneur. Les services restent en arrière-plan. Pour appliquer seulement les migrations à une installation existante, utilisez `make migrate` après démarrage ; `make build`, `make up`, `make permissions` et `make setup` sont aussi disponibles séparément.

## Accès et utilisation

Ouvrez `http://localhost:8081` ou `https://localhost:8443`. Le certificat HTTPS local est autosigné et créé pour `localhost` et `127.0.0.1` : votre navigateur peut demander de l’accepter. La webcam exige une origine que le navigateur considère comme sûre, par exemple `localhost` ou un HTTPS accepté. L’import reste utilisable sans webcam.

Consultez la galerie sans compte. Pour publier, inscrivez-vous, ouvrez l’email reçu, confirmez l’activation sur la page du lien, puis connectez-vous. Dans **Studio**, choisissez un cadre ou un sticker, capturez une image ou importez un PNG/JPEG, ajustez le montage et validez sa publication. Vos créations se gèrent depuis le studio ; les préférences email et les informations du compte se modifient dans **Profil**.

## Commandes et données

Les commandes suivantes s’exécutent depuis `camagru/` :

| Commande | Effet sur les données |
| --- | --- |
| `make ps` / `make logs` | État des services / suivi des journaux ; aucune réinitialisation. |
| `make down` | Arrête et retire les conteneurs ; conserve la base et les fichiers photo. |
| `make start` | Démarre les services et réapplique le schéma et les migrations sans vider la base. |
| `make resetdb` | **Supprime puis recrée toute la base** : comptes, photos en base, interactions et commentaires perdus. Les fichiers PNG sur l’hôte restent présents. |
| `make reset` | **Supprime les montages utilisateurs puis le volume MariaDB**, redémarre les services, prépare les permissions et réinitialise la base. |
| `make finaldown` | **Supprime les montages utilisateurs, les conteneurs, le volume MariaDB et les images Docker**. |

La base utilise le volume Compose `db_data` ; les montages sont dans `camagru/src/public/uploads/photos/`, monté depuis l’hôte. `reset` et `finaldown` effacent le contenu de ce dossier avant de supprimer le volume, sauf son fichier `.gitkeep` ; un échec du nettoyage interrompt la commande avant `down -v`. `resetdb` conserve les fichiers photo et peut donc laisser des PNG orphelins. Sauvegardez la base et les montages avant toute réinitialisation.

## Repères et vérification

```text
camagru/
├── docker/php/          image et configuration Apache, PHP, msmtp
├── src/app/             contrôleurs, modèles, services, vues et classes communes
├── src/config/          routes, connexion et scripts d’initialisation
├── src/database/        schéma SQL et migrations
├── src/public/          entrée HTTP, JavaScript, CSS, filtres et photos
├── src/tests/           vérifications PHP/HTTP/SQL et pages de test DOM
├── docker-compose.yml
└── Makefile
```

L’organisation des requêtes, des tables et du studio est décrite dans [l’architecture](camagru/docs/architecture.md). La [checklist de validation](camagru/docs/validation.md) détaille les parcours manuels et les commandes de contrôle. La suite automatisée disponible se lance par `make test-security` depuis `camagru/` : elle crée une base temporaire distincte et capture les emails localement, sans envoi SMTP externe. `make ps` et `make logs` servent au diagnostic.

## Sécurité et limites

Le code emploie des requêtes PDO préparées, des contrôles de saisie et d’autorisation côté serveur, un jeton CSRF pour les POST, l’échappement des contenus affichés et des mots de passe hachés. Les images source sont vérifiées (PNG/JPEG, contenu, 10 Mio au plus, 20 millions de pixels au plus) et réencodées par GD. Les cookies de session sont `HttpOnly` et `SameSite=Lax`, avec `Secure` en HTTPS. Les liens de récupération expirent après 30 minutes et sont à usage unique. Voir [l’architecture](camagru/docs/architecture.md) pour la portée exacte de ces contrôles.

La livraison des emails par un vrai compte SMTP, la webcam matérielle et les parcours sur appareils réels demandent une validation manuelle. Le certificat autosigné ne couvre pas une adresse IP de réseau local. Le JavaScript utilise des API et une syntaxe modernes : la compatibilité avec les minima Firefox 41 et Chrome 46 cités dans le sujet n’est pas établie et ne doit pas être présumée. Les fichiers photo et la base n’ont pas de transaction commune : une interruption brutale peut demander une réconciliation manuelle.

## Crédits et ressources

Projet réalisé dans le cadre du cursus 42, selon le [sujet Camagru fourni dans le dépôt](camagru/docs/camagru.pdf). Les styles utilisent [Bootstrap](camagru/src/public/vendor/bootstrap/LICENSE) et [Bootstrap Icons](camagru/src/public/vendor/bootstrap-icons/LICENSE), dont les licences sont conservées avec les fichiers distribués. Les visuels de l’interface et les filtres PNG sont inclus dans `src/public/images/` ; leur provenance et leurs conditions de réutilisation ne sont pas documentées dans le dépôt.
