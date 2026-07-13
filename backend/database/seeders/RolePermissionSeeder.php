<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the baseline roles. Permissions themselves are code-defined and
     * synced from the Permission enum (see `permission:sync`).
     */
    public function run(): void
    {
        // Ensure every code-defined permission exists before wiring up roles.
        Artisan::call('permission:sync');

        // The bypass role: holds no explicit permissions — Gate::before grants
        // it everything (see AppServiceProvider).
        Role::findOrCreate('Super Admin');

        // An ordinary, fully-editable role, pre-loaded with the management
        // permissions as a convenient starting point.
        Role::findOrCreate('Admin')
            ->syncPermissions(Permission::all());

        // An example limited role: read-only access to the management screens.
        Role::findOrCreate('Viewer')
            ->syncPermissions(array_filter(
                PermissionEnum::values(),
                fn (string $name) => Str::endsWith($name, '.view')
            ));
    }
}
