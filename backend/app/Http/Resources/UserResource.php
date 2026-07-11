<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Same-origin path to the streamed avatar image, or null. The `v=`
            // cache-buster (a hash of the stored path) changes when the avatar
            // does, so the browser refetches instead of showing a stale image.
            'avatar_url' => $this->avatar_path
                ? '/api/users/'.$this->id.'/avatar?v='.substr(md5($this->avatar_path), 0, 8)
                : null,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            // Protected accounts (super-admin / System) can't be edited or
            // deleted — the UI disables their row actions off this flag.
            'is_protected' => $this->isProtected(),
            // Whether the user has accepted their invitation and set a password.
            'is_verified' => $this->isVerified(),
            // Whether the account is active. Deactivated users can't sign in and
            // are cut off from the API; the Users table exposes a toggle.
            'is_active' => $this->isActive(),
            // Whether the user has an active (confirmed) two-factor setup —
            // drives the Security page state and the required-mode gate. Covers
            // both TOTP and email (see User::hasTwoFactorEnabled).
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            // Which second factor is enrolled ('totp' | 'email'), or null.
            'two_factor_method' => $this->twoFactorMethod()?->value,
            'created_at' => $this->created_at,
        ];
    }
}
