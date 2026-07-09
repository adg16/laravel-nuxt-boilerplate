<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Headless registration response: Fortify has already logged the new user in,
 * so return them (201) exactly like the old AuthController::register did.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse
    {
        /** @var Request $request */
        return UserResource::make($request->user())->response()->setStatusCode(201);
    }
}
