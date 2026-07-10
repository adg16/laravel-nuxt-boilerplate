<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Login / logout / password-reset are handled by Laravel Fortify
 * (see FortifyServiceProvider + config/fortify.php). All that remains here is
 * the "who am I" endpoint the SPA calls to hydrate the auth store.
 */
class AuthController extends Controller
{
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
