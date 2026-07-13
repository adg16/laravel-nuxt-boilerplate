<?php

namespace Tests\Feature\Management;

use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.url'));
    }

    /**
     * Create a pending (unverified) user with a live invitation, returning the
     * plaintext token the e-mail would have carried.
     */
    private function inviteUser(string $email = 'invitee@example.com'): array
    {
        $user = User::factory()->unverified()->create(['email' => $email]);
        $token = 'plain-token-value';
        DB::table('invitations')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        return [$user, $token];
    }

    public function test_accepting_an_invitation_sets_the_password_and_verifies_the_user(): void
    {
        [$user, $token] = $this->inviteUser();

        $this->postJson('/api/accept-invitation', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->isVerified());
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertDatabaseMissing('invitations', ['email' => $user->email]);
    }

    public function test_an_accepted_invitation_lets_the_user_log_in(): void
    {
        [$user, $token] = $this->inviteUser();

        $this->postJson('/api/accept-invitation', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk()->assertJsonPath('email', $user->email);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        [$user] = $this->inviteUser();

        $this->postJson('/api/accept-invitation', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertFalse($user->fresh()->isVerified());
    }

    public function test_an_expired_invitation_is_rejected(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        DB::table('invitations')->insert([
            'email' => $user->email,
            'token' => Hash::make('tok'),
            'created_at' => now()->subDays(config('invitation.expire_days') + 1),
        ]);

        $this->postJson('/api/accept-invitation', [
            'email' => $user->email,
            'token' => 'tok',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertFalse($user->fresh()->isVerified());
    }

    public function test_resending_an_invitation_queues_a_fresh_notification(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('Admin');
        $pending = User::factory()->unverified()->create();
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();

        $this->postJson("/api/users/{$pending->id}/resend-invite")->assertOk();

        Notification::assertSentTo($pending, UserInvitation::class);
    }

    public function test_resend_is_blocked_once_the_invitation_is_accepted(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('Admin');
        $verified = User::factory()->create(); // factory users are verified
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();

        $this->postJson("/api/users/{$verified->id}/resend-invite")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }
}
