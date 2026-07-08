.PHONY: setup up down restart build sh-php sh-node artisan migrate fresh test logs lint lint-fix install-hooks sync-permissions check-permissions

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

# Sync the code-defined permissions to the DB and regenerate the frontend
# constants from the same App\Enums\Permission enum. The php container can't
# write into frontend/, so the host redirect writes the generated file.
sync-permissions:
	docker compose exec -T php php artisan permission:sync
	docker compose exec -T php php artisan permission:export-ts > frontend/app/constants/permissions.ts
	@echo "→ Synced permissions and regenerated frontend/app/constants/permissions.ts"

# Fail if the generated frontend constants are out of date with the enum.
check-permissions:
	docker compose exec -T php php artisan permission:export-ts | diff -u - frontend/app/constants/permissions.ts

test:
	docker compose exec php php artisan test
	docker compose exec node npm run typecheck
	docker compose exec node npm run test
	$(MAKE) check-permissions

lint:
	docker compose run --rm --no-deps php vendor/bin/pint --test
	docker compose run --rm --no-deps node npm run lint

lint-fix:
	docker compose run --rm --no-deps php vendor/bin/pint
	docker compose run --rm --no-deps node npm run lint -- --fix

logs:
	docker compose logs -f
