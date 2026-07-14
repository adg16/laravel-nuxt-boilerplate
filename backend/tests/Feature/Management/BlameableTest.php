<?php

namespace Tests\Feature\Management;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BlameableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for first-party (SPA) requests.
        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_creating_a_user_stamps_the_acting_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'roles' => [],
        ])->assertCreated();

        $jane = User::whereEmail('jane@example.com')->firstOrFail();
        $this->assertSame($admin->id, $jane->created_by);
        $this->assertSame($admin->id, $jane->updated_by);
        $this->assertTrue($jane->creator->is($admin));
    }

    public function test_creating_a_role_stamps_the_acting_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/roles', ['name' => 'editor', 'permissions' => []])
            ->assertCreated();

        $role = Role::findByName('editor', 'web');
        $this->assertSame($admin->id, $role->created_by);
        $this->assertSame($admin->id, $role->updated_by);
    }

    public function test_a_permission_only_edit_stamps_updated_by_without_touching_created_by(): void
    {
        // Created outside a request, so its blame starts null.
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->assertNull($role->created_by);

        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        // Permissions live in a pivot; the role row is only stamped because the
        // controller touch()es it (see App\Models\Concerns\Blameable).
        $this->putJson("/api/roles/{$role->id}", [
            'name' => 'editor',
            'permissions' => ['users.view'],
        ])->assertOk();

        $role->refresh();
        $this->assertNull($role->created_by);
        $this->assertSame($admin->id, $role->updated_by);
    }

    public function test_resource_exposes_blame_stamps_as_id_and_name(): void
    {
        $admin = User::factory()->create(['name' => 'Ada Admin'])->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'roles' => [],
        ])->assertCreated();

        $jane = User::whereEmail('jane@example.com')->firstOrFail();

        $this->getJson("/api/users/{$jane->id}")
            ->assertOk()
            ->assertJsonPath('created_by.id', $admin->id)
            ->assertJsonPath('created_by.name', 'Ada Admin')
            ->assertJsonPath('updated_by.id', $admin->id);
    }

    public function test_super_admin_blame_stamps_are_redacted_from_non_super_admins(): void
    {
        // Jane was created by a super-admin, whose identity must not leak to a
        // plain admin viewing the account.
        $superAdmin = User::factory()->create()->assignRole('Super Admin');
        $jane = User::factory()->create();
        $jane->forceFill(['created_by' => $superAdmin->id])->save();

        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->getJson("/api/users/{$jane->id}")
            ->assertOk()
            ->assertJsonPath('created_by', null);
    }

    public function test_super_admin_sees_the_real_blame_stamp(): void
    {
        $superAdmin = User::factory()->create()->assignRole('Super Admin');
        $jane = User::factory()->create();
        $jane->forceFill(['created_by' => $superAdmin->id])->save();

        $viewer = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($viewer);

        $this->getJson("/api/users/{$jane->id}")
            ->assertOk()
            ->assertJsonPath('created_by.id', $superAdmin->id);
    }

    public function test_roles_list_exposes_blame_stamps_and_accepts_timestamp_sort(): void
    {
        $admin = User::factory()->create(['name' => 'Ada Admin'])->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/roles', ['name' => 'editor', 'permissions' => []])->assertCreated();

        // Filter to the one role and sort by a timestamp column (exercises the
        // widened sort_by whitelist + the index's creator/updater eager loads).
        $this->getJson('/api/roles?name=editor&sort_by=updated_at&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'editor')
            ->assertJsonPath('data.0.created_by.name', 'Ada Admin')
            ->assertJsonPath('data.0.updated_by.id', $admin->id);
    }

    public function test_users_list_redacts_super_admin_blame_stamps_from_non_super_admins(): void
    {
        $superAdmin = User::factory()->create()->assignRole('Super Admin');
        $jane = User::factory()->create(['name' => 'Jane']);
        $jane->forceFill(['created_by' => $superAdmin->id])->save();

        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        // A plain admin sees Jane (an ordinary user) but not the super-admin who
        // created her — the stamp is redacted in the list just like on `show`.
        // Also exercises the widened sort_by whitelist.
        $this->getJson('/api/users?name=Jane&sort_by=updated_at&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Jane')
            ->assertJsonPath('data.0.created_by', null);
    }

    public function test_records_written_without_an_authenticated_user_are_not_stamped(): void
    {
        // Factory / seeder writes have no acting user, so blame stays null
        // rather than being falsely attributed.
        $user = User::factory()->create();

        $this->assertNull($user->created_by);
        $this->assertNull($user->updated_by);
    }
}
