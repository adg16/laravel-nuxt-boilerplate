<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Method-agnostic recovery-code access for the signed-in user. Fortify's own
 * recovery-code endpoint requires a `two_factor_secret`, so it returns nothing
 * for email users (who have none) — this one keys off the recovery-codes column
 * itself, serving TOTP and email alike.
 */
class TwoFactorRecoveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_recovery_codes) {
            return response()->json([]);
        }

        return response()->json(
            json_decode(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true)
        );
    }

    public function store(Request $request, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $user = $request->user();
        // Only regenerate for an enrolled user (a code set already exists).
        abort_unless((bool) $user->two_factor_recovery_codes, 400);

        $generate($user);

        return response()->json(
            json_decode(Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_recovery_codes), true)
        );
    }
}
