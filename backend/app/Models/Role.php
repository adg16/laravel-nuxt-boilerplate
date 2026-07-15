<?php

namespace App\Models;

use App\Models\Concerns\Blameable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * App-level Role model — a thin subclass of spatie's so roles carry blame
 * columns (`created_by` / `updated_by`) and an activity log. Wired as the role
 * model in config/permission.php, so every spatie code path (route-model
 * binding, findOrCreate, HasRoles) resolves this class.
 */
class Role extends SpatieRole
{
    use Blameable, LogsActivity;

    /**
     * Audit trail: only the role's own name is a column; permission grants live
     * in a pivot and are logged explicitly in RoleController (see
     * App\Support\ActivityLogger).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('roles')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
