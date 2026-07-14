<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasBlameStamps;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    use HasBlameStamps;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->permissions->pluck('name')->values(),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Who created / last updated the role, as { id, name } or null.
            // Restricted (super-admin / System) actors are redacted to null for
            // non-super-admin viewers — see HasBlameStamps.
            'created_by' => $this->blameStamp($this->creator, $request),
            'updated_by' => $this->blameStamp($this->updater, $request),
        ];
    }
}
