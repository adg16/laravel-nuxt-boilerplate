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
            // Who created / last updated the role, as { id, name } or null
            // (null when there's no actor — seeded / automated writes).
            'created_by' => $this->blameStamp($this->creator),
            'updated_by' => $this->blameStamp($this->updater),
        ];
    }
}
