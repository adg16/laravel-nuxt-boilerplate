.PHONY: setup up down restart build sh-php sh-node artisan migrate fresh test logs lint lint-fix install-hooks

setup:
	[ -f .env ] || cp .env.example .env
	[ -f backend/.env ] || cp backend/.env.example backend/.env
	[ -f frontend/.env ] || cp frontend/.env.example frontend/.env
	docker compose build
	docker compose run --rm php composer install
	docker compose run --rm php php artisan key:generate
	docker compose run --rm node npm install
	docker compose up -d
	docker compose exec php php artisan migrate --seed
	$(MAKE) install-hooks

install-hooks:
	git config core.hooksPath .githooks

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build

sh-php:
	docker compose exec php sh

sh-node:
	docker compose exec node sh

artisan:
	docker compose exec php php artisan $(ARGS)

migrate:
	docker compose exec php php artisan migrate

fresh:
	docker compose exec php php artisan migrate:fresh --seed

test:
	docker compose exec php php artisan test
	docker compose exec node npm run typecheck

lint:
	docker compose run --rm --no-deps php vendor/bin/pint --test
	docker compose run --rm --no-deps node npm run lint

lint-fix:
	docker compose run --rm --no-deps php vendor/bin/pint
	docker compose run --rm --no-deps node npm run lint -- --fix

logs:
	docker compose logs -f
