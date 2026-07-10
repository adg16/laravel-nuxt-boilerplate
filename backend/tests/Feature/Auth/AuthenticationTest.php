<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests it recognizes as coming
        // from a stateful (first-party SPA) frontend — tests must look the part.
        $this->withHeader('Referer', config('app.url'));
    }

    public function test_public_registration_route_is_not_registered(): void
    {
        // This is an internal backoffice tool: self-registration is disabled
        // entirely (the Fortify `registration` feature is off), so /api/register
        // simply doesn't exist. Accounts come from admins / the invitation flow.
        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_user_can_login_and_fetch_authenticated_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_guest_cannot_access_authenticated_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/logout')->assertOk();

        // AuthManager caches resolved guards for the lifetime of the container.
        // Sanctum's request guard memoizes the authenticated user permanently once
        // resolved, so within a single test (one long-lived container) it would
        // keep reporting the pre-logout user across further simulated requests —
        // something that can't happen in real PHP-FPM requests, which get a fresh
        // container each time. Forget it so the next request re-resolves guards.
        $this->app->forgetInstance('auth');

        $this->getJson('/api/user')->assertStatus(401);
    }
}
