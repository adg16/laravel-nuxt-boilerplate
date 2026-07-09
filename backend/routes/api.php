<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

// login / register / logout / forgot-password / reset-password are registered
// by Laravel Fortify (prefixed with `api`, rate-limited + localized via
// config/fortify.php). The invitation-acceptance flow is bespoke, so it stays
// here — same 6/min-per-IP throttle to blunt token guessing.
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/accept-invitation', [InvitationController::class, 'accept']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/config', ConfigController::class);

    // Every management endpoint is gated here at the route level — this file is
    // the single place to audit "who can call what". Reads require *.view,
    // writes *.manage. (Payload/record-specific rules — e.g. can't delete the
    // last super-admin — live in the controllers, not here.)

    // User management.
    Route::middleware('permission:'.Permission::UsersView->value)->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });
    Route::middleware('permission:'.Permission::UsersManage->value)->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite']);
    });

    // Role management.
    Route::middleware('permission:'.Permission::RolesView->value)->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/{role}', [RoleController::class, 'show']);
    });
    Route::middleware('permission:'.Permission::RolesManage->value)->group(function () {
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    });

    // Read-only permission catalog.
    Route::get('permissions', PermissionController::class)
        ->middleware('permission:'.Permission::PermissionsView->value);

    // Application settings — code-defined keys, editable values.
    Route::middleware('permission:'.Permission::SettingsView->value)
        ->get('settings', [SettingController::class, 'index']);
    Route::middleware('permission:'.Permission::SettingsManage->value)
        ->put('settings/{setting}', [SettingController::class, 'update']);
});
