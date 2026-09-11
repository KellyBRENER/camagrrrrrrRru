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

reset:
	docker compose down -v
	docker compose up -d --wait --wait-timeout 120
	docker compose exec -T $(PHP_SERVICE) uploads-permissions
	docker compose exec -T $(PHP_SERVICE) php config/setup.php

finaldown:
	docker compose down -v --rmi all

# =========================
# FULL START
# =========================

start: up
	$(MAKE) setup

.PHONY: wait-db
wait-db:
	@docker compose up -d --wait --wait-timeout 120 db || { echo "MariaDB indisponible : délai de disponibilité dépassé ou démarrage échoué." >&2; exit 1; }
