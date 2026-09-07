# Camagru

Camagru est une application web de photo/montage realisee dans le cadre du sujet
42. L'utilisateur peut creer un compte, le valider par email, se connecter, prendre
ou uploader une photo dans un studio prive, appliquer des cadres/stickers PNG avec
transparence, puis generer une image finale cote serveur.

Le projet utilise PHP/Apache, MariaDB, JavaScript natif, CSS et Docker Compose. La
base MariaDB est stockee dans un volume Docker nomme `camagru_db_data`.

## Prerequis

- Docker
- Docker Compose
- Make
- un fichier `camagru/.env` avec les variables locales necessaires

Exemple de variables attendues :

```env
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=camagru
SMTP_USER=adresse@example.com
SMTP_PASS=mot-de-passe
```

## Lancer le projet

Depuis la racine du depot :

```bash
cd camagru
make up
make permissions
make setup
```

Puis ouvrir :

```text
http://localhost:8081
https://localhost:8443
```

Pour la camera, preferer `localhost`, `127.0.0.1` ou HTTPS. Une adresse reseau en
HTTP simple, par exemple `http://192.168.x.x:8081`, est generalement bloquee par le
navigateur.

`make start` existe aussi, mais il suit les logs a la fin et garde donc le terminal
occupe.

## Checker le projet

Etat des conteneurs :

```bash
cd camagru
make ps
```

Logs :

```bash
cd camagru
make logs
```

Verifier que le site repond :

```bash
curl -I http://localhost:8081
curl -k -I https://localhost:8443
```

Verifier la base :

```bash
cd camagru
docker compose exec -T db mariadb -uroot -prootpassword camagru -e "SHOW TABLES;"
docker compose exec -T db mariadb -uroot -prootpassword camagru -e "SELECT id, username, email, is_verified FROM users;"
docker compose exec -T db mariadb -uroot -prootpassword camagru -e "SELECT photo_id, user_id, path, created_at FROM photos ORDER BY photo_id DESC;"
```

Verifier la syntaxe PHP depuis le conteneur :

```bash
cd camagru
docker compose exec app php -l /var/www/html/public/index.php
docker compose exec app php -l /var/www/html/app/Controllers/AuthController.php
docker compose exec app php -l /var/www/html/app/Controllers/PhotoController.php
```

Verifier la syntaxe du JS module principal du studio :

```bash
node --input-type=module --check < camagru/src/public/js/pages/studio.js
```

## Stopper le projet

Arreter les conteneurs sans supprimer la DB :

```bash
cd camagru
make down
```

Supprimer et recreer seulement la base :

```bash
cd camagru
make resetdb
```

Supprimer conteneurs, images et volumes :

```bash
cd camagru
make finaldown
```

Attention : `make finaldown` et toute commande avec `docker compose down -v`
suppriment le volume MariaDB, donc les comptes/photos en base.

## Documentation

- `document.md` : document de reference long, notes techniques, DB, routes, stickers, camera, backups.
- `camagru.pdf` : sujet officiel.
- `register.txt` : details du parcours inscription/validation/login.
- `studio_reference.md` : details du studio.
