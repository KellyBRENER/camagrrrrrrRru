# Nom du service PHP dans docker-compose
PHP_SERVICE=app

# =========================
# DOCKER
# =========================

up: wait-db
	docker compose up -d --wait --wait-timeout 120

down:
	docker compose down

build:
	docker compose build

restart:
	docker compose down
	docker compose up -d --wait --wait-timeout 120

logs:
	docker compose logs -f

ps:
	docker compose ps

permissions:
	docker compose exec -T $(PHP_SERVICE) uploads-permissions

# =========================
# CONTAINER ACCESS
# =========================

shell:
	docker compose exec $(PHP_SERVICE) sh

# =========================
# DATABASE SETUP
# =========================

setup: wait-db
	docker compose exec -T $(PHP_SERVICE) php config/setup.php

test-security:
	docker compose exec -T $(PHP_SERVICE) php tests/security.php

migrate: wait-db
	docker compose exec -T $(PHP_SERVICE) php config/migrate.php

dropdb:
	docker compose exec -T $(PHP_SERVICE) php config/dropdb.php

resetdb: dropdb setup

# =========================
# RESET ENV
# =========================

.PHONY: clean-photos reset finaldown
clean-photos:
	@docker compose stop $(PHP_SERVICE) >/dev/null || { echo "Nettoyage des photos impossible : arrêt du service app échoué ; volumes conservés." >&2; exit 1; }
	@set -eu; \
	photos_dir=src/public/uploads/photos; \
	for path in src src/public src/public/uploads "$$photos_dir"; do \
		if [ -L "$$path" ]; then echo "Nettoyage des photos refusé : lien symbolique sur $$path." >&2; exit 1; fi; \
	done; \
	if [ ! -e "$$photos_dir" ]; then exit 0; fi; \
	if [ ! -d "$$photos_dir" ]; then echo "Nettoyage des photos impossible : dossier invalide." >&2; exit 1; fi; \
	if [ -r "$$photos_dir" ] && [ -x "$$photos_dir" ]; then \
		rest=$$(find -P "$$photos_dir" -mindepth 1 -maxdepth 1 ! \( -name .gitkeep -type f \) -printf x -quit) || { echo "Nettoyage des photos impossible : lecture du dossier échouée." >&2; exit 1; }; \
		if [ -z "$$rest" ]; then exit 0; fi; \
	fi; \
	docker compose run --rm --no-deps --user 0:0 --entrypoint /bin/sh $(PHP_SERVICE) -ec ' \
		dir=/var/www/html/public/uploads/photos; \
		for path in /var/www/html/public /var/www/html/public/uploads "$$dir"; do \
			if [ -L "$$path" ]; then echo "Nettoyage des photos refusé : lien symbolique sur $$path." >&2; exit 1; fi; \
		done; \
		if [ ! -e "$$dir" ]; then exit 0; fi; \
		if [ ! -d "$$dir" ]; then echo "Nettoyage des photos impossible : dossier invalide." >&2; exit 1; fi; \
		find -P "$$dir" -mindepth 1 -maxdepth 1 ! \( -name .gitkeep -type f \) -exec rm -rf -- {} + \
	' || { echo "Nettoyage des photos échoué ; conteneurs et volumes conservés." >&2; exit 1; }

reset: clean-photos
	docker compose down -v
	docker compose up -d --wait --wait-timeout 120
	docker compose exec -T $(PHP_SERVICE) uploads-permissions
	docker compose exec -T $(PHP_SERVICE) php config/setup.php

finaldown: clean-photos
	docker compose down -v --rmi all

# =========================
# FULL START
# =========================

start: up
	$(MAKE) setup

.PHONY: wait-db
wait-db:
	@docker compose up -d --wait --wait-timeout 120 db || { echo "MariaDB indisponible : délai de disponibilité dépassé ou démarrage échoué." >&2; exit 1; }
