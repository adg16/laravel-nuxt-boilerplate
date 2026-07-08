<?php

namespace App\Enums;

/**
 * The single source of truth for the application's permissions.
 *
 * A permission only means anything if code checks it — gating is applied
 * uniformly as `permission:<name>` route middleware (see routes/api.php), the
 * single place to audit who can call what. The vocabulary is owned here in code
 * rather than authored in the UI: `permission:sync` projects these cases into
 * the database, and the frontend `app/constants/permissions.ts` is *generated*
 * from this enum (never hand-edited), so the two lists can't drift.
 *
 * To add a permission: add a case here, reference it in the route middleware
 * (and `<Can>` / `useAuthz` on the frontend), then run `make sync-permissions`
 * — it syncs the DB and regenerates the frontend constants. `make
 * check-permissions` (part of `make test`) fails if they're ever out of sync.
 */
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case PermissionsView = 'permissions.view';
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';

    /**
     * Every permission value, for seeding/syncing and bulk assignment.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
