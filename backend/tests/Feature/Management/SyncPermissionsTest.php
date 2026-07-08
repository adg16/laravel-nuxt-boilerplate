<?php

namespace Tests\Feature\Management;

use App\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Tests\TestCase;

class SyncPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_every_enum_permission_under_the_web_guard(): void
    {
        $this->assertSame(0, PermissionModel::count());

        $this->artisan('permission:sync')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            Permission::values(),
            PermissionModel::where('guard_name', 'web')->pluck('name')->all()
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('permission:sync')->assertSuccessful();
        $this->artisan('permission:sync')->assertSuccessful();

        $this->assertSame(count(Permission::values()), PermissionModel::count());
    }

    public function test_prune_removes_permissions_not_in_the_enum(): void
    {
        $this->artisan('permission:sync')->assertSuccessful();
        PermissionModel::create(['name' => 'legacy.permission', 'guard_name' => 'web']);

        $this->artisan('permission:sync --prune')->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'legacy.permission']);
        $this->assertSame(count(Permission::values()), PermissionModel::count());
    }
}
