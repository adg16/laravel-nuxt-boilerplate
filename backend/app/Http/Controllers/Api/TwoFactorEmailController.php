<?php

namespace App\Http\Controllers\Api;

use App\Enums\TwoFactorMethod;
use App\Enums\TwoFactorMode;
use App\Http\Controllers\Controller;
use App\Services\Settings;
use App\Services\TwoFactorEmailCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * Self-service enrollment for email-based 2FA — the parallel to Fortify's TOTP
 * `/user/two-factor-*` endpoints. Enable generates recovery codes and emails a
 * confirmation code (leaving the setup unconfirmed); confirm activates it once
 * the user proves they received the code. Gated by both the `two_factor_mode`
 * (Off blocks all enrollment) and `two_factor_methods` (must permit Email)
 * settings.
 */
class TwoFactorEmailController extends Controller
{
    /**
     * Begin email enrollment: record the method, mint recovery codes, and email
     * a confirmation code. Returns the recovery codes so the user can save them.
     */
    public function store(Request $request, Settings $settings, TwoFactorEmailCode $codes): JsonResponse
    {
        $this->guardEmailAllowed($settings);

        $user = $request->user();

        $user->forceFill([
            'two_factor_method' => TwoFactorMethod::Email->value,
            // Email 2FA has no shared secret; codes are delivered per-challenge.
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(
                Collection::times(8, fn () => RecoveryCode::generate())->all()
            )),
            // Not enabled until the emailed code is confirmed.
            'two_factor_confirmed_at' => null,
        ])->save();

        $codes->send($user, TwoFactorEmailCode::PURPOSE_ENROLL);

        return response()->json([
            'recovery_codes' => json_decode(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true),
        ]);
    }

    /**
     * Activate the pending email setup once the user submits the emailed code.
     */
    public function confirm(Request $request, TwoFactorEmailCode $codes): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        abort_unless($user->twoFactorMethod() === TwoFactorMethod::Email, 400);

        if (! $codes->verify($user, TwoFactorEmailCode::PURPOSE_ENROLL, (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => [__('two_factor.invalid_code')]]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return response()->json(['message' => __('two_factor.enabled')]);
    }

    /**
     * Re-send the enrollment code (a fresh one, invalidating the previous).
     */
    public function resend(Request $request, TwoFactorEmailCode $codes): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->twoFactorMethod() === TwoFactorMethod::Email, 400);

        $codes->send($user, TwoFactorEmailCode::PURPOSE_ENROLL);

        return response()->json(['message' => __('two_factor.code_sent')]);
    }

    private function guardEmailAllowed(Settings $settings): void
    {
        abort_if($settings->twoFactorMode() === TwoFactorMode::Off, 403, __('auth.two_factor_unavailable'));
        abort_unless(
            $settings->twoFactorMethodPolicy()->permits(TwoFactorMethod::Email),
            403,
            __('auth.two_factor_method_not_allowed'),
        );
    }
}
