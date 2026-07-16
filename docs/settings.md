# Application settings

Runtime settings with a **registry defined in code, values editable in the UI** — the same pattern as [permissions](authorization.md). Admins edit values on the `/settings` page (gated by `settings.view` / `settings.manage`).

## How it works

`App\Enums\Setting` is the **single source of truth for which settings exist**. Each case carries its metadata via a `SettingDefinition` (`type()`, `default()`, `options()`, `group()`, `rules()`).

The `settings` table stores only **overrides** — it's sparse: no seeding, no sync command. `App\Services\Settings` resolves `DB override ?? enum default` and caches the map (invalidated on write).

- `GET /api/settings` returns the resolved values (grouped).
- `PUT /api/settings/{key}` resolves the key via `Setting::tryFrom()` and **404s on anything not in the enum** — so keys can't be added/removed from the UI, only their values changed. The new value is validated with that setting's own `rules()`.

A separate `GET /api/config` endpoint (authenticated, non-permissioned) exposes a small set of UI-shaping flags derived from settings, so the SPA can adapt forms without a redeploy.

## The shipped settings

| Key | Group | Values | Effect |
|---|---|---|---|
| `user_creation_mode` | users | `choice` \| `invite` \| `set_password` | How `UserController::store` creates users — invite email vs. admin-set password (see [user-management.md](user-management.md#invitations)) |
| `two_factor_mode` | authentication | `off` \| `optional` \| `required` | Whether [2FA](two-factor-authentication.md) applies |
| `two_factor_methods` | authentication | `totp` \| `email` \| `both` | Which second factors users may enroll |

## Adding a setting

1. Add a case to `App\Enums\Setting` + a single arm to `definition()` (the `SettingType` decides validation/casting).
2. Read it via `app(Settings::class)->get(Setting::TheCase)`.
3. Add i18n labels under `settings.items` / `settings.options` / `settings.groups` on the frontend.

If the SPA needs the value to shape the UI before a permissioned settings fetch, also surface it through `GET /api/config`.
