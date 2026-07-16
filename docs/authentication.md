# Authentication

Cookie-based SPA authentication via **Laravel Sanctum**, with **Laravel Fortify** driving the auth actions headlessly (no Blade views). Because the SPA and API are [same-origin](architecture.md#same-origin-nginx-the-core-decision), this needs **zero CORS configuration**.

## What's included

- Email + password **login / logout**
- **Forgot-password / reset-password** (queued, brand-themed email, anti-enumeration)
- **Self-service profile** — update own name, email, password ([user-management.md](user-management.md#profile--security-self-service))
- **Two-factor authentication** (TOTP + email) — see [two-factor-authentication.md](two-factor-authentication.md)
- **Invitation-based onboarding** — see [user-management.md](user-management.md#invitations)

Public self-**registration** and email-verification are intentionally **off** — this is an internal backoffice tool, so accounts are created by admins or via invitation, never self-signup.

## How the flow works

`bootstrap/app.php` calls `$middleware->statefulApi()` (Sanctum's `EnsureFrontendRequestsAreStateful`). The SPA's login sequence (`frontend/app/stores/auth.ts`, Pinia):

1. `getCsrfCookie()` → hits `/sanctum/csrf-cookie` to set the `XSRF-TOKEN` cookie.
2. `login()` → `POST /api/login` with credentials.
3. `fetchUser()` → `GET /api/user` hydrates the store.

`frontend/app/composables/useApi.ts` manually copies the `XSRF-TOKEN` cookie into the `X-XSRF-TOKEN` header on each request (unlike axios, `$fetch` doesn't do this automatically).

## Fortify wiring

Fortify runs headless (`config/fortify.php` → `views => false`). Read `config/fortify.php` + `App\Providers\FortifyServiceProvider` together before touching auth:

- **Routes land under `/api/*`** (`'prefix' => 'api'`) — required, because the same-origin nginx only routes `/api|sanctum|up|horizon` to PHP, and the SPA owns `/login`, `/reset-password`, etc. as its own pages. Fortify's middleware is `['web', SetLocale::class, 'throttle:6,1']`: `web` supplies the session/CSRF stack; `SetLocale` re-adds i18n; the throttle is 6/min per route+IP.
- **Enabled features**: `resetPasswords`, `updateProfileInformation`, `updatePasswords`, `twoFactorAuthentication` (login/logout are always on).
- **Headless JSON responses** live in `app/Http/Responses/` and are bound in the provider: `LoginResponse` returns the `UserResource`; `LogoutResponse` returns a 200 `{message}`.
- **Anti-enumeration:** `PasswordResetLinkResponse` is bound to *both* the success and failure forgot-password contracts, so an unknown email returns the **same** generic 200 message as a known one.
- **401 on bad credentials** (not Fortify's default 422): `authenticateUsing` throws `AuthenticationException` to preserve the contract.
- **Password-reset mail is queued** (`App\Notifications\QueuedResetPassword`), so a transport failure surfaces in the worker rather than as a request 500 (which would also leak account existence). The **[queue worker](queues.md) must be running** for reset mail to send.

## Adding an auth capability

To add e.g. email verification: enable the Fortify feature in `config/fortify.php`, add/adjust the matching Action + Response binding, and build the SPA screens — **don't hand-roll a new controller**. Anything genuinely bespoke (like the invitation flow) stays a normal controller.

## Gotcha: testing by hand

Sanctum only starts a session for requests carrying a `Referer`/`Origin` matching `SANCTUM_STATEFUL_DOMAINS`. Real browsers always send one; raw `curl` does not — add `-H "Referer: http://localhost/"` when testing manually. (Feature tests need the same — see [development.md](development.md#testing).)
