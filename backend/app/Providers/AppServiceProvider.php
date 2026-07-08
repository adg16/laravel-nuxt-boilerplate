<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // The `super-admin` role bypasses every permission check. Returning null
        // (not false) for everyone else lets normal gate/policy resolution — and
        // the explicit permissions on ordinary roles like `admin` — proceed.
        Gate::before(fn ($user) => $user?->hasRole('super-admin') ? true : null);

        // Password-reset emails must link to the SPA reset page (same origin
        // as the API — nginx fronts both), not a backend Blade route.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return rtrim(config('app.url'), '/')."/reset-password?{$query}";
        });
    }
}
