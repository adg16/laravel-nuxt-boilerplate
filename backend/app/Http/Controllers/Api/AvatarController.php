<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Self-service profile avatar: the signed-in user uploads/removes their own
 * image (`/user/avatar`), and any authenticated user can fetch a user's avatar
 * image (`/users/{user}/avatar`) so it renders in the app bar / user list.
 *
 * Images are stored on the configured avatar disk (User::avatarDisk() — `local`
 * by default, MinIO/`s3` in dev, real S3 in prod) and streamed back through this
 * controller regardless of disk. The browser never contacts the store directly,
 * so no bucket is public and no CORS is needed; it also means the same-origin
 * nginx (which only routes /api|sanctum|up to PHP) can serve it. No server-side
 * resizing (the php image ships without GD), so uploads are capped by validation.
 */
class AvatarController extends Controller
{
    private const MAX_KILOBYTES = 2048;

    /**
     * Upload or replace the signed-in user's avatar.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ]);

        $user = $request->user();

        // Remove the previous file first so replaced avatars don't accumulate.
        $user->deleteAvatar();

        $path = $request->file('avatar')->store('avatars', User::avatarDisk());

        $user->forceFill(['avatar_path' => $path])->save();

        return UserResource::make($user)->response();
    }

    /**
     * Remove the signed-in user's avatar.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->deleteAvatar();
        $user->forceFill(['avatar_path' => null])->save();

        return UserResource::make($user)->response();
    }

    /**
     * Stream a user's avatar image. Auth-only (no permission gate) so avatars
     * render wherever they're shown; 404 when unset. Honors the same protected-
     * account visibility as UserController::show so a non-super-admin can't pull a
     * hidden account's avatar by guessing its id.
     */
    public function show(Request $request, User $user): StreamedResponse
    {
        if ($user->isRestrictedToSuperAdmins() && ! $request->user()->hasRole('Super Admin')) {
            abort(404);
        }

        abort_unless(
            $user->avatar_path && Storage::disk(User::avatarDisk())->exists($user->avatar_path),
            404
        );

        return Storage::disk(User::avatarDisk())->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
