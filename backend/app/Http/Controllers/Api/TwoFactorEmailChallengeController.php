<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TwoFactorEmailCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Completes an email-2FA login. After a correct password, the login pipeline
 * (RedirectIfTwoFactorAuthenticatable) stashed the pending user in the session
 * and emailed a code; this verifies that code (or a recovery code) and
 * establishes the session — the email counterpart to Fortify's
 * `/two-factor-challenge`.
 */
class TwoFactorEmailChallengeController extends Controller
{
    public function store(Request $request, TwoFactorEmailCode $codes): JsonResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ]);

        $user = $this->challengedUser($request);

        $valid = ($recovery = (string) $request->input('recovery_code', ''))
            ? $this->consumeRecoveryCode($user, $recovery)
            : $codes->verify($user, TwoFactorEmailCode::PURPOSE_LOGIN, (string) $request->input('code', ''));

        if (! $valid) {
            throw ValidationException::withMessages(['code' => [__('two_factor.invalid_code')]]);
        }

        $guard = auth()->guard(config('fortify.guard'));
        $guard->login($user, (bool) $request->session()->pull('login.remember', false));

        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return UserResource::make($user)->response();
    }

    public function resend(Request $request, TwoFactorEmailCode $codes): JsonResponse
    {
        $codes->send($this->challengedUser($request), TwoFactorEmailCode::PURPOSE_LOGIN);

        return response()->json(['message' => __('two_factor.code_sent')]);
    }

    /**
     * The user mid-challenge, from the session id Fortify's pipeline stashed.
     */
    private function challengedUser(Request $request): User
    {
        $id = $request->session()->get('login.id');
        $user = $id ? User::find($id) : null;

        if (! $user) {
            throw ValidationException::withMessages(['code' => [__('two_factor.challenge_expired')]]);
        }

        return $user;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        if (! in_array($code, $user->recoveryCodes(), true)) {
            return false;
        }

        $user->replaceRecoveryCode($code);

        return true;
    }
}
