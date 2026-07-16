# User management

Admin-facing account management (`/users`), plus self-service profile/security for every user. All admin endpoints are gated by `users.view` / `users.manage` (see [authorization.md](authorization.md)).

## What's included

- **User CRUD** — list, view, create, edit, delete (`UserController`).
- **Two creation modes** — invite by email, or admin-set password (driven by a [setting](settings.md#the-shipped-settings)).
- **Activation / deactivation** — disable an account without deleting it.
- **2FA reset** — clear a locked-out user's second factor.
- **Role assignment** — grant/revoke roles (with super-admin guardrails).
- **Avatars** — self-service upload, streamed back through an authenticated route.
- **Profile & security** self-service pages.

## Protected accounts & visibility rules

The boilerplate ships two special accounts, both created by `DatabaseSeeder`:

- **The seeded super-admin** — the default login (see [the README](../README.md#default-login)). Has the `Super Admin` role.
- **The System account** (`SYSTEM_USER_EMAIL`, default `system@example.com`) — a **permission-less** account with an unusable random password. It **can't log in** and is hidden from the user list. It exists to attribute app-generated activity (scheduled/automated events) that has no human actor; resolve it in code with `User::system()`.

Both are **protected** (`User::isProtected()` — a durable `is_protected` column, *not* derived from the mutable email, so changing the super-admin's email can't strip its protection). Protected accounts are **locked from edits/deletion for everyone, even other super-admins** — enforced in `UserController` (`guardProtected`) and reflected in the UI (disabled row actions).

Layered rules (all in `UserController`):

- **Assigned super-admins aren't protected** — a super-admin can manage a user who merely *has* the `Super Admin` role, but a non-super-admin can't (`guardSuperAdminManagement`, 422) and can't see them at all.
- **Super-admin + System accounts are hidden from non-super-admins** on the list, `show`, and the avatar route (`User::isRestrictedToSuperAdmins()`).
- **Only a super-admin can grant the `Super Admin` role** (`guardSuperAdminAssignment`).
- **Both built-in names are reserved** (case-insensitive, via `App\Rules\NotReservedName`) — no other account can impersonate them. The original super-admin also can't rename themselves on `/profile`.

## Activation / deactivation

Users carry a nullable `deactivated_at` (`User::isActive()`/`deactivate()`/`activate()`). Admins toggle it via `POST /api/users/{user}/{activate,deactivate}` (`users.manage`, same self- and protected-account guards as delete). Enforced in two places:

- `FortifyServiceProvider::authenticateUsing` refuses login (401 `auth.account_deactivated`) — checked *after* the password verifies, so it doesn't leak account state.
- `App\Http\Middleware\EnsureActive` wraps the **whole** authenticated surface (including `/user`·`/logout`), 403ing a deactivated user's live session so deactivation takes effect on their **very next request**.

## Invitations

New users can be onboarded by email invite instead of an admin-set password. The flow is a bespoke controller (not Fortify): `InvitationController` + `POST /api/accept-invitation` (guest, 6/min-per-IP throttle to blunt token guessing). The invite email is **queued** (**[worker](queues.md) must run**) and brand-themed. Admins can re-send via `POST /api/users/{user}/resend-invite`. The SPA acceptance page is `/accept-invite`.

Which mode `UserController::store` uses is driven by the `user_creation_mode` [setting](settings.md) (`choice` | `invite` | `set_password`); the create-user form reads the effective value from `GET /api/config` and adapts without a redeploy.

## Profile & security (self-service)

Every signed-in user gets two pages, reached from the app-bar account menu:

- **`/profile`** — update own name, email, password, and avatar. Backed by Fortify's `updateProfileInformation` / `updatePasswords` (`App\Actions\Fortify\UpdateUserProfileInformation` / `UpdateUserPassword`) + `AvatarController`. Password change requires `current_password` and rotates `remember_token`.
- **`/security`** — manage two-factor authentication ([two-factor-authentication.md](two-factor-authentication.md)).

### Avatars

Uploaded images live on the app's default filesystem disk (MinIO/S3 in dev/prod, `local` in tests — see [storage.md](storage.md)) and are **streamed back through an authenticated API route** (`GET /api/users/{user}/avatar`), never a public URL — so the browser never touches the object store (no CORS, no public bucket). `POST`/`DELETE /api/user/avatar` are self-service. `UserResource::avatar_url` is a same-origin path with a `?v=<hash>` cache-buster. No server-side resizing — uploads are capped by validation (`image`, `jpeg/png/webp`, `max:2048`KB). Rendered everywhere via the shared `<AppUserAvatar>` (image when set, else initials).
