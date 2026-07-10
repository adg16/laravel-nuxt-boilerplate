<?php

namespace App\Actions\Fortify;

use App\Enums\TwoFactorMethod;
use App\Enums\TwoFactorMode;
use App\Models\User;
use App\Services\Settings;
use App\Services\TwoFactorEmailCode;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * The login-pipeline step that diverts an enrolled user to a two-factor
 * challenge. We override it for two reasons:
 *  - when `two_factor_mode` is Off, 2FA is dormant (log straight in);
 *  - Fortify only challenges TOTP users (those with a `two_factor_secret`), so
 *    email-2FA users — who have no secret — would otherwise sail through
 *    `AttemptToAuthenticate` and be logged in without a second factor. We
 *    intercept them here, email a code, and return the same challenge marker.
 *
 * Bound to the RedirectsIfTwoFactorAuthenticatable contract in
 * FortifyServiceProvider (that's the key the login pipeline resolves).
 */
class RedirectIfTwoFactorAuthenticatable extends FortifyRedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        if (app(Settings::class)->twoFactorMode() === TwoFactorMode::Off) {
            return $next($request);
        }

        // validateCredentials (parent, protected) resolves the user via our
        // authenticateUsing callback — preserving the 401-on-bad-credentials
        // contract — and throws on failure before we get here.
        $user = $this->validateCredentials($request);

        if ($user instanceof User && $user->hasTwoFactorEnabled()) {
            if ($user->twoFactorMethod() === TwoFactorMethod::Email) {
                return $this->emailChallengeResponse($request, $user);
            }

            // TOTP (or a legacy confirmed user with a secret but no recorded
            // method) — hand back to Fortify's TOTP challenge.
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    /**
     * Stash the pending user (Fortify's session convention), email a fresh code,
     * and return the challenge marker — tagged so the SPA shows the email UI.
     */
    protected function emailChallengeResponse($request, User $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        app(TwoFactorEmailCode::class)->send($user, TwoFactorEmailCode::PURPOSE_LOGIN);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return response()->json(['two_factor' => true, 'two_factor_method' => 'email']);
    }
}
