# Authorization (roles & permissions)

Role-based access control via [`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission), with a strict split: **permissions are defined in code**, **roles are managed in the UI**.

## Permissions are code-defined

`App\Enums\Permission` is the single source of truth. The management UI only *assigns* permissions to roles — it never creates or deletes them.

The shipped permissions:

| Permission | Guards |
|---|---|
| `users.view` / `users.manage` | User list/detail vs. create/edit/delete/activate/invite |
| `roles.view` / `roles.manage` | Role list/detail (+ the permission catalog) vs. role CRUD |
| `settings.view` / `settings.manage` | Read vs. write application settings |
| `activity.view` | Read the audit trail (no `manage` — the log is never mutated from the UI) |

`php artisan permission:sync` projects the enum into the DB (idempotent; `--prune` removes stragglers). The frontend `app/constants/permissions.ts` is **generated** from the same enum (never hand-edited).

## Roles

Roles are fully **admin-managed** (CRUD) via the `/roles` pages. The seeder ships three baseline roles — `Super Admin`, `Admin`, `Viewer` (the role name *is* the display name; there's no separate label).

**`Super Admin` bypasses every permission check.** `Gate::before` (in `AppServiceProvider`) grants it everything; the frontend `useAuthz` mirrors that bypass. Only a super-admin can grant the `Super Admin` role (see [user-management.md](user-management.md) for the full protected-account rules).

## Gating is uniform

**`permission:<name>` route middleware in `routes/api.php` is the single place authorization is enforced** — reads → `*.view`, writes → `*.manage`. This file is the one place to audit "who can call what."

- Don't scatter permission checks into `FormRequest::authorize()` — those are reserved for record/payload-specific rules.
- Business guardrails (can't delete the last super-admin, protected accounts, etc.) live in the **controllers** as explicit checks throwing `ValidationException`/`abort()`.

Frontend consumes permissions through `useAuthz` (`can` / `canAny` / `hasRole`), the `<Can>` component, nav filtering, and `definePageMeta({ permission })` (enforced by `middleware/auth.global.ts`).

## Blameable (who created/edited a record)

`users` and `roles` carry nullable `created_by`/`updated_by` FKs, stamped from `auth()->id()` by the `App\Models\Concerns\Blameable` trait — **only when a user is authenticated**, so seeder/console/guest writes stay null rather than falsely attributed. (Because spatie's `Role` is vendor code, roles get the trait via `App\Models\Role`, a thin subclass wired as `permission.models.role`.)

The stamps surface through `UserResource`/`RoleResource` as `{ id, name }` (or `null`). This is complementary to the [activity log](activity-log.md), which records the *full* history rather than just the last actor.

## Adding a permission

1. Add a case to `App\Enums\Permission`.
2. Reference it in route middleware + `<Can>` / `useAuthz` on the frontend.
3. Run **`make sync-permissions`** — syncs the DB *and* regenerates `frontend/app/constants/permissions.ts`.

`make check-permissions` (part of `make test`) fails on any drift between the enum and the generated file, so the FE/BE lists can't silently diverge.
