<?php

namespace App\Models;

use App\Models\Concerns\Blameable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * App-level Role model — a thin subclass of spatie's so roles carry blame
 * columns (`created_by` / `updated_by`). Wired as the role model in
 * config/permission.php, so every spatie code path (route-model binding,
 * findOrCreate, HasRoles) resolves this class.
 */
class Role extends SpatieRole
{
    use Blameable;
}
