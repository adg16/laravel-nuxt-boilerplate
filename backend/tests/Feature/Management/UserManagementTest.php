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
        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($viewer);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', $viewer->email)
            ->assertJsonPath('total', 1);
    }

    public function test_users_list_paginates_and_reports_the_total(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        User::factory()->count(30)->create();
        $this->loginAs($admin);

        $response = $this->getJson('/api/users?per_page=10&page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        // 30 created + the admin themselves.
        $this->assertSame(31, $response->json('total'));
    }

    public function test_users_list_filters_by_name_role_and_status(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $alice = User::factory()->create(['name' => 'Alice Zephyr', 'email' => 'alice@example.com'])->assignRole('Viewer');
        $bob = User::factory()->create(['name' => 'Bob Quill', 'email' => 'bob@example.com', 'deactivated_at' => now()])->assignRole('Admin');
        $this->loginAs($admin);

        // Name substring match.
        $this->getJson('/api/users?name=zephyr')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.email', $alice->email);

        // Role filter — only viewers.
        $emails = collect($this->getJson('/api/users?roles=Viewer')->assertOk()->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($alice->email));
        $this->assertFalse($emails->contains($bob->email));

        // Account-status filter — only deactivated accounts.
        $emails = collect($this->getJson('/api/users?account_status=inactive')->assertOk()->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($bob->email));
        $this->assertFalse($emails->contains($alice->email));
    }

    public function test_users_list_name_filter_matches_a_literal_zero(): void
    {
        // "0" is a falsy string in PHP — the filter must still apply, not be skipped.
        $admin = User::factory()->create(['name' => 'Admin User'])->assignRole('Admin');
        User::factory()->create(['name' => 'Agent 007', 'email' => 'agent@example.com']);
        $this->loginAs($admin);

        $names = collect($this->getJson('/api/users?name=0')->assertOk()->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Agent 007'));
        $this->assertFalse($names->contains('Admin User'));
    }

    public function test_users_list_status_filters_or_within_field_and_and_across_fields(): void
    {
        $admin = User::factory()->create(['email' => 'admin-user@example.com'])->assignRole('Admin');
        User::factory()->create(['email' => 'av@example.com']); // active + verified
        User::factory()->create(['email' => 'iv@example.com', 'deactivated_at' => now()]); // inactive + verified
        User::factory()->create(['email' => 'au@example.com', 'email_verified_at' => null]); // active + unverified
        User::factory()->create(['email' => 'iu@example.com', 'deactivated_at' => now(), 'email_verified_at' => null]); // inactive + unverified
        $this->loginAs($admin);

        // OR within a field: verification=unverified matches both unverified users.
        $emails = collect($this->getJson('/api/users?verification_status=unverified')->assertOk()->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('au@example.com'));
        $this->assertTrue($emails->contains('iu@example.com'));
        $this->assertFalse($emails->contains('av@example.com'));

        // AND across fields: inactive AND unverified narrows to the one user failing both.
        $emails = collect($this->getJson('/api/users?account_status=inactive&verification_status=unverified')->assertOk()->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('iu@example.com'));
        $this->assertFalse($emails->contains('iv@example.com'));  // inactive but verified
        $this->assertFalse($emails->contains('au@example.com'));  // unverified but active
        $this->assertFalse($emails->contains('av@example.com'));
    }

    public function test_user_without_manage_cannot_create_user(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
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
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'roles' => ['Viewer'],
        ])->assertCreated()
            ->assertJsonPath('roles.0', 'Viewer')
            ->assertJsonPath('is_verified', false);

        $jane = User::whereEmail('jane@example.com')->firstOrFail();
        $this->assertTrue($jane->hasRole('Viewer'));
        Notification::assertSentTo($jane, UserInvitation::class);
    }

    public function test_non_super_admin_cannot_assign_the_super_admin_role(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Escalate',
            'email' => 'escalate@example.com',
            'roles' => ['Super Admin'],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
    }

    public function test_super_admin_can_assign_the_super_admin_role(): void
    {
        Notification::fake();
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($super);

        $this->postJson('/api/users', [
            'name' => 'New Super',
            'email' => 'newsuper@example.com',
            'roles' => ['Super Admin'],
        ])->assertCreated();

        $this->assertTrue(User::whereEmail('newsuper@example.com')->firstOrFail()->hasRole('Super Admin'));
    }

    public function test_cannot_create_a_user_with_the_reserved_super_admin_name(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        // Case-insensitive — "super admin" is just as reserved as "Super Admin" —
        // and the message reports the canonical reserved name.
        $this->postJson('/api/users', [
            'name' => 'super admin',
            'email' => 'imposter@example.com',
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['name' => 'The name "Super Admin" is reserved']);

        $this->assertDatabaseMissing('users', ['email' => 'imposter@example.com']);
    }

    public function test_cannot_rename_a_user_to_the_reserved_super_admin_name(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $target = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Super Admin',
            'email' => $target->email,
            'roles' => ['Viewer'],
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_cannot_create_a_user_with_the_reserved_system_name(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson('/api/users', [
            'name' => 'system',
            'email' => 'sys@example.com',
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'sys@example.com']);
    }

    public function test_admin_can_update_a_users_roles(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $target = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Admin'],
        ])->assertOk()->assertJsonPath('roles.0', 'Admin');

        $this->assertTrue($target->fresh()->hasRole('Admin'));
        $this->assertFalse($target->fresh()->hasRole('Viewer'));
    }

    public function test_a_super_admin_bypasses_permission_checks_without_explicit_permissions(): void
    {
        // super-admin holds no permissions — only Gate::before grants access.
        $super = User::factory()->create()->assignRole('Super Admin');
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
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_a_non_super_admin_cannot_delete_a_super_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$super->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_a_super_admin_can_manage_an_assigned_super_admin(): void
    {
        $actor = User::factory()->create()->assignRole('Super Admin');
        $assigned = User::factory()->create(['name' => 'Assigned Super'])->assignRole('Super Admin');
        $this->loginAs($actor);

        // Editable, unlike the original super-admin…
        $this->putJson("/api/users/{$assigned->id}", [
            'name' => 'Renamed Super',
            'email' => $assigned->email,
            'roles' => ['Super Admin'],
        ])->assertOk()->assertJsonPath('name', 'Renamed Super');

        // …and deletable.
        $this->deleteJson("/api/users/{$assigned->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $assigned->id]);
    }

    public function test_the_default_super_admin_cannot_be_modified_even_by_a_super_admin(): void
    {
        $actor = User::factory()->create()->assignRole('Super Admin');
        $default = User::factory()->protected()->create(['email' => config('users.default_user.email')])->assignRole('Super Admin');
        $this->loginAs($actor);

        $this->putJson("/api/users/{$default->id}", [
            'name' => 'Hijack',
            'email' => $default->email,
            'roles' => ['Super Admin'],
        ])->assertStatus(422)->assertJsonValidationErrors('user');

        $this->deleteJson("/api/users/{$default->id}")
            ->assertStatus(422)->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $default->id]);
    }

    public function test_protected_accounts_are_hidden_from_non_super_admins(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($admin);

        $emails = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('email');

        $this->assertTrue($emails->contains($admin->email));
        $this->assertFalse($emails->contains($super->email));
        $this->assertFalse($emails->contains($system->email));
    }

    public function test_a_super_admin_sees_protected_accounts_in_the_list(): void
    {
        $super = User::factory()->create()->assignRole('Super Admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($super);

        $emails = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('email');

        $this->assertTrue($emails->contains($super->email));
        $this->assertTrue($emails->contains($system->email));
    }

    public function test_a_non_super_admin_cannot_view_a_protected_account_by_id(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $system = User::factory()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($admin);

        $this->getJson("/api/users/{$super->id}")->assertNotFound();
        $this->getJson("/api/users/{$system->id}")->assertNotFound();
    }

    public function test_a_super_admin_can_view_a_protected_account_by_id(): void
    {
        $super = User::factory()->create()->assignRole('Super Admin');
        $system = User::factory()->protected()->create(['email' => config('app.system_user_email')]);
        $this->loginAs($super);

        $this->getJson("/api/users/{$system->id}")
            ->assertOk()
            ->assertJsonPath('email', $system->email)
            ->assertJsonPath('is_protected', true);
    }

    public function test_the_system_account_cannot_be_edited_or_deleted(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $system = User::factory()->protected()->create(['email' => config('app.system_user_email')]);
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
        $admin = User::factory()->create()->assignRole('Admin');
        $target = User::factory()->create()->assignRole('Viewer');
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
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$admin->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_a_protected_account_cannot_be_deactivated(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$super->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertTrue($super->fresh()->isActive());
    }

    public function test_a_non_super_admin_cannot_reset_a_super_admins_two_factor(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$super->id}/two-factor")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }

    public function test_a_non_super_admin_cannot_activate_a_super_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create()->assignRole('Super Admin');
        $this->loginAs($admin);

        $this->postJson("/api/users/{$super->id}/activate")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
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
        $user = User::factory()->create()->assignRole('Viewer');
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

    public function test_a_non_super_admin_cannot_edit_a_super_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $super = User::factory()->create(['name' => 'Root'])->assignRole('Super Admin');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$super->id}", [
            'name' => 'Changed',
            'email' => $super->email,
            'roles' => ['Super Admin'],
        ])->assertStatus(422)->assertJsonValidationErrors('user');

        $this->assertSame('Root', $super->fresh()->name);
    }
}
