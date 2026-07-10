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
