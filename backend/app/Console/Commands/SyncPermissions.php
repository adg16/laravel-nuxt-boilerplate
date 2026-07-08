<?php

namespace App\Console\Commands;

use App\Enums\Permission as PermissionEnum;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permission:sync {--prune : Delete DB permissions no longer defined in the Permission enum}';

    protected $description = 'Reconcile the database permissions with the App\\Enums\\Permission registry';

    public function handle(PermissionRegistrar $registrar): int
    {
        // The SPA authenticates through the session-backed `web` guard, so all
        // permissions live under it (kept explicit rather than reading the
        // default guard, which `auth:sanctum` flips to `sanctum` mid-request).
        $guard = 'web';
        $names = PermissionEnum::values();

        foreach ($names as $name) {
            Permission::findOrCreate($name, $guard);
        }
        $this->info(count($names).' permission(s) present.');

        if ($this->option('prune')) {
            $removed = Permission::where('guard_name', $guard)
                ->whereNotIn('name', $names)
                ->get();

            foreach ($removed as $permission) {
                $permission->delete();
                $this->line("Pruned: {$permission->name}");
            }
        }

        // Drop spatie's cached permission map so the change is visible at once.
        $registrar->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
