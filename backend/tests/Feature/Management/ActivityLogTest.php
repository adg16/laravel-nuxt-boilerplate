<?php

namespace Tests\Feature\Management;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_updating_a_user_logs_an_activity_with_the_actor_as_causer(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $target = User::factory()->create(['name' => 'Old Name'])->assignRole('Viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'New Name',
            'email' => $target->email,
            'roles' => ['Viewer'],
        ])->assertOk();

        $activity = Activity::where('log_name', 'users')
            ->where('event', 'updated')
            ->where('subject_type', User::class)
            ->where('subject_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        // v5 stores the auto-logged model diff in `attribute_changes`.
        $this->assertSame('Old Name', $activity->attribute_changes['old']['name']);
        $this->assertSame('New Name', $activity->attribute_changes['attributes']['name']);
    }

    public function test_changing_a_users_roles_logs_a_role_assignment(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $target = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['Admin'],
        ])->assertOk();

        $activity = Activity::where('description', 'role_assignment')
            ->where('subject_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(['Viewer'], $activity->properties['old']['roles']);
        $this->assertSame(['Admin'], $activity->properties['attributes']['roles']);
    }

    public function test_changing_a_roles_permissions_logs_a_permission_change(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $role = Role::create(['name' => 'Editors', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view']);
        $this->loginAs($admin);

        $this->putJson("/api/roles/{$role->id}", [
            'name' => 'Editors',
            'permissions' => ['users.view', 'users.manage'],
        ])->assertOk();

        $activity = Activity::where('description', 'permission_change')
            ->where('subject_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(['users.view'], $activity->properties['old']['permissions']);
        $this->assertEqualsCanonicalizing(['users.manage', 'users.view'], $activity->properties['attributes']['permissions']);
    }

    public function test_changing_a_setting_logs_an_activity(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        // Default is 'off'; move it so the change actually logs.
        $this->putJson('/api/settings/two_factor_mode', ['value' => 'optional'])->assertOk();

        $activity = Activity::where('log_name', 'settings')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('two_factor_mode', $activity->description);
        $this->assertSame('off', $activity->properties['old']['two_factor_mode']);
        $this->assertSame('optional', $activity->properties['attributes']['two_factor_mode']);

        // Re-applying the same value is a no-op — no second row.
        $this->putJson('/api/settings/two_factor_mode', ['value' => 'optional'])->assertOk();
        $this->assertSame(1, Activity::where('log_name', 'settings')->count());
    }

    public function test_activity_index_requires_the_view_permission(): void
    {
        $role = Role::create(['name' => 'Limited', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view']);
        $user = User::factory()->create()->assignRole($role);
        $this->loginAs($user);

        $this->getJson('/api/activity')->assertForbidden();
    }

    public function test_activity_index_returns_paginated_envelope(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $this->loginAs($admin);

        // Generate a few user-update activities.
        $target = User::factory()->create()->assignRole('Viewer');
        foreach (['A', 'B', 'C'] as $name) {
            $this->putJson("/api/users/{$target->id}", [
                'name' => $name,
                'email' => $target->email,
                'roles' => ['Viewer'],
            ])->assertOk();
        }

        $response = $this->getJson('/api/activity?per_page=2')->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
        $this->assertArrayHasKey('causer', $response->json('data.0'));
        $this->assertArrayHasKey('subject', $response->json('data.0'));
    }

    public function test_activity_search_matches_subject_and_changed_values(): void
    {
        $admin = User::factory()->create(['name' => 'Quill Admin'])->assignRole('Admin');
        $target = User::factory()->create(['name' => 'Old Name'])->assignRole('Viewer');
        $this->loginAs($admin);

        // Rename the target to a distinctive value → logged in the properties diff.
        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Zephyr',
            'email' => $target->email,
            'roles' => ['Viewer'],
        ])->assertOk();

        // Matches the new value in the change payload (and the subject, now named
        // Zephyr) — several rows, but the point is the search finds them.
        $this->assertGreaterThanOrEqual(1, $this->getJson('/api/activity?search=Zephyr')->assertOk()->json('total'));

        // Matches the old value preserved in the rename's diff.
        $this->assertGreaterThanOrEqual(1, $this->getJson('/api/activity?search=Old+Name')->assertOk()->json('total'));

        // A term that appears nowhere returns nothing.
        $this->getJson('/api/activity?search=nonexistentxyz')
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_search_ignores_subject_name_when_scoped_to_one_record(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $subject = User::factory()->create(['name' => 'Uniquename']);
        $this->loginAs($admin);

        // Drop the factory's `created` rows (whose properties hold the name).
        Activity::query()->delete();

        // A change whose properties do NOT contain the subject's name.
        activity('users')->performedOn($subject)
            ->withProperties(['old' => ['deactivated_at' => null], 'attributes' => ['deactivated_at' => '2026-07-15']])
            ->event('updated')->log('updated');

        // Global search (no subject filter) matches the subject's name.
        $this->assertSame(1, $this->getJson('/api/activity?search=Uniquename')->assertOk()->json('total'));

        // Scoped to the record, the subject name isn't searched — only values.
        $this->getJson('/api/activity?subject_type=user&subject_id='.$subject->id.'&search=Uniquename')
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_activity_actor_filter_matches_the_causer(): void
    {
        $admin = User::factory()->create(['name' => 'Quill Admin'])->assignRole('Admin');
        $target = User::factory()->create()->assignRole('Viewer');
        $this->loginAs($admin);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Renamed',
            'email' => $target->email,
            'roles' => ['Viewer'],
        ])->assertOk();

        // The dedicated actor filter finds rows caused by that user.
        $this->assertGreaterThanOrEqual(1, $this->getJson('/api/activity?actor=Quill')->assertOk()->json('total'));

        // An actor who caused nothing returns no rows.
        $this->getJson('/api/activity?actor=nobodyxyz')
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_same_timestamp_rows_order_by_insertion(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $subject = User::factory()->create();
        $this->loginAs($admin);
        Activity::query()->delete();

        // Two rows sharing the exact same second (as a `created` + `role_assignment`
        // pair does), written first then second.
        $first = activity('users')->performedOn($subject)->event('created')->log('created');
        $second = activity('users')->performedOn($subject)->event('updated')->log('role_assignment');
        $stamp = '2026-07-15 12:17:00';
        $first->forceFill(['created_at' => $stamp])->save();
        $second->forceFill(['created_at' => $stamp])->save();

        // Newest-first: the later-inserted row wins the tie (top); oldest last.
        $ids = collect($this->getJson('/api/activity?subject_type=user&subject_id='.$subject->id)
            ->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_activity_index_filters_by_date_range(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $subject = User::factory()->create();
        $this->loginAs($admin);

        // Clear the factory's `created` rows so only the dated rows below count.
        Activity::query()->delete();

        // Three activities dated across a week.
        activity('users')->performedOn($subject)->event('updated')->log('updated');
        Activity::latest('id')->first()->update(['created_at' => '2026-07-10 09:00:00']);
        activity('users')->performedOn($subject)->event('updated')->log('updated');
        Activity::latest('id')->first()->update(['created_at' => '2026-07-14 09:00:00']);
        activity('users')->performedOn($subject)->event('updated')->log('updated');
        Activity::latest('id')->first()->update(['created_at' => '2026-07-20 09:00:00']);

        // Window that spans only the middle row (inclusive on both ends).
        $this->getJson('/api/activity?subject_type=user&subject_id='.$subject->id.'&date_from=2026-07-12&date_to=2026-07-15')
            ->assertOk()->assertJsonPath('total', 1);

        // Open-ended lower bound picks up the middle and last rows.
        $this->getJson('/api/activity?subject_type=user&subject_id='.$subject->id.'&date_from=2026-07-12')
            ->assertOk()->assertJsonPath('total', 2);

        // A day boundary is inclusive.
        $this->getJson('/api/activity?subject_type=user&subject_id='.$subject->id.'&date_to=2026-07-10')
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_date_range_resolves_boundaries_in_the_given_timezone(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $subject = User::factory()->create();
        $this->loginAs($admin);
        Activity::query()->delete();

        // 20:00 UTC on Jul 15 is 04:00 on Jul 16 in Asia/Manila (+08).
        $activity = activity('users')->performedOn($subject)->event('updated')->log('updated');
        $activity->forceFill(['created_at' => '2026-07-15 20:00:00'])->save();

        $base = '/api/activity?subject_type=user&subject_id='.$subject->id.'&date_from=2026-07-16&date_to=2026-07-16';

        // In Manila the row is on Jul 16 → matches; in UTC (default) it's Jul 15 → not.
        $this->getJson($base.'&tz=Asia/Manila')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($base)->assertOk()->assertJsonPath('total', 0);
    }

    public function test_activity_about_restricted_subjects_is_hidden_from_non_super_admins(): void
    {
        $admin = User::factory()->create()->assignRole('Admin'); // has activity.view, not super
        $super = User::factory()->create()->assignRole('Super Admin');
        $superRoleId = Role::where('name', 'Super Admin')->value('id');

        // Activity whose subject is a super-admin user and the super-admin role.
        activity('users')->performedOn($super)->event('updated')->log('updated');
        activity('roles')->performedOn(Role::find($superRoleId))->event('updated')->log('updated');

        $this->loginAs($admin);
        $this->getJson("/api/activity?subject_type=user&subject_id={$super->id}")
            ->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/activity?subject_type=role&subject_id={$superRoleId}")
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_super_admin_sees_activity_about_restricted_subjects(): void
    {
        $super = User::factory()->create()->assignRole('Super Admin');
        $superRoleId = Role::where('name', 'Super Admin')->value('id');

        activity('users')->performedOn($super)->event('updated')->log('updated');
        activity('roles')->performedOn(Role::find($superRoleId))->event('updated')->log('updated');

        $this->loginAs($super);
        // The user subject also carries the factory's `created` row, so assert
        // presence rather than an exact count; the role has only the manual one.
        $userTotal = $this->getJson("/api/activity?subject_type=user&subject_id={$super->id}")
            ->assertOk()->json('total');
        $this->assertGreaterThanOrEqual(1, $userTotal);
        $this->getJson("/api/activity?subject_type=role&subject_id={$superRoleId}")
            ->assertOk()->assertJsonPath('total', 1);
    }
}
