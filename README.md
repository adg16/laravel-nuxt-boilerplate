# laravel-nuxt-boilerplate

A reusable, dockerized starting point for internal/backoffice web apps: a Laravel API backend and a Nuxt SPA frontend, authenticated via Laravel Sanctum's cookie-based SPA auth.

## Stack

- **Backend**: Laravel 13 (PHP 8.5), API-only — no Blade views
- **Frontend**: Nuxt 4 (`ssr: false`), Pinia
- **UI components**: Vuetify 4 (Material Design) with MDI icons (`@mdi/font`)
- **Auth**: Laravel Sanctum SPA (stateful cookie session + CSRF)
- **i18n**: `@nuxtjs/i18n` on the frontend + an `Accept-Language` middleware on the backend
- **Database**: MariaDB
- **Cache / session / queue**: Redis (separate logical DBs each)
- **Web server**: nginx — fronts both the API and the SPA on the same origin

nginx routes `/api`, `/sanctum`, and `/up` to PHP-FPM; everything else goes to the Nuxt dev server in development, or a static `nuxi generate` build in production (no Node process in prod).

Vuetify is wired up manually via `vite-plugin-vuetify` and a Nuxt plugin (`frontend/app/plugins/vuetify.ts`) — there's no Tailwind. That plugin is the single place for the theme (brand colors, light/dark) and app-wide component defaults. Forms use `v-form` + `v-text-field` with rules generated from Zod schemas (`frontend/app/utils/validation.ts`).

## Internationalization

Both tiers are localized, and the project ships with English (`en`) only — the wiring is in place so adding a language is a drop-in.

- **Frontend** — `@nuxtjs/i18n` with `strategy: 'no_prefix'` (one URL per page, no `/en` `/fr` segments). The active locale is detected from the browser once and persisted in a cookie. Messages live in `frontend/i18n/locales/*.json`, and **every user-facing string is a translation key** — no hardcoded literals (including Zod validation messages, aria-labels, and page breadcrumbs/subtitles). A language switcher sits in the app bar and appears automatically once a second locale is configured.
- **Backend** — the `SetLocale` middleware reads the `Accept-Language` header (sent by the SPA) and sets the app locale to the best match within `config('app.supported_locales')`, so API responses (validation/auth/password-reset messages) localize too. Laravel's translation files live under `backend/lang/<code>/`.

**To add a locale** (e.g. `fr`):

1. `frontend/i18n/locales/fr.json` — copy `en.json` and translate the values.
2. Add `{ code: 'fr', name: 'Français', language: 'fr-FR', file: 'fr.json' }` to `i18n.locales` in `frontend/nuxt.config.ts`.
3. Add `'fr'` to `supported_locales` in `backend/config/app.php`.
4. `cp -r backend/lang/en backend/lang/fr` and translate the message files.

The switcher then lights up on its own. When adding UI, keep the invariant: **new strings go through `$t()` / `t()` with a key in the locale file** — the `stack-review` skill flags hardcoded literals.

## Repo layout

```
backend/     Laravel API
frontend/    Nuxt SPA
docker/      Dockerfiles and service configs (nginx, php, node, mariadb, redis)
```

## Quickstart

```
git clone <repo> my-new-app
cd my-new-app
cp .env.example .env
make setup   # copies .env files, installs deps, generates key, migrates + seeds
make up
```

The app is served at http://localhost.

## Default user

`make setup` (and `make fresh`) seed a default user via `backend/database/seeders/DatabaseSeeder.php`:

- **Email**: `admin@example.com`
- **Password**: `password`

Override these before deploying anywhere real by setting `DEFAULT_USER_EMAIL` / `DEFAULT_USER_PASSWORD` in `backend/.env`. Re-run seeding any time with `make artisan ARGS=db:seed` (safe to repeat — it won't duplicate the user).

## Common commands

| Command | Description |
|---|---|
| `make up` / `make down` | Start / stop the stack |
| `make sh-php` / `make sh-node` | Shell into the php or node container |
| `make artisan ARGS="migrate:fresh --seed"` | Run an artisan command |
| `make fresh` | Re-migrate and reseed the database |
| `make test` | Run backend tests and frontend typecheck |
| `make lint` | Check code style (Pint) and lint (ESLint) — fails without modifying files |
| `make lint-fix` | Auto-fix style/lint issues |
| `make logs` | Tail all container logs |

## Code style & pre-commit hook

Backend style is enforced with [Laravel Pint](https://laravel.com/docs/pint) (`backend/pint.json`, Laravel preset); frontend linting is ESLint via `@nuxt/eslint`. `make setup` installs a git pre-commit hook (`git config core.hooksPath .githooks`) that runs Pint when staged files touch `backend/*.php` and ESLint when they touch `frontend/*.{vue,ts,js,mjs}`, blocking the commit on failure. If you didn't run `make setup`, install it manually with `make install-hooks`. On failure, run `make lint-fix`, re-stage, and commit again.

## Production

```
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

This builds the Nuxt SPA to static assets and serves them directly from nginx — the base `docker-compose.yml` is prod-leaning by default (no `node` service); `docker-compose.override.yml` (auto-loaded by plain `docker compose up`) is what switches to the Nuxt dev server for local development.
