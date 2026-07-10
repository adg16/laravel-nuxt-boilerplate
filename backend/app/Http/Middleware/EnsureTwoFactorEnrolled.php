<?php

namespace App\Http\Middleware;

use App\Enums\TwoFactorMode;
use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the `two_factor_mode` = Required policy server-side: a signed-in user
 * who hasn't enrolled in 2FA is blocked from the management API until they do.
 * The SPA drives its own forced-setup redirect off the config + user flags (see
 * middleware/auth.global.ts); the machine-readable `code` here is defense in
 * depth for any direct API caller that bypasses the client guard.
 *
 * Wrap only the management routes with this — never /user, /config, the Fortify
 * two-factor endpoints, or logout, so the user can always hydrate, read config,
 * enroll, and sign out.
 */
class EnsureTwoFactorEnrolled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->settings->twoFactorMode() === TwoFactorMode::Required
            && $user !== null
            && ! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('auth.two_factor_setup_required'),
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
