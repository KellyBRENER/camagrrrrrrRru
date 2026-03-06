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
	docker compose exec $(PHP_SERVICE) php config/setup.php

# =========================
# FULL START
# =========================

start: up
	sleep 5
	make setup