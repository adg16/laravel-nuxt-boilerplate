<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * Determines who can access the Horizon dashboard in non-local
     * environments. The dashboard authenticates via the same-origin Sanctum
     * `web` session, so a signed-in Super Admin's cookie authorizes them here.
     * (`Gate::before` in AppServiceProvider already grants Super Admins every
     * ability; this explicit check keeps the gate correct on its own.)
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => (bool) $user?->hasRole('Super Admin'));
    }
}
