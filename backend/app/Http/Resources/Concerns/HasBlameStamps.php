<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Shared rendering of a blame relation (creator / updater) for resources whose
 * model uses App\Models\Concerns\Blameable.
 */
trait HasBlameStamps
{
    /**
     * Render a blame actor as `{ id, name }`, or null when there's no actor.
     *
     * Redacts to null when the actor is a super-admin or the System account and
     * the viewer isn't a super-admin — otherwise the stamp would leak the
     * existence and name of an account the app deliberately hides from
     * non-super-admins (mirrors User::isRestrictedToSuperAdmins()).
     *
     * @return array{id: int, name: string}|null
     */
    protected function blameStamp(?User $actor, Request $request): ?array
    {
        if (! $actor) {
            return null;
        }

        if ($actor->isRestrictedToSuperAdmins() && ! $request->user()?->hasRole('Super Admin')) {
            return null;
        }

        return ['id' => $actor->id, 'name' => $actor->name];
    }
}
