<?php

namespace App\Actions\Fortify;

use App\Enums\TwoFactorMethod;
use App\Enums\TwoFactorMode;
use App\Services\Settings;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;

/**
 * Enrolls a user in TOTP two-factor authentication. We override Fortify's action
 * to refuse enrollment when 2FA is disabled (`two_factor_mode` = Off) or when the
 * `two_factor_methods` policy doesn't permit TOTP — the manage routes always
 * exist (see config/fortify.php), so this is the guard that enforces the policy
 * even against a direct API call. On success it records the method so email and
 * TOTP users are distinguishable.
 *
 * Bound to Fortify's concrete action in FortifyServiceProvider.
 */
class EnableTwoFactorAuthentication extends FortifyEnableTwoFactorAuthentication
{
    public function __invoke($user, $force = false): void
    {
        $settings = app(Settings::class);

        abort_if($settings->twoFactorMode() === TwoFactorMode::Off, 403, __('auth.two_factor_unavailable'));
        abort_unless(
            $settings->twoFactorMethodPolicy()->permits(TwoFactorMethod::Totp),
            403,
            __('auth.two_factor_method_not_allowed'),
        );

        parent::__invoke($user, $force);

        $user->forceFill(['two_factor_method' => TwoFactorMethod::Totp->value])->save();
    }
}
