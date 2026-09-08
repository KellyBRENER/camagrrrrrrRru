# Nom du service PHP dans docker-compose
PHP_SERVICE=app

# =========================
# DOCKER
# =========================

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart:
	docker compose down
	docker compose up -d

logs:
	docker compose logs -f

ps:
	docker compose ps

permissions:
	docker compose exec $(PHP_SERVICE) sh -c "mkdir -p public/uploads/photos && chmod -R 777 public/uploads"

# =========================
# CONTAINER ACCESS
# =========================

shell:
	docker compose exec $(PHP_SERVICE) sh

# =========================
# DATABASE SETUP
# =========================

setup:
	docker compose exec $(PHP_SERVICE) php config/setup.php

test-security:
	docker compose exec -T $(PHP_SERVICE) php tests/security.php

migrate:
	docker compose exec $(PHP_SERVICE) php config/migrate.php

dropdb:
	docker compose exec $(PHP_SERVICE) php config/dropdb.php

resetdb: dropdb setup

# =========================
# RESET ENV
# =========================

reset:
	docker compose down -v
	docker compose up -d
	sleep 5
	docker compose exec $(PHP_SERVICE) sh -c "mkdir -p public/uploads/photos && chmod -R 777 public/uploads"
	docker compose exec $(PHP_SERVICE) php config/setup.php

finaldown:
	docker compose down -v --rmi all

# =========================
# FULL START
# =========================

start: up
	sleep 10
	make permissions
	make setup
	make logs
