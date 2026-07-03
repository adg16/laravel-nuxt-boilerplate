# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A reusable, dockerized boilerplate for internal/backoffice web apps: Laravel 13 (PHP 8.5) API backend + Nuxt 4 SPA frontend (`ssr: false`), authenticated via Laravel Sanctum's cookie-based SPA auth. Meant to be cloned as the starting point for new projects, not extended in place — keep additions generic/reusable rather than domain-specific.

## Commands

All commands run through Docker; there is no local PHP/Node toolchain expected on the host.

```
make setup                              # first-time bootstrap: .env copies, composer/npm install, key:generate, migrate --seed
make up / make down                     # start / stop the stack (docker compose up/down)
make sh-php / make sh-node              # shell into the php or node container
make artisan ARGS="migrate:fresh --seed" # run any artisan command
make fresh                              # migrate:fresh --seed
make test                               # backend test suite + frontend typecheck + vitest
make lint / make lint-fix               # Pint (backend) + ESLint (frontend) — check-only vs auto-fix
make logs                               # tail all container logs
```

Backend (PHPUnit, not Pest — `php artisan test`, run via `docker compose exec php`):
```
docker compose exec php php artisan test                                    # full suite
docker compose exec php php artisan test --filter=test_user_can_login       # single test
docker compose exec php php artisan test tests/Feature/Auth/AuthenticationTest.php
```

Frontend (run via `docker compose exec node`):
```
docker compose exec node npm run lint        # eslint
docker compose exec node npm run typecheck    # nuxt typecheck (vue-tsc)
docker compose exec node npm run test         # vitest (component/composable tests)
```
Frontend tests use Vitest + `@nuxt/test-utils` in the Nuxt runtime environment (`vitest.config.ts` sets `environment: 'nuxt'`); specs live in `frontend/test/*.nuxt.test.ts`. There's no browser E2E — these are component/composable tests (mount via `mountSuspended`, mock auto-imports via `mockNuxtImport`). `make test` runs the backend suite, then frontend typecheck, then Vitest.

`make setup` installs a git pre-commit hook (`git config core.hooksPath .githooks`; script at `.githooks/pre-commit`) that runs Pint (`--test`, check-only) when staged files touch `backend/*.php` and ESLint when they touch `frontend/*.{vue,ts,js,mjs}`, blocking the commit on failure — both invoked via `docker compose run --rm --no-deps`, so they work whether or not the stack is currently up. If editing this hook, note it lints the whole project rather than just staged files (simpler than wiring per-file lint-staged-style scoping, acceptable at this repo's size).

Seed the default user (`admin@example.com` / `password`, overridable via `DEFAULT_USER_EMAIL`/`DEFAULT_USER_PASSWORD` in `backend/.env`):
```
docker compose exec php php artisan db:seed --force
```

Production overlay (builds the Nuxt static SPA, no `node` container):
```
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Architecture

### Repo layout
```
backend/     Laravel API (API-only — no Blade views, no Breeze/Jetstream)
frontend/    Nuxt 4 SPA
docker/      Dockerfiles + per-service configs (nginx, php, node, mariadb, redis)
```
Root `docker-compose.yml` is the prod-leaning base (nginx, php, queue, mariadb, redis — 5 services). `docker-compose.override.yml` is auto-loaded by plain `docker compose up` and adds a `node` dev-server service, bind-mounts `backend/`/`frontend/` into `php`/`queue`/`node`, and swaps nginx for a plain image serving the Nuxt dev server (it uses YAML `!reset null` on `build:` to fully clear the base service's build config). `docker-compose.prod.yml` only adds `restart: unless-stopped` policies.

Three separate `.env` layers, don't conflate them: root `.env` (compose-level vars only: ports, DB credentials passed into the mariadb container), `backend/.env` (Laravel), `frontend/.env` (Nuxt — rarely needed since `apiBase` defaults to relative `/api`).

### The core design decision: same-origin nginx
nginx fronts **both** the API and the SPA on one origin. It routes `location ~ ^/(api|sanctum|up)` to PHP-FPM and everything else to the Nuxt dev server (dev, via `docker/nginx/conf.d/app.dev.conf`) or static files (prod, via `app.prod.conf` + `nuxi generate` output baked into the nginx image by `docker/nginx/Dockerfile`). This is why Sanctum's cookie auth works with zero CORS configuration — same-origin, no cross-site cookie issues, no Node process needed in production at all.

Both nginx confs use `resolver 127.0.0.11 valid=10s;` + a `set $var host:port;` before `proxy_pass`/`fastcgi_pass` instead of a bare hostname. This is required, not stylistic: nginx resolves bare upstream hostnames once at config-load time, so if `php` or `node` isn't up yet when nginx starts, it refuses to boot at all. The `resolver` + variable pattern defers resolution to request time.

### Auth flow (Sanctum SPA)
`backend/app/Http/Controllers/Api/AuthController.php` has register/login/me/logout. `bootstrap/app.php` calls `$middleware->statefulApi()` (the Laravel 11+ one-liner for Sanctum's `EnsureFrontendRequestsAreStateful`). Frontend: `frontend/app/stores/auth.ts` (Pinia) drives `getCsrfCookie()` → `login()` → `fetchUser()`, using `frontend/app/composables/useApi.ts` which manually copies the `XSRF-TOKEN` cookie into an `X-XSRF-TOKEN` header (unlike axios, `$fetch` doesn't do this automatically).

**Important, easy to forget**: Sanctum only treats a request as "from the frontend" (and therefore starts a session) if it carries a `Referer` or `Origin` header matching `SANCTUM_STATEFUL_DOMAINS`. Real browsers always send one; raw `curl` does not — add `-H "Referer: http://localhost/"` when testing by hand. Feature tests need the same treatment (see `tests/Feature/Auth/AuthenticationTest.php`'s `setUp()`).

**PHPUnit-specific gotcha**: `AuthManager` caches resolved guards for the life of the container, and Sanctum's `RequestGuard` memoizes the authenticated user permanently once resolved (no per-request reset). Within one PHPUnit test method the container isn't rebuilt between simulated HTTP calls, so a `logout()` call won't "stick" for a later `$this->getJson(...)` call in the same test unless you call `$this->app->forgetInstance('auth')` first. This is a testing-only artifact (real PHP-FPM requests get a fresh container each time) — see the existing logout test for the pattern.

### Redis: one instance, three logical DBs
Cache, session, and queue all use Redis but on separate logical DBs (`REDIS_CACHE_DB`, `REDIS_SESSION_DB`, `REDIS_QUEUE_DB` — defaults 1/2/3, DB 0 reserved as `default`), configured as named connections in `backend/config/database.php`'s `redis` array. This is deliberate: without it, an eviction policy tuned for cache would silently evict session/queue data. `docker/redis/redis.conf` sets `maxmemory-policy noeviction` + AOF persistence for the same reason (queue jobs must not be silently dropped).

### PHP image
`docker/php/Dockerfile` is multi-stage (`base` → `dev`/`prod`). `dev` installs nothing extra — source is bind-mounted, and `make setup` runs `composer install` inside the running container. `prod` copies `backend/` in and runs `composer install --no-dev` at build time, but config/route caching happens in `docker/php/entrypoint.sh` at **container start**, not build time, so the same image is portable across environments with different env vars. Note: `opcache` is already built into the base `php:8.5-fpm-alpine` image and must NOT be added to the `docker-php-ext-install` line — doing so breaks the build (its JIT/IR build step isn't compatible with being installed as a discrete shared extension on this image).

### Pinned image versions
All base images are pinned to a concrete major (or major.minor for nginx, which has separate stable/mainline branches) rather than floating tags like `latest`/`alpine`/`lts`: `php:8.5-fpm-alpine`, `node:24-alpine`, `nginx:1.30-alpine` (stable branch, not `nginx:alpine` which tracks mainline), `mariadb:12`, `redis:8-alpine`. Keep this convention when bumping versions — pin to a specific major, don't switch back to a floating tag.

### Frontend structure
Nuxt 4's `srcDir` defaults to `frontend/app/` — composables, stores (Pinia), middleware, plugins, layouts, pages, and types all live under `app/`, not at the frontend root (this matters for `@pinia/nuxt`'s default `storesDirs` resolution and the `~` import alias). `middleware/auth.global.ts` is a global route guard; `plugins/auth.client.ts` hydrates the auth store on app init before route resolution.
