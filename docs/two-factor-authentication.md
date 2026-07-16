# Two-factor authentication

Two second factors ship out of the box — **TOTP** (authenticator apps) and **email codes** — with recovery codes for both. Whether 2FA applies, and which factors users may enroll, are **runtime settings** an admin controls from the [Settings](settings.md) page (never `config/fortify.php`, which prod `config:cache`s).

## Two settings drive policy

Both are `Select`s in the `authentication` settings group:

| Setting | Values | Meaning |
|---|---|---|
| `two_factor_mode` | `off` \| `optional` \| `required` | Whether 2FA applies at all |
| `two_factor_methods` | `totp` \| `email` \| `both` | Which factors users may enroll |

Resolve in code via `app(Settings::class)->twoFactorMode()` / `->twoFactorMethodPolicy()`. Enums: `App\Enums\TwoFactorMode`, `TwoFactorMethodPolicy`, and `TwoFactorMethod` (the per-user column).

## Enforcement (method-layer, not config)

Fortify's TOTP feature is enabled *unconditionally* (routes always registered); the gating lives in `App\Actions\Fortify\` subclasses (bound in `FortifyServiceProvider`):

- `RedirectIfTwoFactorAuthenticatable` — skips the challenge when mode is `off`, and **diverts email users to an emailed-code challenge** (Fortify only challenges users with a `two_factor_secret`, which email users lack).
- `EnableTwoFactorAuthentication` — refuses TOTP enrollment unless the policy permits it; records `two_factor_method = 'totp'`.
- `DisableTwoFactorAuthentication` — blocks self-disable under `required` mode; clears the method column.

`App\Http\Middleware\EnsureTwoFactorEnrolled` wraps the **management** routes (never `/user`·`/config`·the 2FA endpoints — those must stay reachable so a user can enroll). Under `required` mode it 403s an un-enrolled user (`code: two_factor_setup_required`) until they set up 2FA. The SPA mirrors this in `middleware/auth.global.ts` (redirect to `/security`).

## Email codes

`App\Services\TwoFactorEmailCode` issues/verifies 6-digit codes — hashed in the **cache**, 10-min TTL, 5-attempt cap, keyed per user+purpose — delivered via the queued, brand-themed `TwoFactorCodeNotification` (**[queue worker](queues.md) must run**). Enrollment: `TwoFactorEmailController` (`/api/user/two-factor-email*`); login challenge: `TwoFactorEmailChallengeController` (`/api/two-factor-email-challenge*`, guest + a dedicated `two-factor-email` rate limiter).

## Key APIs

- **Unified "enabled" check:** use `User::hasTwoFactorEnabled()` (confirmed *either* method) everywhere — **not** Fortify's TOTP-only `hasEnabledTwoFactorAuthentication()`.
- `UserResource` exposes `two_factor_enabled` + `two_factor_method`; `GET /api/config` exposes `twoFactorMode` + `twoFactorMethods`.
- Recovery codes (both methods) come from `TwoFactorRecoveryController` (`/api/user/two-factor/recovery-codes`).

## The UI

- Self-service **`/security`** page (alongside `/profile`, both reachable from the app-bar account menu) + a challenge step in `pages/login.vue`.
- Admins reset a locked-out user's 2FA (either method) from the user list — `UserController::resetTwoFactor` (`DELETE /api/users/{user}/two-factor`, `users.manage`).

## Adding another factor (e.g. SMS)

Add a `TwoFactorMethod` case, a login-pipeline diversion + enroll/challenge controllers mirroring the email ones, and extend the policy — don't fold it into Fortify's TOTP path.
