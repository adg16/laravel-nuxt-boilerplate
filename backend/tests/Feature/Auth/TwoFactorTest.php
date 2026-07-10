<?php

namespace Tests\Feature\Auth;

use App\Enums\Setting;
use App\Enums\TwoFactorMode;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests that look first-party.
        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    private function setMode(TwoFactorMode $mode): void
    {
        app(Settings::class)->set(Setting::TwoFactorMode, $mode->value);
    }

    /**
     * Give a user a fully-confirmed two-factor secret directly (bypassing the
     * enroll endpoints), returning the plaintext secret for OTP generation.
     */
    private function enroll(User $user): string
    {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_user_can_enable_and_confirm_two_factor_when_optional(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-authentication')->assertOk();

        $user->refresh();
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        $this->postJson('/api/user/confirmed-two-factor-authentication', ['code' => $this->otp($secret)])
            ->assertOk();

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->getJson('/api/user')->assertOk()->assertJsonPath('two_factor_enabled', true);
    }

    public function test_totp_user_can_read_and_regenerate_recovery_codes(): void
    {
        // The SPA reads recovery codes through the app's method-agnostic endpoint
        // (not Fortify's, which requires a secret) for both TOTP and email.
        $this->setMode(TwoFactorMode::Optional);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-authentication')->assertOk();

        $codes = $this->getJson('/api/user/two-factor/recovery-codes')->assertOk()->json();
        $this->assertCount(8, $codes);

        $regenerated = $this->postJson('/api/user/two-factor/recovery-codes')->assertOk()->json();
        $this->assertCount(8, $regenerated);
        $this->assertNotEquals($codes, $regenerated);
    }

    public function test_enrolled_user_is_challenged_at_login_and_can_complete_it(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $secret = $this->enroll($user);

        // The password login returns a challenge marker, not the user/session.
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', true);

        $this->getJson('/api/user')->assertStatus(401);

        $this->postJson('/api/two-factor-challenge', ['code' => $this->otp($secret)])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_off_mode_blocks_enrollment(): void
    {
        // Off is the default — no override needed.
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-authentication')
            ->assertForbidden()
            ->assertJsonPath('message', __('auth.two_factor_unavailable'));
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_required_mode_forbids_disabling_an_active_setup(): void
    {
        $this->setMode(TwoFactorMode::Required);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $secret = $this->enroll($user);
        $this->loginAs($user);
        $this->postJson('/api/two-factor-challenge', ['code' => $this->otp($secret)])->assertOk();

        $this->deleteJson('/api/user/two-factor-authentication')->assertForbidden();
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_required_mode_still_allows_cancelling_a_pending_enrollment(): void
    {
        // A not-yet-confirmed secret (a half-finished enrollment) must remain
        // removable even in Required mode, or the user could get stuck.
        $this->setMode(TwoFactorMode::Required);
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->loginAs($user);

        $this->postJson('/api/user/two-factor-authentication')->assertOk();
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());

        $this->deleteJson('/api/user/two-factor-authentication')->assertOk();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_off_mode_skips_the_challenge_for_an_enrolled_user(): void
    {
        // A user enrolled while 2FA was on, now switched off: logs straight in.
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $this->enroll($user);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_required_mode_blocks_management_until_enrolled(): void
    {
        $this->setMode(TwoFactorMode::Required);
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->loginAs($admin);

        // The management API is off-limits (even with permission) until enrolled.
        $this->getJson('/api/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'two_factor_setup_required');

        // Hydration, config, and the enrollment surface stay reachable.
        $this->getJson('/api/user')->assertOk();
        $this->getJson('/api/config')->assertOk()->assertJsonPath('twoFactorMode', 'required');
        $this->postJson('/api/user/two-factor-authentication')->assertOk();
    }

    public function test_required_mode_allows_management_after_enrollment(): void
    {
        $this->setMode(TwoFactorMode::Required);
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->enroll($admin);
        $this->loginAs($admin);

        // The login is challenged; complete it, then management works.
        $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
        $this->postJson('/api/two-factor-challenge', ['code' => $this->otp($secret)])->assertOk();

        $this->getJson('/api/users')->assertOk();
    }

    public function test_admin_can_reset_another_users_two_factor(): void
    {
        $this->setMode(TwoFactorMode::Optional);
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $target = User::factory()->create();
        $this->enroll($target);
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$target->id}/two-factor")
            ->assertOk()
            ->assertJsonPath('message', __('management.two_factor_reset'));

        $this->assertFalse($target->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertNull($target->fresh()->two_factor_secret);
    }

    public function test_resetting_two_factor_requires_manage_permission(): void
    {
        $viewer = User::factory()->create(['password' => bcrypt('password')])->assignRole('viewer');
        $target = User::factory()->create();
        $this->enroll($target);
        $this->loginAs($viewer);

        $this->deleteJson("/api/users/{$target->id}/two-factor")->assertForbidden();
        $this->assertTrue($target->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_resetting_two_factor_is_blocked_for_protected_accounts(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $protected = User::factory()->create()->assignRole('super-admin');
        $this->enroll($protected);
        $this->loginAs($admin);

        $this->deleteJson("/api/users/{$protected->id}/two-factor")->assertStatus(422);
        $this->assertTrue($protected->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_admin_reset_bypasses_the_required_mode_disable_guard(): void
    {
        // In Required mode a user can't disable their own active setup, but an
        // admin reset must still go through (the lockout-recovery path).
        $this->setMode(TwoFactorMode::Required);
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->enroll($admin);
        $target = User::factory()->create();
        $this->enroll($target);

        $this->loginAs($admin);
        $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
        $this->postJson('/api/two-factor-challenge', ['code' => $this->otp($secret)])->assertOk();

        $this->deleteJson("/api/users/{$target->id}/two-factor")->assertOk();
        $this->assertFalse($target->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_mode_setting_rejects_unknown_values(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('password')])->assignRole('admin');
        $this->loginAs($admin);

        $this->putJson('/api/settings/two_factor_mode', ['value' => 'sometimes'])
            ->assertStatus(422);

        $this->putJson('/api/settings/two_factor_mode', ['value' => 'required'])
            ->assertOk()
            ->assertJsonPath('value', 'required');
    }
}
