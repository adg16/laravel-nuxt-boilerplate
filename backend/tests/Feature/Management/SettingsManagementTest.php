<?php

namespace Tests\Feature\Management;

use App\Enums\Setting;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.url'));
        $this->seed(RolePermissionSeeder::class);
    }

    private function loginAs(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_user_with_settings_view_can_list_settings_with_metadata(): void
    {
        $viewer = User::factory()->create()->assignRole('viewer');
        $this->loginAs($viewer);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('0.key', 'user_creation_mode')
            ->assertJsonPath('0.type', 'select')
            ->assertJsonPath('0.value', 'choice')
            ->assertJsonPath('0.options', ['choice', 'invite', 'set_password']);
    }

    public function test_user_without_settings_view_is_forbidden(): void
    {
        $user = User::factory()->create(); // no roles
        $this->loginAs($user);

        $this->getJson('/api/settings')->assertForbidden();
    }

    public function test_user_without_settings_manage_cannot_update(): void
    {
        $viewer = User::factory()->create()->assignRole('viewer'); // has view, not manage
        $this->loginAs($viewer);

        $this->putJson('/api/settings/user_creation_mode', ['value' => 'invite'])
            ->assertForbidden();

        $this->assertSame('choice', app(Settings::class)->get(Setting::UserCreationMode));
    }

    public function test_admin_can_update_a_setting_value(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->putJson('/api/settings/user_creation_mode', ['value' => 'set_password'])
            ->assertOk()
            ->assertJsonPath('value', 'set_password');

        $this->assertSame('set_password', app(Settings::class)->get(Setting::UserCreationMode));
        // The change is reflected in the UI config endpoint too.
        $this->getJson('/api/config')->assertJsonPath('userCreationMode', 'set_password');
    }

    public function test_an_invalid_value_is_rejected(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->putJson('/api/settings/user_creation_mode', ['value' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');

        $this->assertSame('choice', app(Settings::class)->get(Setting::UserCreationMode));
    }

    public function test_an_unknown_setting_key_is_not_found(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->loginAs($admin);

        $this->putJson('/api/settings/made_up_key', ['value' => 'whatever'])
            ->assertNotFound();
    }
}
