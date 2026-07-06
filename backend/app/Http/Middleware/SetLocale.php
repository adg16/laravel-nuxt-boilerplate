<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from the request's Accept-Language header so API
 * responses (validation messages, password-reset status, etc.) are localized.
 *
 * The frontend sends its active locale as Accept-Language (see the SPA's
 * useApi composable). The negotiated locale is constrained to the app's
 * supported_locales; anything unsupported falls back to the first entry, so the
 * app locale is always one we actually have translations for.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', [config('app.locale')]);

        App::setLocale($request->getPreferredLanguage($supported));

        return $next($request);
    }
}
