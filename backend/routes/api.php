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

// Rate-limit the unauthenticated auth endpoints to blunt credential brute-force
// and reset-token guessing (6 requests/minute per client IP).
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/accept-invitation', [InvitationController::class, 'accept']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
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
