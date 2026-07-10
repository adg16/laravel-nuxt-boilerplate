<?php

namespace App\Providers;

use App\Actions\Fortify\DisableTwoFactorAuthentication;
use App\Actions\Fortify\EnableTwoFactorAuthentication;
use App\Actions\Fortify\RedirectIfTwoFactorAuthenticatable;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Http\Responses\PasswordUpdateResponse;
use App\Http\Responses\ProfileInformationUpdatedResponse;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse as ProfileInformationUpdatedResponseContract;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Headless (JSON) responses in place of Fortify's Blade-oriented
        // defaults. Login returns the user; the two forgot-password outcomes are
        // made indistinguishable for anti-enumeration.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        // A completed two-factor challenge returns its own response type — bind
        // it to the same user payload so the SPA gets the user (not Fortify's
        // default empty 204) whether login was one-step or via a 2FA challenge.
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);

        // Self-service profile/password updates: return the updated user (so the
        // SPA store refreshes in place) and a localized message respectively,
        // instead of Fortify's default empty 200s.
        $this->app->singleton(ProfileInformationUpdatedResponseContract::class, ProfileInformationUpdatedResponse::class);
        $this->app->singleton(PasswordUpdateResponseContract::class, PasswordUpdateResponse::class);

        // Two-factor is registered unconditionally, but the `two_factor_mode`
        // setting decides its behavior at runtime. Override the login-pipeline
        // step so an Off setting skips the challenge, and the enroll action so
        // an Off setting refuses new enrollment. (Bind the contract the pipeline
        // resolves + Fortify's concrete enroll action.)
        $this->app->scoped(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfTwoFactorAuthenticatable::class);
        $this->app->singleton(FortifyEnableTwoFactorAuthentication::class, EnableTwoFactorAuthentication::class);
        // Required mode also can't be turned off from the account: refuse to
        // disable an active setup while the setting demands 2FA.
        $this->app->singleton(FortifyDisableTwoFactorAuthentication::class, DisableTwoFactorAuthentication::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // Authenticate manually so a bad credential set throws a 401
        // (AuthenticationException) — Fortify's default is a 422 validation
        // error. Returning null here would trigger that default, so we throw
        // explicitly to preserve the SPA's long-standing 401 contract.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check((string) $request->password, $user->password)) {
                return $user;
            }

            throw new AuthenticationException(__('auth.failed'));
        });
    }
}
