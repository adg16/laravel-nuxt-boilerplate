<?php

namespace Tests\Feature\Management;

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
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

    public function test_user_with_roles_view_sees_the_catalog(): void
    {
        // The catalog feeds the role editor, so it rides on roles.view.
        $viewer = User::factory()->create()->assignRole('viewer');
        $this->loginAs($viewer);

        $this->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonCount(count(Permission::values()))
            ->assertJsonPath('0.roles', fn ($roles) => is_array($roles));
    }

    public function test_user_without_roles_view_is_forbidden(): void
    {
        $user = User::factory()->create(); // no roles
        $this->loginAs($user);

        $this->getJson('/api/permissions')->assertForbidden();
    }

    public function test_permissions_have_no_write_endpoint(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        // Only GET is routed; a write attempt is Method Not Allowed.
        $this->postJson('/api/permissions', ['name' => 'hack.me'])
            ->assertStatus(405);

        $this->assertDatabaseMissing('permissions', ['name' => 'hack.me']);
    }
}
