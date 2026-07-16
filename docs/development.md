# Development

Day-to-day workflow. All commands run through Docker — there's no local PHP/Node toolchain expected on the host.

## Commands

| Command | Description |
|---|---|
| `make setup` | First-time bootstrap: `.env` copies, composer/npm install, `key:generate`, `migrate --seed`, install git hook |
| `make up` / `make down` | Start / stop the stack |
| `make restart` | Restart the stack |
| `make sh-php` / `make sh-node` | Shell into the php or node container |
| `make artisan ARGS="migrate:fresh --seed"` | Run any artisan command |
| `make migrate` | Run pending migrations |
| `make fresh` | `migrate:fresh --seed` (rebuild + reseed the DB) |
| `make sync-permissions` | Sync code-defined permissions to the DB **and** regenerate the frontend constants |
| `make check-permissions` | Fail if the generated frontend permission constants drift from the enum |
| `make test` | Backend suite + frontend typecheck + Vitest + permission-drift check |
| `make lint` / `make lint-fix` | Check-only vs auto-fix (Pint + ESLint) |
| `make logs` | Tail all container logs |

## Testing

`make test` runs, in order: the backend PHPUnit suite, the frontend typecheck (`vue-tsc`), Vitest, and the permission-drift check.

**Backend** (PHPUnit, not Pest):

```
docker compose exec php php artisan test                                # full suite
docker compose exec php php artisan test --filter=test_user_can_login   # single test
docker compose exec php php artisan test tests/Feature/Auth/AuthenticationTest.php
```

Tests force `FILESYSTEM_DISK=local` (`phpunit.xml`) and use `Storage::fake()`, so they never need MinIO or the S3 driver.

> **Sanctum test gotcha:** Sanctum only treats a request as "from the frontend" if it carries a `Referer`/`Origin` header matching `SANCTUM_STATEFUL_DOMAINS`. Feature tests must set this (see `tests/Feature/Auth/AuthenticationTest.php`'s `setUp()`). Also, within one test method, a `logout()` won't "stick" for a later request unless you call `$this->app->forgetInstance('auth')` first (a PHPUnit-only container-caching artifact).

**Frontend** (Vitest + `@nuxt/test-utils` in the Nuxt runtime):

```
docker compose exec node npm run lint       # eslint
docker compose exec node npm run typecheck   # nuxt typecheck (vue-tsc)
docker compose exec node npm run test        # vitest
```

Specs live in `frontend/test/*.nuxt.test.ts` — component/composable tests (`mountSuspended`, `mockNuxtImport`). There's no browser E2E.

## Code style & the pre-commit hook

Backend style is [Laravel Pint](https://laravel.com/docs/pint) (Laravel preset); frontend is ESLint via `@nuxt/eslint`.

`make setup` installs a git pre-commit hook (`git config core.hooksPath .githooks`) that runs Pint when staged files touch `backend/*.php` and ESLint when they touch `frontend/*.{vue,ts,js,mjs}`, blocking the commit on failure. Both run via `docker compose run --rm --no-deps`, so they work whether or not the stack is up. If you skipped `make setup`, install it with `make install-hooks`. On failure: `make lint-fix`, re-stage, commit again.

## Extending the boilerplate — quick pointers

These follow a "registry defined in code" pattern so the two tiers can't drift:

- **Add a permission** → [authorization.md](authorization.md) (`App\Enums\Permission` + `make sync-permissions`)
- **Add a setting** → [settings.md](settings.md) (`App\Enums\Setting`)
- **Add a locale** → [internationalization.md](internationalization.md)
- **Add an auth capability** → [authentication.md](authentication.md)

> Keep additions **generic and reusable** — this repo is meant to be cloned as the start of a new project, not extended in place with domain-specific code.
