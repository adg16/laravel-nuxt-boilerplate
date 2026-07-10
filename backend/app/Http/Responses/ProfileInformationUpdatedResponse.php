<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse as ProfileInformationUpdatedResponseContract;

/**
 * Headless profile-update response: return the freshly updated user (as the SPA
 * expects) so the auth store can refresh in place, instead of Fortify's default
 * empty 200.
 */
class ProfileInformationUpdatedResponse implements ProfileInformationUpdatedResponseContract
{
    public function toResponse($request): JsonResponse
    {
        /** @var Request $request */
        return UserResource::make($request->user())->response();
    }
}
