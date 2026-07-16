# Architecture

How the pieces fit together. The single most important design decision is the **same-origin nginx** — everything else follows from it.

## The stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.5), API-only — no Blade views |
| Frontend | Nuxt 4 SPA (`ssr: false`), Pinia, Vuetify 4 (MDI icons) |
| Auth | Sanctum cookie SPA session + CSRF, driven headlessly by Laravel Fortify |
| Database | MariaDB 12 |
| Cache / session / queue | Redis 8 (three separate logical DBs) |
| Queue supervisor | Laravel Horizon |
| Web server | nginx (fronts API **and** SPA on one origin) |
| Dev mail | Mailpit (SMTP catcher) |
| Dev object storage | MinIO (S3-compatible) |

## Same-origin nginx (the core decision)

nginx fronts **both** the API and the SPA on a single origin. It routes `location ~ ^/(api|sanctum|up|horizon)` to PHP-FPM and everything else to the Nuxt dev server (development) or the static `nuxi generate` build (production).

This is why **Sanctum's cookie auth works with zero CORS configuration** — the browser only ever talks to one origin, so there are no cross-site cookie issues, no CORS preflights, and no Node process in production at all.

Both nginx confs use `resolver 127.0.0.11 valid=10s;` + a `set $var host:port;` before `proxy_pass`/`fastcgi_pass` rather than a bare hostname. This is required, not stylistic: nginx resolves bare upstream hostnames once at config-load time, so if `php`/`node` isn't up yet when nginx boots it would refuse to start. Deferring resolution to request time fixes that.

## Docker Compose layers

Three compose files, composed by convention:

- **`docker-compose.yml`** — the prod-leaning base: `nginx`, `php`, `queue`, `mariadb`, `redis` (5 services).
- **`docker-compose.override.yml`** — auto-loaded by plain `docker compose up`. Adds the dev-only `node` dev server, `mailpit`, and `minio` (+ a one-shot `createbuckets`); bind-mounts `backend/`/`frontend/` into the containers; swaps nginx for a plain image that proxies the Nuxt dev server.
- **`docker-compose.prod.yml`** — adds `restart: unless-stopped` policies.

So `docker compose up` is development; `docker compose -f docker-compose.yml -f docker-compose.prod.yml up` is production. See [deployment.md](deployment.md).

## Redis: one instance, three logical DBs

Cache, session, and queue all use Redis but on **separate logical DBs** (`REDIS_CACHE_DB`/`REDIS_SESSION_DB`/`REDIS_QUEUE_DB`, defaults 1/2/3; DB 0 is reserved as `default` and holds Horizon's own metrics). Configured as named connections in `backend/config/database.php`.

This is deliberate: without it, an eviction policy tuned for cache would silently evict session or queue data. `docker/redis/redis.conf` sets `maxmemory-policy noeviction` + AOF persistence for the same reason — queued jobs must never be silently dropped.

## PHP image

`docker/php/Dockerfile` is multi-stage (`base` → `dev`/`prod`):

- **`dev`** installs nothing extra — source is bind-mounted and `make setup` runs `composer install` inside the running container.
- **`prod`** copies `backend/` in and runs `composer install --no-dev` at build time. Config/route caching happens in `docker/php/entrypoint.sh` at **container start**, not build time, so the same image is portable across environments with different env vars.

> `opcache` is already built into `php:8.5-fpm-alpine` — do **not** add it to `docker-php-ext-install` (it breaks the build).

## Pinned image versions

All base images are pinned to a concrete major (or major.minor for nginx) rather than floating tags: `php:8.5-fpm-alpine`, `node:24-alpine`, `nginx:1.30-alpine` (stable branch), `mariadb:12`, `redis:8-alpine`. Keep this convention when bumping — pin to a specific major, don't switch back to `latest`/`alpine`.

## The three `.env` layers

Don't conflate these:

| File | Read by | Holds |
|---|---|---|
| root `.env` | Docker Compose | Compose-level vars only: ports, DB credentials passed into the mariadb container |
| `backend/.env` | Laravel | Everything Laravel |
| `frontend/.env` | Nuxt | Rarely needed — `apiBase` defaults to the relative `/api` |

> **`.env` force-recreate gotcha:** Compose injects `backend/.env` into `php`/`queue` as real container env vars at **creation** time, and Laravel's Dotenv won't override existing env vars. So after editing any `backend/.env` value, run `docker compose up -d --force-recreate php queue` — a plain `restart` keeps the stale values.
