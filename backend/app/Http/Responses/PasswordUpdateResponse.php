<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;

/**
 * Headless password-update response: a 200 with a localized confirmation message
 * (Fortify's default is an empty 200), matching the `{ message }` shape the SPA
 * surfaces as a toast.
 */
class PasswordUpdateResponse implements PasswordUpdateResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json(['message' => __('auth.password_updated')]);
    }
}
