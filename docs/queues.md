# Queue processing (Laravel Horizon)

The `queue` container runs **[Laravel Horizon](https://laravel.com/docs/horizon)** (`php artisan horizon`) rather than a bare `queue:work`. Worker settings (tries, timeout, balance, processes) are **code-defined in `config/horizon.php`** (the `defaults`/`environments` supervisors), not CLI flags.

Horizon supervises the `redis` queue connection (→ the `queue` Redis connection, [DB 3](architecture.md#redis-one-instance-three-logical-dbs), queue name `default`) and stores its own metrics/state on the `default` connection (DB 0), keeping it off the queue DB.

## The worker must be running for queued mail

Several features send **queued** notifications — they won't arrive unless the `queue` worker is up (it is, by default):

- invitation emails ([user-management.md](user-management.md#invitations))
- password-reset emails ([authentication.md](authentication.md))
- 2FA email codes ([two-factor-authentication.md](two-factor-authentication.md))

## Dashboard at `/horizon`

Horizon inlines its CSS/JS into the page HTML, so there's no separate asset path — the same-origin nginx just adds `horizon` to the PHP-FPM `location` regex.

Access is gated by the `viewHorizon` gate in `App\Providers\HorizonServiceProvider` — **Super Admin only** — authorized via the same-origin Sanctum `web` session cookie. (Horizon also opens the dashboard unconditionally when `APP_ENV=local`, its built-in behavior, so it's open in dev and locked down in prod.) A sidebar link to it appears for super-admins.

## Dev gotcha

Like `queue:work`, Horizon workers hold the code they booted with. After editing job code, reload them:

```
docker compose exec php php artisan horizon:terminate
```

(or recreate the `queue` container). Config defaults need no `HORIZON_*` env vars; in production consider `HORIZON_PREFIX` if multiple apps share one Redis.
