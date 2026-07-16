# laravel-nuxt-boilerplate

A reusable, **dockerized starting point for internal / backoffice web apps**: a Laravel API backend and a Nuxt SPA frontend, authenticated via Laravel Sanctum's cookie-based SPA auth (with Laravel Fortify driving the auth actions headlessly). Clone it, and you start with authentication, role-based access control, user management, an audit trail, and a themed Vuetify UI already built.

> Meant to be **cloned as the start of a new project**, not extended in place — keep additions generic/reusable rather than domain-specific.

## What you get

- **Authentication** — Sanctum cookie SPA session + CSRF, driven headlessly by Fortify. Zero CORS config (same-origin). → [docs](docs/authentication.md)
- **Two-factor auth** — TOTP *and* email codes, with recovery codes; `off`/`optional`/`required` modes an admin toggles at runtime. → [docs](docs/two-factor-authentication.md)
- **Roles & permissions** — RBAC (spatie) with code-defined permissions, admin-managed roles, and a super-admin bypass. → [docs](docs/authorization.md)
- **User management** — CRUD, invite-or-set-password onboarding, activation/deactivation, protected + System accounts, avatars, self-service profile & security pages. → [docs](docs/user-management.md)
- **Application settings** — code-defined keys, admin-editable values, no redeploy. → [docs](docs/settings.md)
- **Activity log** — a read-only audit trail of who changed what, when. → [docs](docs/activity-log.md)
- **Queues** — Laravel Horizon with a Super-Admin-gated `/horizon` dashboard. → [docs](docs/queues.md)
- **Email** — Mailpit catcher in dev, brand-themed mail. → [docs](docs/email.md)
- **File storage** — MinIO (S3-compatible) in dev, real S3 in prod, streamed through an auth'd route. → [docs](docs/storage.md)
- **Internationalization** — localized on both tiers; English-only out of the box, adding a locale is a drop-in. → [docs](docs/internationalization.md)
- **Vuetify 4 UI** — themed, with a shared component kit and Zod-backed form validation. → [docs](docs/frontend.md)

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.5), API-only |
| Frontend | Nuxt 4 SPA (`ssr: false`), Pinia, Vuetify 4 |
| Auth | Sanctum SPA session + CSRF, driven by Fortify |
| Database | MariaDB 12 |
| Cache / session / queue | Redis 8 (three separate logical DBs) + Horizon |
| Web server | nginx — fronts the API **and** the SPA on one origin |
| Dev services | Mailpit (mail), MinIO (S3-compatible storage) |

The **same-origin nginx** is the core design decision — it routes `/api`, `/sanctum`, `/up`, `/horizon` to PHP-FPM and everything else to the Nuxt dev server (dev) or a static `nuxi generate` build (prod, no Node process). That's why Sanctum's cookie auth needs no CORS. → [architecture](docs/architecture.md)

## Quickstart

```
git clone <repo> my-new-app
cd my-new-app
make setup   # copies .env files, installs deps, generates key, migrates + seeds, installs the git hook
make up
```

The app is served at **http://localhost**. (`make setup` also copies `.env` for you — no manual `cp` needed.)

## Default login

`make setup` (and `make fresh`) seed a default **super-admin**:

- **Email**: `super.admin@example.com`
- **Password**: `password`

Override these before deploying anywhere real by setting `DEFAULT_USER_EMAIL` / `DEFAULT_USER_PASSWORD` in `backend/.env`. Re-run seeding any time with `make artisan ARGS=db:seed` (safe to repeat). The seeder also creates a permission-less, non-loginnable **System** account used to attribute automated activity — details in [user-management.md](docs/user-management.md#protected-accounts--visibility-rules).

## Common commands

| Command | Description |
|---|---|
| `make up` / `make down` | Start / stop the stack |
| `make sh-php` / `make sh-node` | Shell into the php or node container |
| `make artisan ARGS="migrate:fresh --seed"` | Run an artisan command |
| `make fresh` | Re-migrate and reseed the database |
| `make test` | Backend tests + frontend typecheck + Vitest + permission-drift check |
| `make lint` / `make lint-fix` | Check-only vs auto-fix (Pint + ESLint) |
| `make logs` | Tail all container logs |

Full workflow, testing notes, and the pre-commit hook → [development.md](docs/development.md).

## Documentation

| Topic | |
|---|---|
| [Architecture](docs/architecture.md) | Same-origin nginx, Docker layers, Redis DBs, images, `.env` layers |
| [Development](docs/development.md) | Commands, testing, linting, pre-commit hook, extending |
| [Authentication](docs/authentication.md) | Sanctum + Fortify flow, password reset, anti-enumeration |
| [Two-factor auth](docs/two-factor-authentication.md) | TOTP + email, modes/methods, enforcement |
| [Authorization](docs/authorization.md) | Roles, permissions, gating, blameable |
| [User management](docs/user-management.md) | CRUD, invitations, protected accounts, avatars, profile |
| [Settings](docs/settings.md) | Code-defined keys, editable values |
| [Activity log](docs/activity-log.md) | Audit trail |
| [Queues](docs/queues.md) | Laravel Horizon |
| [Email](docs/email.md) | Mailpit + branded mail |
| [File storage](docs/storage.md) | MinIO / S3 avatars |
| [Internationalization](docs/internationalization.md) | i18n on both tiers, adding a locale |
| [Frontend](docs/frontend.md) | Nuxt structure, Vuetify, shared components |
| [Deployment](docs/deployment.md) | Production overlay + checklist |

## Repo layout

```
backend/     Laravel API
frontend/    Nuxt SPA
docker/      Dockerfiles and service configs (nginx, php, node, mariadb, redis)
docs/        Feature documentation (see above)
```

## Production

```
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Builds the SPA to static assets served directly by nginx (no `node` service). Read the [production checklist](docs/deployment.md#production-checklist) before deploying — it covers the env vars, seeded-user, mail, storage, and queue steps.

## License

See [LICENSE](LICENSE).
