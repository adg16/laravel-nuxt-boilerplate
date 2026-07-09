<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Headless login response: return the authenticated user (as the SPA expects)
 * instead of Fortify's default `{ "two_factor": false }` payload.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse
    {
        /** @var Request $request */
        return UserResource::make($request->user())->response();
    }
}
