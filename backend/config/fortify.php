<?php

use App\Http\Middleware\SetLocale;
use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    | (Unused: this is a headless SPA — Fortify returns JSON, never redirects.)
    |
    */

    'home' => '/',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    | Prefixed with `api` so every Fortify route (login, logout,
    | forgot/reset-password) lands under `/api/*` — the only path the
    | same-origin nginx routes to PHP-FPM (everything else serves the SPA).
    |
    */

    'prefix' => 'api',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    | `web` gives Fortify the session + CSRF stack that Sanctum's cookie SPA
    | auth relies on. `SetLocale` localizes JSON responses from Accept-Language
    | (Fortify routes aren't in the `api` group, so it must be added here).
    | `throttle:6,1` rate-limits the auth surface per-route/IP (Laravel keys the
    | limiter by route), matching the old hand-rolled `throttle:6,1` on these
    | endpoints and blunting credential/token brute-force.
    |
    */

    'middleware' => ['web', SetLocale::class, 'throttle:6,1'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    | Left null — the `throttle:6,1` group middleware above already rate-limits
    | every auth route (including login), so no separate named limiter is wired.
    |
    */

    'limiters' => [
        'login' => null,
        'two-factor' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    'views' => false,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    | Login/logout (always registered), password reset, two-factor, and
    | self-service profile/password updates are enabled. Profile/password
    | self-service edits the signed-in user's *own* record (name, email, password)
    | from the /profile page; admin account management still goes through the
    | permissioned UserController. Public self-registration and email verification
    | stay off — this is an internal backoffice tool, so accounts are created by
    | admins (UserController) or via the invitation flow, never self-signup.
    |
    | Two-factor is registered here *unconditionally* (routes always exist).
    | Whether it actually applies — off / optional / required — is a runtime
    | decision read from the `two_factor_mode` setting at request time (see
    | App\Actions\Fortify\RedirectIfTwoFactorAuthenticatable and
    | EnsureTwoFactorEnrolled). Gating it via this config array instead would
    | freeze at boot, since the prod entrypoint runs `config:cache`.
    |
    | `confirm => true` makes a user verify a TOTP code before 2FA activates
    | (prevents self-lockout — only `two_factor_confirmed_at` users count as
    | enabled). `confirmPassword => false` skips Fortify's `password.confirm`
    | session gate on the manage endpoints (it would need a bespoke confirm-
    | password flow in the headless SPA); flip it on for extra hardening.
    |
    */

    'features' => [
        Features::resetPasswords(),
        // Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false]),
    ],

];
