<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Logs the changes that the model-level LogsActivity trait can't see on its own:
 * pivot (many-to-many) edits and non-Eloquent settings. Everything is written in
 * the same `{ old, attributes }` property shape the trait produces, so the
 * frontend renders every activity's diff the same way.
 *
 * The causer is resolved automatically by spatie from the `web` guard (see
 * config/activitylog.php) — the same actor App\Models\Concerns\Blameable stamps.
 */
class ActivityLogger
{
    /**
     * Log a change to a user's assigned roles. No-op when the set is unchanged.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    public static function logRoleAssignment(User $user, array $before, array $after): void
    {
        self::logListChange('users', 'role_assignment', $user, 'roles', $before, $after);
    }

    /**
     * Log a change to a role's granted permissions. No-op when unchanged.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    public static function logPermissionChange(Role $role, array $before, array $after): void
    {
        self::logListChange('roles', 'permission_change', $role, 'permissions', $before, $after);
    }

    /**
     * Log a settings value change. Settings aren't Eloquent models, so the
     * activity has no subject — the key travels in the description and diff.
     */
    public static function logSetting(string $key, mixed $old, mixed $new): void
    {
        if ($old === $new) {
            return;
        }

        activity('settings')
            ->withProperties([
                'old' => [$key => $old],
                'attributes' => [$key => $new],
            ])
            ->event('updated')
            ->log($key);
    }

    /**
     * Diff two string lists and, if they differ, log an `updated` activity whose
     * diff carries the old/new lists under a single field name.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private static function logListChange(string $logName, string $description, Model $subject, string $field, array $before, array $after): void
    {
        // Order-insensitive comparison; store sorted so the rendered diff is stable.
        $before = array_values(array_unique($before));
        $after = array_values(array_unique($after));
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        activity($logName)
            ->performedOn($subject)
            ->withProperties([
                'old' => [$field => $before],
                'attributes' => [$field => $after],
            ])
            ->event('updated')
            ->log($description);
    }
}
