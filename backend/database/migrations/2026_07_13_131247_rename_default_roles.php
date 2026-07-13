<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

// The baseline roles were renamed to friendly display names (the role name is
// what users see — there is no separate label). `Super Admin` is also the
// code-referenced Gate::before identifier, so existing databases must be updated
// or the seeded super-admin would lose its bypass. Fresh installs run this on an
// empty table (no-op) and then get the new names from RolePermissionSeeder.
return new class extends Migration
{
    /** old name => new name (the committed baseline seed used lowercase slugs) */
    private const RENAMES = [
        'super-admin' => 'Super Admin',
        'admin' => 'Admin',
        'viewer' => 'Viewer',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        // Best-effort reverse (case-only renames collapse, e.g. Admin -> admin).
        $this->rename([
            'Super Admin' => 'super-admin',
            'Admin' => 'admin',
            'Viewer' => 'viewer',
        ]);
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        $table = config('permission.table_names.roles', 'roles');

        // Direct updates: on a fresh (empty) table each is a no-op, and on an
        // already-migrated table a case-only rename (e.g. admin -> Admin) just
        // rewrites the same row to the same value under the case-insensitive
        // unique index — no conflict. (Assumes the old and new names aren't both
        // present, which holds for the seeded set.)
        foreach ($map as $from => $to) {
            DB::table($table)->where('name', $from)->update(['name' => $to]);
        }

        // Roles are cached by the registrar; drop it so the new names resolve.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
