<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Headless logout response: a 200 with a localized message (Fortify's default
 * is an empty 204), preserving the shape the SPA has always received.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json(['message' => __('auth.logged_out')]);
    }
}
