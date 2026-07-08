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
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            // Protected accounts (super-admin / System) can't be edited or
            // deleted — the UI disables their row actions off this flag.
            'is_protected' => $this->isProtected(),
            // Whether the user has accepted their invitation and set a password.
            'is_verified' => $this->isVerified(),
            'created_at' => $this->created_at,
        ];
    }
}
