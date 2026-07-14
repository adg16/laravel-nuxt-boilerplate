<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;

/**
 * Shared rendering of a blame relation (creator / updater) for resources whose
 * model uses App\Models\Concerns\Blameable.
 */
trait HasBlameStamps
{
    /**
     * Render a blame actor as `{ id, name }`, or null when there's no actor
     * (seeded / automated / guest writes leave the column null).
     *
     * @return array{id: int, name: string}|null
     */
    protected function blameStamp(?User $actor): ?array
    {
        return $actor ? ['id' => $actor->id, 'name' => $actor->name] : null;
    }
}
