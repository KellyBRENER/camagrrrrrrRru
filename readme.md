#pour consulter la DB:
docker compose exec db mariadb -u root -p
rootpassword
USE camagru;
SHOW TABLES;
SELECT id, username, email, is_verified, token FROM users;
#pour quitter:
exit;

