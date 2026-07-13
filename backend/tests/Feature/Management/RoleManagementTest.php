<?php

namespace Tests\Feature\Management;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_user_without_manage_cannot_create_role(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($viewer);

        $this->postJson('/api/roles', ['name' => 'editor', 'permissions' => []])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/roles', [
            'name' => 'editor',
            'permissions' => ['users.view', 'roles.view'],
        ])->assertCreated()->assertJsonPath('name', 'editor');

        $role = Role::findByName('editor', 'web');
        $this->assertEqualsCanonicalizing(
            ['users.view', 'roles.view'],
            $role->permissions->pluck('name')->all()
        );
    }

    public function test_admin_can_sync_a_roles_permissions(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->putJson("/api/roles/{$role->id}", [
            'name' => 'editor',
            'permissions' => ['users.view'],
        ])->assertOk();

        $this->assertEquals(['users.view'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_the_super_admin_role_cannot_be_modified(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);
        $super = Role::findByName('Super Admin', 'web');

        $this->putJson("/api/roles/{$super->id}", ['name' => 'root', 'permissions' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_a_role_assigned_to_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        User::factory()->create()->assignRole('Viewer');
        $this->loginAs($admin);
        $viewer = Role::findByName('Viewer', 'web');

        $this->deleteJson("/api/roles/{$viewer->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $viewer->id]);
    }

    public function test_admin_can_delete_an_unused_role(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        $this->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_roles_list_paginates_and_reports_the_total(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        for ($i = 0; $i < 5; $i++) {
            Role::create(['name' => "role-{$i}", 'guard_name' => 'web']);
        }
        $this->loginAs($admin);

        // The admin (non-super-admin) can't see the super-admin role.
        $expected = Role::where('name', '!=', 'Super Admin')->count();
        $response = $this->getJson('/api/roles?per_page=3&page=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertSame($expected, $response->json('total'));
    }

    public function test_roles_list_filters_by_name(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->loginAs($admin);

        $this->getJson('/api/roles?name=edit')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'editor');
    }

    public function test_super_admin_role_is_hidden_from_non_super_admins(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $names = collect($this->getJson('/api/roles')->assertOk()->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Super Admin'));
        $this->assertTrue($names->contains('Admin'));
    }

    public function test_a_super_admin_sees_the_super_admin_role(): void
    {
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($super);

        $names = collect($this->getJson('/api/roles')->assertOk()->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Super Admin'));
    }

    public function test_a_non_super_admin_cannot_view_the_super_admin_role_by_id(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $superRole = Role::findByName('Super Admin', 'web');
        $this->loginAs($admin);

        $this->getJson("/api/roles/{$superRole->id}")->assertNotFound();
    }

    public function test_roles_list_filters_by_permission(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        Role::create(['name' => 'settings-viewer', 'guard_name' => 'web'])->syncPermissions(['settings.view']);
        Role::create(['name' => 'empty-role', 'guard_name' => 'web']);
        $this->loginAs($admin);

        $names = collect($this->getJson('/api/roles?permissions=settings.view')->assertOk()->json('data'))->pluck('name');
        $this->assertTrue($names->contains('settings-viewer'));
        $this->assertFalse($names->contains('empty-role'));
    }
}
