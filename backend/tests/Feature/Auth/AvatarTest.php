<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests it recognizes as coming
        // from a stateful (first-party SPA) frontend — tests must look the part.
        $this->withHeader('Referer', config('app.url'));

        Storage::fake(User::avatarDisk());
    }

    public function test_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/avatar', [
                'avatar' => UploadedFile::fake()->create('me.png', 200, 'image/png'),
            ])
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk(User::avatarDisk())->assertExists($path);

        // The resource exposes a served URL rather than the raw disk path.
        $this->getJson('/api/user')->assertJsonPath('avatar_url', fn ($url) => is_string($url) && str_contains($url, '/avatar'));
    }

    public function test_uploading_replaces_and_deletes_the_previous_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('first.png', 100, 'image/png'),
        ])->assertOk();
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('second.png', 100, 'image/png'),
        ])->assertOk();
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk(User::avatarDisk())->assertMissing($first);
        Storage::disk(User::avatarDisk())->assertExists($second);
    }

    public function test_upload_rejects_non_images(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/avatar', [
                'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_upload_rejects_files_over_the_size_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/avatar', [
                'avatar' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_user_can_remove_their_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('me.png', 100, 'image/png'),
        ])->assertOk();
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)
            ->deleteJson('/api/user/avatar')
            ->assertOk()
            ->assertJsonPath('avatar_url', null);

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk(User::avatarDisk())->assertMissing($path);
    }

    public function test_any_authenticated_user_can_view_an_avatar(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('me.png', 100, 'image/png'),
        ])->assertOk();

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/api/users/'.$owner->id.'/avatar')
            ->assertOk();
    }

    public function test_protected_account_avatar_is_hidden_from_non_super_admins(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($super)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('me.png', 100, 'image/png'),
        ])->assertOk();

        // A non-super-admin can't pull a protected account's avatar even by id
        // (mirrors UserController::show's visibility rule).
        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->get('/api/users/'.$super->id.'/avatar')
            ->assertNotFound();

        // A super-admin can.
        $otherSuper = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($otherSuper)
            ->get('/api/users/'.$super->id.'/avatar')
            ->assertOk();
    }

    public function test_viewing_a_missing_avatar_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/api/users/'.$user->id.'/avatar')
            ->assertNotFound();
    }

    public function test_deleting_a_user_removes_their_avatar_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('me.png', 100, 'image/png'),
        ])->assertOk();
        $path = $user->fresh()->avatar_path;

        $user->delete();

        Storage::disk(User::avatarDisk())->assertMissing($path);
    }

    public function test_guest_cannot_upload_an_avatar(): void
    {
        $this->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('me.png', 100, 'image/png'),
        ])->assertUnauthorized();
    }
}
