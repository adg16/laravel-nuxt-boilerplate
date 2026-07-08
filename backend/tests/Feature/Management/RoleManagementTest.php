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
        $viewer = User::factory()->create()->assignRole('viewer');
        $this->loginAs($viewer);

        $this->postJson('/api/roles', ['name' => 'editor', 'permissions' => []])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
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
        $admin = User::factory()->create()->assignRole('admin');
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
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);
        $super = Role::findByName('super-admin', 'web');

        $this->putJson("/api/roles/{$super->id}", ['name' => 'root', 'permissions' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_a_role_assigned_to_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        User::factory()->create()->assignRole('viewer');
        $this->loginAs($admin);
        $viewer = Role::findByName('viewer', 'web');

        $this->deleteJson("/api/roles/{$viewer->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $viewer->id]);
    }

    public function test_admin_can_delete_an_unused_role(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        $this->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
