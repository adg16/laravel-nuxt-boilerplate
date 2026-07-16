# Deployment

## Production overlay

```
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

This builds the Nuxt SPA to **static assets** (`nuxi generate`, baked into the nginx image) and serves them directly from nginx — **there is no `node` process in production**. The base `docker-compose.yml` is prod-leaning by default; the dev-only `node`/`mailpit`/`minio` services come from `docker-compose.override.yml`, which is *not* loaded here. `docker-compose.prod.yml` adds `restart: unless-stopped`.

The `prod` PHP image runs `composer install --no-dev` at build time, but config/route caching happens at **container start** (`docker/php/entrypoint.sh`), so one image is portable across environments with different env vars. See [architecture.md](architecture.md#php-image).

## Production checklist

Before pointing this at anything real, in `backend/.env`:

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`, and the real `APP_URL`.
- [ ] Change the seeded super-admin — set `DEFAULT_USER_EMAIL` / `DEFAULT_USER_PASSWORD` (and `DEFAULT_USER_NAME`) **before** the first seed. See [the README](../README.md#default-login).
- [ ] Set `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` to your real domain.
- [ ] **Email** ([email.md](email.md)) — real SMTP credentials; Mailpit is dev-only.
- [ ] **Storage** ([storage.md](storage.md)) — keep `FILESYSTEM_DISK=s3`, swap in real AWS credentials, **drop** `AWS_ENDPOINT`, set `AWS_USE_PATH_STYLE_ENDPOINT=false`.
- [ ] **Queue** ([queues.md](queues.md)) — the `queue` (Horizon) service must run for mail/notifications; consider `HORIZON_PREFIX` if multiple apps share one Redis.
- [ ] Strong DB credentials in the root `.env` (they're passed into the mariadb container).
- [ ] Run migrations + the first seed (`make setup` does this; or `php artisan migrate --seed` in the running `php` container).

> After changing any `backend/.env` value on a running stack, recreate the affected services (`docker compose ... up -d --force-recreate php queue`) — a plain restart keeps stale env values. See [the .env gotcha](architecture.md#the-three-env-layers).

## Health check

`GET /up` (Laravel's built-in) and `GET /api/health` are routed to PHP and usable as container/load-balancer health probes.
