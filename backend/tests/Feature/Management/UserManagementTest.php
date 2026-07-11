<?php

namespace Tests\Feature\Management;

use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for first-party (SPA) requests.
        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_user_with_users_view_can_list_users(): void
    {
        $viewer = User::factory()->create()->assignRole('viewer');
        $this->loginAs($viewer);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('0.email', $viewer->email);
    }

    public function test_user_without_manage_cannot_create_user(): void
    {
        $viewer = User::factory()->create()->assignRole('viewer');
        $this->loginAs($viewer);

        $this->postJson('/api/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'roles' => [],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
    }

    public function test_admin_can_invite_a_user_who_starts_unverified(): void
    {
        Notification::fake();
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'roles' => ['viewer'],
        ])->assertCreated()
            ->assertJsonPath('roles.0', 'viewer')
            ->assertJsonPath('is_verified', false);

        $jane = User::whereEmail('jane@example.com')->firstOrFail();
        $this->assertTrue($jane->hasRole('viewer'));
        Notification::assertSentTo($jane, UserInvitation::class);
    }

    public function test_admin_can_update_a_users_roles(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $target = User::factory()->create()->assignRole('viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['admin'],
        ])->assertOk()->assertJsonPath('roles.0', 'admin');

        $this->assertTrue($target->fresh()->hasRole('admin'));
        $this->assertFalse($target->fresh()->hasRole('viewer'));
    }

    public function test_a_super_admin_bypasses_permission_checks_without_explicit_permissions(): void
    {
        // super-admin holds no permissions — only Gate::before grants access.
        $super = User::factory()->create()->assignRole('super-admin');
        $this->assertEmpty($super->getAllPermissions());
        $this->loginAs($super);

        $this->postJson('/api/users', [
            'name' => 'Made By Super',
            'email' => 'super-made@example.com',
            'roles' => [],
        ])->assertCreated();
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_a_super_admin_user_cannot_be_deleted(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$super->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_protected_accounts_are_hidden_from_non_super_admins(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($admin);

        $emails = collect($this->getJson('/api/users')->assertOk()->json())->pluck('email');

        $this->assertTrue($emails->contains($admin->email));
        $this->assertFalse($emails->contains($super->email));
        $this->assertFalse($emails->contains($system->email));
    }

    public function test_a_super_admin_sees_protected_accounts_in_the_list(): void
    {
        $super = User::factory()->create()->assignRole('super-admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($super);

        $emails = collect($this->getJson('/api/users')->assertOk()->json())->pluck('email');

        $this->assertTrue($emails->contains($super->email));
        $this->assertTrue($emails->contains($system->email));
    }

    public function test_a_non_super_admin_cannot_view_a_protected_account_by_id(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($admin);

        $this->getJson("/api/users/{$super->id}")->assertNotFound();
        $this->getJson("/api/users/{$system->id}")->assertNotFound();
    }

    public function test_a_super_admin_can_view_a_protected_account_by_id(): void
    {
        $super = User::factory()->create()->assignRole('super-admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($super);

        $this->getJson("/api/users/{$system->id}")
            ->assertOk()
            ->assertJsonPath('email', $system->email)
            ->assertJsonPath('is_protected', true);
    }

    public function test_the_system_account_cannot_be_edited_or_deleted(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($admin);

        $this->putJson("/api/users/{$system->id}", [
            'name' => 'Changed',
            'email' => $system->email,
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('user');

        $this->deleteJson("/api/users/{$system->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $system->id]);
    }

    public function test_seeder_creates_a_permissionless_system_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $system = User::system();

        $this->assertNotNull($system);
        $this->assertSame('System', $system->name);
        $this->assertEmpty($system->getRoleNames());
        $this->assertTrue($system->isProtected());
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $target = User::factory()->create()->assignRole('viewer');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$target->id}/deactivate")
            ->assertOk()
            ->assertJsonStructure(['message']);
        $this->assertFalse($target->fresh()->isActive());

        $this->postJson("/api/users/{$target->id}/activate")
            ->assertOk();
        $this->assertTrue($target->fresh()->isActive());
    }

    public function test_a_user_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$admin->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_a_protected_account_cannot_be_deactivated(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-admin');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$super->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertTrue($super->fresh()->isActive());
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $user->deactivate();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', __('auth.account_deactivated'));
    }

    public function test_a_live_session_is_cut_off_once_the_user_is_deactivated(): void
    {
        $user = User::factory()->create()->assignRole('viewer');
        $this->loginAs($user);

        // Session is live and hydrating fine.
        $this->getJson('/api/user')->assertOk();

        $user->deactivate();

        // Drop the memoized guard so the next request re-resolves the (now
        // deactivated) user rather than the cached one (see CLAUDE.md).
        $this->app->forgetInstance('auth');

        $this->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_deactivated');
    }

    public function test_a_super_admin_user_cannot_be_edited(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create(['name' => 'Root'])->assignRole('super-admin');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$super->id}", [
            'name' => 'Changed',
            'email' => $super->email,
            'roles' => ['super-admin'],
        ])->assertStatus(422)->assertJsonValidationErrors('user');

        $this->assertSame('Root', $super->fresh()->name);
    }
}
