<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests it recognizes as coming
        // from a stateful (first-party SPA) frontend — tests must look the part.
        $this->withHeader('Referer', config('app.url'));
    }

    public function test_user_can_update_their_profile_information(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_profile_email_must_be_unique_to_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_default_super_admin_cannot_change_their_name_but_can_change_other_fields(): void
    {
        $user = User::factory()->protected()->create([
            'email' => config('users.default_user.email'),
            'name' => 'Root Admin',
        ]);

        // Name change is rejected…
        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'New Name',
                'email' => $user->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        // …but changing the email (name unchanged) is allowed.
        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Root Admin',
                'email' => 'newmail@example.com',
            ])
            ->assertOk();

        $this->assertSame('Root Admin', $user->fresh()->name);
        $this->assertSame('newmail@example.com', $user->fresh()->email);
    }

    public function test_protection_and_name_lock_survive_an_email_change(): void
    {
        // Regression: protection is a durable flag, not derived from the email —
        // so changing the email can't strip it.
        $user = User::factory()->protected()->create([
            'email' => config('users.default_user.email'),
            'name' => config('users.default_user.name'),
        ]);

        // Change the email (keeping the locked name) — allowed.
        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => $user->name,
                'email' => 'moved@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('moved@example.com', $user->email);
        $this->assertTrue($user->isProtected());

        // The name is still locked even after the email moved.
        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Renamed',
                'email' => $user->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_user_cannot_take_the_reserved_super_admin_name_via_profile(): void
    {
        $user = User::factory()->create(['name' => 'Regular', 'email' => 'reg@example.com']);

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Super Admin',
                'email' => $user->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_profile_update_allows_keeping_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)
            ->putJson('/api/user/profile-information', [
                'name' => 'Renamed',
                'email' => 'me@example.com',
            ])
            ->assertOk();
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->putJson('/api/user/profile-information', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
        ])->assertUnauthorized();
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_password_change_requires_matching_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current-password')]);

        $this->actingAs($user)
            ->putJson('/api/user/password', [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->putJson('/api/user/password', [
            'current_password' => 'whatever',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnauthorized();
    }
}
