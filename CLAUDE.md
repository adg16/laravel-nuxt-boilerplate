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

Seed the default user (`super.admin@example.com` / `password`, overridable via `DEFAULT_USER_EMAIL`/`DEFAULT_USER_PASSWORD` in `backend/.env`):
```
docker compose exec php php artisan db:seed --force
```
The seeder also creates a permission-less **System** account (`SYSTEM_USER_EMAIL`, default `system@example.com`) with an unusable random password — it can't log in, is hidden from the user-management list, and is protected from edits/deletion. It exists to attribute app-generated activity (scheduled/automated events) that has no human actor; resolve it in code with `User::system()`. Both the super-admin user and the System account are "protected" (`User::isProtected()`), enforced in `UserController` and reflected in the UI (disabled row actions).

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
Root `docker-compose.yml` is the prod-leaning base (nginx, php, queue, mariadb, redis — 5 services). `docker-compose.override.yml` is auto-loaded by plain `docker compose up` and adds a `node` dev-server service plus a `mailpit` mail catcher, bind-mounts `backend/`/`frontend/` into `php`/`queue`/`node`, and swaps nginx for a plain image serving the Nuxt dev server (it uses YAML `!reset null` on `build:` to fully clear the base service's build config). `docker-compose.prod.yml` only adds `restart: unless-stopped` policies.

### Local email (Mailpit)
Dev mail goes to **Mailpit** (the `mailpit` service in the override), a local SMTP catcher — nothing leaves the machine. `backend/.env` points `MAIL_MAILER=smtp` at `mailpit:1025`; read captured mail in the web inbox at `http://localhost:${MAILPIT_PORT}` (default `8025`). Outbound mail (e.g. user invitations) is queued, so the `queue` worker must be running for messages to arrive. **Gotcha:** `php`/`queue` get `backend/.env` via Compose `env_file`, which injects it as real container env vars at *creation* — and Laravel's Dotenv won't override existing env vars. So after editing `MAIL_*` (or any `backend/.env` value), run `docker compose up -d --force-recreate php queue`; a plain `restart` keeps the stale values. In production, set real SMTP credentials and drop Mailpit.

**Email branding:** the Laravel markdown-mail components are published to `backend/resources/views/vendor/mail/` and themed to the app brand — `themes/default.css` swaps Laravel's zinc palette for the brand orange (`#EA580C`, matching Vuetify's `primary`) on the CTA button, links, and panel accent; `html/header.blade.php` renders the same-origin brand logo (`config('app.url')/apple-touch-icon.png` — a raster PNG for cross-client support, since email clients don't reliably render SVG) inline with the app name. This themes **every** `MailMessage` (invites, password resets) at once, so build new notification emails with `MailMessage` (not bespoke HTML) to inherit it. Editing these Blade/CSS files needs a `php artisan view:clear` (and, because the worker renders queued mail, `docker compose restart queue`) to take effect.

Three separate `.env` layers, don't conflate them: root `.env` (compose-level vars only: ports, DB credentials passed into the mariadb container), `backend/.env` (Laravel), `frontend/.env` (Nuxt — rarely needed since `apiBase` defaults to relative `/api`).

### The core design decision: same-origin nginx
nginx fronts **both** the API and the SPA on one origin. It routes `location ~ ^/(api|sanctum|up)` to PHP-FPM and everything else to the Nuxt dev server (dev, via `docker/nginx/conf.d/app.dev.conf`) or static files (prod, via `app.prod.conf` + `nuxi generate` output baked into the nginx image by `docker/nginx/Dockerfile`). This is why Sanctum's cookie auth works with zero CORS configuration — same-origin, no cross-site cookie issues, no Node process needed in production at all.

Both nginx confs use `resolver 127.0.0.11 valid=10s;` + a `set $var host:port;` before `proxy_pass`/`fastcgi_pass` instead of a bare hostname. This is required, not stylistic: nginx resolves bare upstream hostnames once at config-load time, so if `php` or `node` isn't up yet when nginx starts, it refuses to boot at all. The `resolver` + variable pattern defers resolution to request time.

### Auth flow (Sanctum SPA)
`backend/app/Http/Controllers/Api/AuthController.php` has register/login/me/logout. `bootstrap/app.php` calls `$middleware->statefulApi()` (the Laravel 11+ one-liner for Sanctum's `EnsureFrontendRequestsAreStateful`). Frontend: `frontend/app/stores/auth.ts` (Pinia) drives `getCsrfCookie()` → `login()` → `fetchUser()`, using `frontend/app/composables/useApi.ts` which manually copies the `XSRF-TOKEN` cookie into an `X-XSRF-TOKEN` header (unlike axios, `$fetch` doesn't do this automatically).

**Important, easy to forget**: Sanctum only treats a request as "from the frontend" (and therefore starts a session) if it carries a `Referer` or `Origin` header matching `SANCTUM_STATEFUL_DOMAINS`. Real browsers always send one; raw `curl` does not — add `-H "Referer: http://localhost/"` when testing by hand. Feature tests need the same treatment (see `tests/Feature/Auth/AuthenticationTest.php`'s `setUp()`).

**PHPUnit-specific gotcha**: `AuthManager` caches resolved guards for the life of the container, and Sanctum's `RequestGuard` memoizes the authenticated user permanently once resolved (no per-request reset). Within one PHPUnit test method the container isn't rebuilt between simulated HTTP calls, so a `logout()` call won't "stick" for a later `$this->getJson(...)` call in the same test unless you call `$this->app->forgetInstance('auth')` first. This is a testing-only artifact (real PHP-FPM requests get a fresh container each time) — see the existing logout test for the pattern.

### Authorization (roles & permissions, spatie)
RBAC via `spatie/laravel-permission`. **Permissions are code-defined** — `App\Enums\Permission` is the single source of truth; `php artisan permission:sync` projects the enum into the DB (idempotent, `--prune` removes stragglers), and the management UI only *assigns* permissions to roles, never creates/deletes them. Roles **are** admin-managed (full CRUD). `Gate::before` (in `AppServiceProvider`) grants the `super-admin` role everything; the frontend `useAuthz` mirrors that bypass. The default seeded user is `super-admin`; there's also a permission-less, protected **System** account (`User::system()`, `isProtected()`) and protected-account visibility rules (only a super-admin sees/edits super-admin + System users) — see `UserController`.

**Gating is uniform: `permission:<name>` route middleware in `routes/api.php` is the single place authorization is enforced** (reads → `*.view`, writes → `*.manage`). Don't scatter permission checks into `FormRequest::authorize()` — those are reserved for record/payload-specific rules, and business guardrails (can't delete the last super-admin, protected accounts, etc.) live in the controllers as explicit checks throwing `ValidationException`/`abort()`. Frontend consumes permissions through `useAuthz` (`can`/`canAny`/`hasRole`), the `<Can>` component, nav filtering, and `definePageMeta({ permission })` (enforced by `middleware/auth.global.ts`).

**Adding a permission**: add a case to `App\Enums\Permission`, reference it in route middleware + `<Can>`/`useAuthz`, then run **`make sync-permissions`** — it syncs the DB *and* regenerates `frontend/app/constants/permissions.ts` from the enum (that file is **generated, never hand-edited** — the php container can't write into `frontend/`, so the Make target does it via a host stdout redirect using `php artisan permission:export-ts`). `make check-permissions` (run as part of `make test`) diffs the generated output against the committed file and fails on drift, so the FE/BE lists can't silently diverge.

### Redis: one instance, three logical DBs
Cache, session, and queue all use Redis but on separate logical DBs (`REDIS_CACHE_DB`, `REDIS_SESSION_DB`, `REDIS_QUEUE_DB` — defaults 1/2/3, DB 0 reserved as `default`), configured as named connections in `backend/config/database.php`'s `redis` array. This is deliberate: without it, an eviction policy tuned for cache would silently evict session/queue data. `docker/redis/redis.conf` sets `maxmemory-policy noeviction` + AOF persistence for the same reason (queue jobs must not be silently dropped).

### PHP image
`docker/php/Dockerfile` is multi-stage (`base` → `dev`/`prod`). `dev` installs nothing extra — source is bind-mounted, and `make setup` runs `composer install` inside the running container. `prod` copies `backend/` in and runs `composer install --no-dev` at build time, but config/route caching happens in `docker/php/entrypoint.sh` at **container start**, not build time, so the same image is portable across environments with different env vars. Note: `opcache` is already built into the base `php:8.5-fpm-alpine` image and must NOT be added to the `docker-php-ext-install` line — doing so breaks the build (its JIT/IR build step isn't compatible with being installed as a discrete shared extension on this image).

### Pinned image versions
All base images are pinned to a concrete major (or major.minor for nginx, which has separate stable/mainline branches) rather than floating tags like `latest`/`alpine`/`lts`: `php:8.5-fpm-alpine`, `node:24-alpine`, `nginx:1.30-alpine` (stable branch, not `nginx:alpine` which tracks mainline), `mariadb:12`, `redis:8-alpine`. Keep this convention when bumping versions — pin to a specific major, don't switch back to a floating tag.

### Frontend structure
Nuxt 4's `srcDir` defaults to `frontend/app/` — composables, stores (Pinia), middleware, plugins, layouts, pages, and types all live under `app/`, not at the frontend root (this matters for `@pinia/nuxt`'s default `storesDirs` resolution and the `~` import alias). `middleware/auth.global.ts` is a global route guard; `plugins/auth.client.ts` hydrates the auth store on app init before route resolution.

The `default` layout owns the page content shell: it wraps the routed page in a padded `v-container` and renders the `<AppBreadcrumbs>` header (the page title comes from each page's `definePageMeta({ breadcrumb })` meta — a string, or an array of `{ title, to }` for nested routes). So pages under this layout render **bare body content** (a plain root `<div>`, no `v-container`/page padding, no `<h1>`) — adding your own container double-pads. Page intro rows (a description + right-aligned actions) go through `<AppPageHeader>`. Custom `definePageMeta` keys are typed in `app/types/page-meta.d.ts`.

### Application settings (code-defined keys, DB-editable values)
Runtime settings follow the same "registry in code" idea as permissions. `App\Enums\Setting` is the **single source of truth for which settings exist** — each case carries its metadata (`default()`, `type()` → `SettingType`, `options()`, `group()`, `rules()`). The `settings` table stores only **overrides** (sparse — no seeding, no sync command); `App\Services\Settings` resolves `DB override ?? enum default` and caches the map (invalidated on write). `GET/PUT /api/settings` (gated by `settings.view` / `settings.manage`) let the frontend Settings page edit **values only** — `PUT /settings/{key}` resolves the key via `Setting::tryFrom()` and 404s on anything not in the enum, so keys can't be added/removed from the UI, and the new value is validated with that setting's own `rules()`. To add a setting: add an enum case + its match arms, read it via `app(Settings::class)->get(Setting::TheCase)`, and add i18n labels under `settings.items/options/groups` on the frontend. The first setting, `user_creation_mode` (`choice|invite|set_password`), drives how `UserController::store` creates users (invite email vs. admin-set password → immediately verified); the SPA reads the effective value from `GET /api/config` (a small authenticated, non-permissioned endpoint for UI-shaping flags, separate from the permissioned settings API) so the create-user form adapts without a redeploy.

### UI: Vuetify 4 (no Nuxt UI, no Tailwind)
The component library is **Vuetify 4** (Material Design) with MDI icons (`@mdi/font`). There is deliberately **no `vuetify-nuxt-module`** — it doesn't reliably support Vuetify 4 yet (styling breaks), so Vuetify is wired manually: `nuxt.config.ts` registers `vite-plugin-vuetify` via the `vite:extendConfig` hook (plus `build.transpile: ['vuetify']` and `transformAssetUrls`), and `app/plugins/vuetify.ts` calls `createVuetify`. That plugin is the **single source** for the theme (brand `primary`/`secondary` per light/dark) and app-wide component defaults (e.g. `variant: 'outlined'` + `hideDetails: 'auto'` on the input family) — set colors/defaults there, not with per-component props or raw hex. There's no Tailwind: style with Vuetify utility classes (`d-flex`, `ga-4`, `text-medium-emphasis`) and theme tokens (`color="primary"`, `rgb(var(--v-theme-primary))`), reserving scoped CSS for what utilities can't express. Forms use `v-form` + `v-text-field` with `:rules`; rules come from Zod schemas via the `zodRule` helper (`app/utils/validation.ts`) so messages stay single-sourced, and are validated on submit through a `VForm` ref's `validate()`. Global toasts go through the single `<AppSnackbar>` (in `app.vue`) + `useSnackbar().notify`. The displayed app name is `runtimeConfig.public.appName` (override `NUXT_PUBLIC_APP_NAME`), baked in at build/generate time since this is a static SPA.

**This is Vuetify 4, not 3 — don't copy Vuetify 3 snippets.** Most v3 examples online use utility classes that were renamed in v4, and an unknown utility class **fails silently** (no style applied, no console warning — the element just falls back to the browser default), so these bugs are invisible unless you check the rendered size. The one that bit us: **typography** now uses the Material Design 3 scale `text-{display|headline|title|body|label}-{large|medium|small}` — the v3 names **do not exist**. Map v3 → v4: `text-h4`→`text-headline-medium`, `text-h5`→`text-headline-small`/`text-title-large`, `text-h6`→`text-title-large`, `text-subtitle-1`→`text-title-medium`, `text-body-1`→`text-body-large`, `text-body-2`→`text-body-medium`, `text-caption`→`text-body-small`, `text-overline`→`text-label-small`. Spacing (`pa-*`/`mt-*`, incl. `-n*` negatives), flex (`d-flex`/`ga-*`), text-color/emphasis (`text-primary`, `text-medium-emphasis`), and `text-center`/`text-decoration-none` are unchanged from v3. When unsure whether a class exists, grep the installed CSS: `docker compose exec node grep -r "text-title-large" node_modules/vuetify/lib/styles/`.

### Internationalization (i18n)
Both tiers are localized. **Frontend**: `@nuxtjs/i18n` (module registered in `nuxt.config.ts`) with `strategy: 'no_prefix'` — one URL per page, no `/en`, `/fr` segments (right for an internal SPA). The active locale is detected from the browser once and persisted in the `i18n_locale` cookie. Messages live in `frontend/i18n/locales/*.json` (not under `app/` — the module's `restructureDir` defaults to `i18n/` at the frontend root). Use `$t('key')` in templates and `useI18n()`'s `t` in `<script setup>`; in composables/utilities that may run **outside** a component setup (e.g. `useSubmit`, `useApi`), use `useNuxtApp().$i18n` instead — `useI18n()` throws unless called at the top of a setup. **All user-facing strings are keys, no literals**, including Zod validation messages (`zodRule(z.string().email(t('validation.email')))`), aria-labels, and `definePageMeta({ breadcrumb, subtitle })` — those meta values are i18n **keys** (they must be static for the macro) and are translated in `useBreadcrumbs`. `nav.ts` items carry a `titleKey`, resolved with `$t` where the menu renders. The `<AppLanguageSwitcher>` in the app bar lists the configured locales and only renders once there's more than one (so it stays hidden in the default English-only setup). Vuetify's own component labels aren't wired to vue-i18n (no visible built-in text in the current screens); add the `createVueI18nAdapter` from `vuetify/locale/adapters/vue-i18n` in `plugins/vuetify.ts` if you later need them localized.

**Backend**: `App\Http\Middleware\SetLocale` (appended to the `api` group in `bootstrap/app.php`) reads `Accept-Language` — sent by the SPA's `useApi` as the active locale — and sets the app locale to the best match within `config('app.supported_locales')` (falling back to the first entry), so API responses (`__()` validation/auth/password-reset messages) localize. Laravel's translation files are published under `backend/lang/<code>/`.

**To add a locale** (e.g. `fr`): (1) `frontend/i18n/locales/fr.json` (copy `en.json`, translate); (2) add `{ code: 'fr', name: 'Français', language: 'fr-FR', file: 'fr.json' }` to `i18n.locales` in `nuxt.config.ts`; (3) add `'fr'` to `supported_locales` in `backend/config/app.php`; (4) `cp -r backend/lang/en backend/lang/fr` and translate. The switcher then appears automatically.
