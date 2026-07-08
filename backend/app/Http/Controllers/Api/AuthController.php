<?php

namespace App\Http\Controllers\Api;

use App\Enums\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, Settings $settings): JsonResponse
    {
        abort_unless($settings->bool(Setting::RegistrationEnabled), 403, __('auth.registration_disabled'));

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
        ]);

        Auth::login($user);

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw new AuthenticationException('These credentials do not match our records.');
        }

        $request->session()->regenerate();

        return UserResource::make(Auth::user());
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Always report success regardless of the broker's status so this
        // endpoint can't be used to enumerate which emails have accounts.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            // A mail transport failure must not surface as a 500 (it would also
            // leak that the address exists). Log it for diagnosis — without the
            // email/token — and still return the generic message below.
            Log::error('Failed to send password reset link.', ['exception' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'If that email address is in our system, a reset link is on its way.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $user, string $password) {
                /** @var User $user */
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => __($status)]);
    }
}
