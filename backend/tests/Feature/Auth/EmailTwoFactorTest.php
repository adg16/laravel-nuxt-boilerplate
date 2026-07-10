<?php

namespace Tests\Feature\Auth;

use App\Enums\Setting;
use App\Enums\TwoFactorMethodPolicy;
use App\Enums\TwoFactorMode;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\Settings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use Tests\TestCase;

class EmailTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function setMode(TwoFactorMode $mode): void
    {
        app(Settings::class)->set(Setting::TwoFactorMode, $mode->value);
    }

    private function setMethods(TwoFactorMethodPolicy $policy): void
    {
        app(Settings::class)->set(Setting::TwoFactorMethods, $policy->value);
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    /**
     * Enroll a user in email 2FA through the real endpoints, returning them
     * confirmed. Captures the emailed code via a faked notification.
     */
    private function enrollEmail(User $user): void
    {
        Notification::fake();
        $this->postJson('/api/user/two-factor-email')->assertOk();

        $code = $this->capturedCode($user);
        $this->postJson('/api/user/two-factor-email/confirm', ['code' => $code])->assertOk();
    }

    /**
     * Sign out, then sign back in so the login pipeline issues a fresh email
     * challenge. (The AuthManager memoizes the resolved guard for the life of the
     * container within one test, so we forget it around each identity switch —
     * see AuthenticationTest's logout note.)
     */
    private function startEmailChallenge(User $user): void
    {
        $this->app->forgetInstance('auth');
        $this->postJson('/api/logout');
        $this->app->forgetInstance('auth');

        Notification::fake();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonPath('two_factor_method', 'email');
        $this->app->forgetInstance('auth');
    }

    /**
     * Put a user into a confirmed email-2FA state without HTTP (so a *second*
     * account can be set up without a login that would pollute the memoized
     * guard within this test).
     */
    private function enrollEmailDirectly(User $user): void
    {
        $user->forceFill([
            'two_factor_method' => 'email',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(
                Collection::times(8, fn () => RecoveryCode::generate())->all()
            )),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    private function capturedCode(User $user): string
    {
        $captured = null;
        Notification::assertSentTo($user, TwoFactorCodeNotification::class, function ($n) use (&$captured) {
            $captured = $n->code;

            return true;
        });

        return $captured;
    }

    public function test_user_can_enroll_and_confirm_email_two_factor(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Both);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        Notification::fake();
        $this->postJson('/api/user/two-factor-email')
            ->assertOk()
            ->assertJsonStructure(['recovery_codes']);
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);

        $code = $this->capturedCode($user);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->postJson('/api/user/two-factor-email/confirm', ['code' => $code])->assertOk();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->getJson('/api/user')
            ->assertJsonPath('two_factor_enabled', true)
            ->assertJsonPath('two_factor_method', 'email');
    }

    public function test_enrolled_email_user_is_challenged_and_can_complete_login(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Both);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);
        $this->enrollEmail($user);

        // Fresh login now diverts to an email challenge (not a session).
        $this->startEmailChallenge($user);
        $this->getJson('/api/user')->assertStatus(401);

        $code = $this->capturedCode($user);
        $this->postJson('/api/two-factor-email-challenge', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_email_challenge_rejects_a_wrong_code(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Both);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);
        $this->enrollEmail($user);

        $this->startEmailChallenge($user);

        $this->postJson('/api/two-factor-email-challenge', ['code' => '000000'])->assertStatus(422);
        $this->app->forgetInstance('auth');
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_email_challenge_accepts_a_recovery_code(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Both);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);
        $this->enrollEmail($user);

        $recovery = $this->getJson('/api/user/two-factor/recovery-codes')->assertOk()->json();
        $this->assertNotEmpty($recovery);

        $this->startEmailChallenge($user);

        $this->postJson('/api/two-factor-email-challenge', ['recovery_code' => $recovery[0]])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_policy_totp_only_blocks_email_enrollment(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Totp);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-email')->assertForbidden();
    }

    public function test_policy_email_only_blocks_totp_enrollment(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Email);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-authentication')->assertForbidden();
    }

    public function test_admin_reset_clears_an_email_two_factor_setup(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $this->setMethods(TwoFactorMethodPolicy::Both);
        // Enroll the target directly so only the admin logs in this test.
        $target = User::factory()->create();
        $this->enrollEmailDirectly($target);
        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());

        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$target->id}/two-factor")->assertOk();

        $fresh = $target->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_method);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_email_method_satisfies_required_mode(): void
    {
        $this->setMode(TwoFactorMode::Optional); // enroll first while not gated
        $this->setMethods(TwoFactorMethodPolicy::Both);
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->loginAs($admin);
        $this->enrollEmail($admin);

        $this->setMode(TwoFactorMode::Required);

        // Enrolled (email) → the required-mode gate lets management through.
        $this->getJson('/api/users')->assertOk();
    }
}
