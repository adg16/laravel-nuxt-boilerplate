<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts off a signed-in user the moment they're deactivated: the login block in
 * FortifyServiceProvider only stops *new* sign-ins, so this guards the live
 * session an admin deactivates out from under. Wraps every authenticated API
 * route (the whole auth:sanctum group, including /user) so the SPA's next
 * hydration fails and it bounces the user to /login.
 *
 * The machine-readable `code` lets any direct API caller distinguish this from
 * an ordinary 403.
 */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            return response()->json([
                'message' => __('auth.account_deactivated'),
                'code' => 'account_deactivated',
            ], 403);
        }

        return $next($request);
    }
}
