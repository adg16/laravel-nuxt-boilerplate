<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Dedicated limiter for the email-2FA login challenge + resend, so those
        // guest requests get their own 6/min-per-IP budget instead of sharing the
        // unnamed `throttle:6,1` bucket (which Laravel keys by domain+IP) with
        // login and invitation acceptance — a fumbling code entry shouldn't
        // exhaust an unrelated flow's limit.
        RateLimiter::for('two-factor-email', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

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
