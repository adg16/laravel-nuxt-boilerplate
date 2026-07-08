<?php

namespace Tests\Feature\Management;

use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserCreationModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function actAsAdmin(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
    }

    private function setMode(string $mode): void
    {
        config()->set('users.creation_mode', $mode);
    }

    public function test_choice_mode_defaults_to_invite_when_no_method_is_given(): void
    {
        Notification::fake();
        $this->setMode('choice');
        $this->actAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'roles' => [],
        ])->assertCreated()->assertJsonPath('is_verified', false);

        $jane = User::whereEmail('jane@example.com')->firstOrFail();
        Notification::assertSentTo($jane, UserInvitation::class);
    }

    public function test_choice_mode_admin_can_set_a_password_making_the_user_active(): void
    {
        Notification::fake();
        $this->setMode('choice');
        $this->actAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'Kim',
            'email' => 'kim@example.com',
            'method' => 'set_password',
            'password' => 'kims-password',
            'password_confirmation' => 'kims-password',
            'roles' => [],
        ])->assertCreated()->assertJsonPath('is_verified', true);

        $kim = User::whereEmail('kim@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('kims-password', $kim->password));
        Notification::assertNothingSent();
    }

    public function test_set_password_mode_requires_a_password(): void
    {
        $this->setMode('set_password');
        $this->actAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'NoPass',
            'email' => 'nopass@example.com',
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_forced_invite_mode_ignores_a_client_supplied_password(): void
    {
        Notification::fake();
        $this->setMode('invite');
        $this->actAsAdmin();

        // Even though the client asks to set a password, the fixed mode wins.
        $this->postJson('/api/users', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'method' => 'set_password',
            'password' => 'attacker-chosen',
            'password_confirmation' => 'attacker-chosen',
            'roles' => [],
        ])->assertCreated()->assertJsonPath('is_verified', false);

        $user = User::whereEmail('mallory@example.com')->firstOrFail();
        $this->assertFalse(Hash::check('attacker-chosen', $user->password));
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_config_endpoint_reports_the_active_mode(): void
    {
        $this->setMode('set_password');
        $this->actAsAdmin();

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('userCreationMode', 'set_password');
    }
}
