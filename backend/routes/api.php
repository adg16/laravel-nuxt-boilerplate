<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TwoFactorEmailChallengeController;
use App\Http\Controllers\Api\TwoFactorEmailController;
use App\Http\Controllers\Api\TwoFactorRecoveryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

// login / register / logout / forgot-password / reset-password are registered
// by Laravel Fortify (prefixed with `api`, rate-limited + localized via
// config/fortify.php). The invitation-acceptance flow is bespoke, so it stays
// here — same 6/min-per-IP throttle to blunt token guessing.
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/accept-invitation', [InvitationController::class, 'accept']);

});

// Email-2FA login challenge — the guest counterpart to Fortify's
// /two-factor-challenge (hence the same `guest` guard). Reads the pending user
// from the session the login pipeline stashed. Its own named limiter (6/min/IP)
// keeps a fumbled code from eating into the shared login/invite throttle.
Route::middleware(['guest:'.config('fortify.guard'), 'throttle:two-factor-email'])->group(function () {
    Route::post('/two-factor-email-challenge', [TwoFactorEmailChallengeController::class, 'store']);
    Route::post('/two-factor-email-challenge/resend', [TwoFactorEmailChallengeController::class, 'resend']);
});

// EnsureActive cuts off a deactivated user's live session across the whole
// authenticated surface (including /user), so an admin's deactivation takes
// effect on the target's very next request.
Route::middleware(['auth:sanctum', EnsureActive::class])->group(function () {
    // Always reachable so the SPA can hydrate, read UI config, and (when
    // two_factor_mode = required) reach the self-service enrollment flow. The
    // enrollment gate below must never cover these.
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/config', ConfigController::class);

    // Self-service 2FA that must stay reachable even under required mode (a user
    // has to be able to enroll) — so, like the Fortify /user/two-factor-* routes,
    // these sit OUTSIDE the EnsureTwoFactorEnrolled gate below. Throttled to blunt
    // email flooding / code guessing.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('user/two-factor-email', [TwoFactorEmailController::class, 'store']);
        Route::post('user/two-factor-email/confirm', [TwoFactorEmailController::class, 'confirm']);
        Route::post('user/two-factor-email/resend', [TwoFactorEmailController::class, 'resend']);
    });
    // Method-agnostic recovery codes (Fortify's require a TOTP secret email
    // users lack).
    Route::get('user/two-factor/recovery-codes', [TwoFactorRecoveryController::class, 'index']);
    Route::post('user/two-factor/recovery-codes', [TwoFactorRecoveryController::class, 'store']);

    // Self-service profile avatar. Upload/remove sit outside the enrollment gate
    // (like profile/password) so the account stays editable, and are throttled
    // like the other self-service mutations (Fortify's profile/password + the
    // 2FA-email routes) to blunt upload spam. The image itself is served auth-only
    // with no permission gate so avatars render in the app bar / user list — but
    // still honoring the protected-account visibility rules (see the controller).
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('user/avatar', [AvatarController::class, 'store']);
        Route::delete('user/avatar', [AvatarController::class, 'destroy']);
    });
    Route::get('users/{user}/avatar', [AvatarController::class, 'show']);

    // Every management endpoint is gated here at the route level — this file is
    // the single place to audit "who can call what". Reads require *.view,
    // writes *.manage. (Payload/record-specific rules — e.g. can't delete the
    // last super-admin — live in the controllers, not here.)
    //
    // EnsureTwoFactorEnrolled additionally blocks these when two_factor_mode is
    // Required and the user hasn't set up 2FA yet — the app is off-limits until
    // they enroll (the Fortify /user/two-factor-* routes stay reachable).
    Route::middleware(EnsureTwoFactorEnrolled::class)->group(function () {

        // User management.
        Route::middleware('permission:'.Permission::UsersView->value)->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::get('users/{user}', [UserController::class, 'show']);
        });
        Route::middleware('permission:'.Permission::UsersManage->value)->group(function () {
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
            Route::delete('users/{user}/two-factor', [UserController::class, 'resetTwoFactor']);
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate']);
            Route::post('users/{user}/activate', [UserController::class, 'activate']);
            Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite']);
        });

        // Role management.
        Route::middleware('permission:'.Permission::RolesView->value)->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('roles/{role}', [RoleController::class, 'show']);
            // Read-only permission catalog — only consumed by the role editor and
            // its permission filter, so it rides on the same view permission.
            Route::get('permissions', PermissionController::class);
        });
        Route::middleware('permission:'.Permission::RolesManage->value)->group(function () {
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
        });

        // Application settings — code-defined keys, editable values.
        Route::middleware('permission:'.Permission::SettingsView->value)
            ->get('settings', [SettingController::class, 'index']);
        Route::middleware('permission:'.Permission::SettingsManage->value)
            ->put('settings/{setting}', [SettingController::class, 'update']);

    }); // EnsureTwoFactorEnrolled
});
