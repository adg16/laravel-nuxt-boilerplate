<?php

namespace App\Actions\Fortify;

use App\Enums\TwoFactorMode;
use App\Services\Settings;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;

/**
 * Tears down a user's two-factor setup. We override Fortify's action to refuse
 * disabling an *active* (confirmed) setup while `two_factor_mode` is Required —
 * enforcing server-side what the Security page only disables in the UI. A
 * still-unconfirmed secret (a cancelled/half-finished enrollment) is always
 * removable, so `hasEnabledTwoFactorAuthentication()` is the gate, not the mere
 * presence of a secret.
 *
 * Bound to Fortify's concrete action in FortifyServiceProvider.
 */
class DisableTwoFactorAuthentication extends FortifyDisableTwoFactorAuthentication
{
    public function __invoke($user): void
    {
        abort_if(
            app(Settings::class)->twoFactorMode() === TwoFactorMode::Required
                && $user->hasTwoFactorEnabled(),
            403,
            __('auth.two_factor_required'),
        );

        parent::__invoke($user);

        // Fortify's base action clears the secret/recovery/confirmed columns but
        // not our method column.
        $user->clearTwoFactorMethod();
    }
}
