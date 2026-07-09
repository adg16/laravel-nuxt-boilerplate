<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Bound to BOTH the success and failure forgot-password contracts so the two
 * outcomes are indistinguishable: whether or not the email matched an account,
 * the caller gets the same 200 + generic message. This is what prevents the
 * endpoint from being used to enumerate registered addresses.
 *
 * Fortify resolves these with a `status` argument (the broker status string);
 * we accept and ignore it, since the whole point is to not leak it.
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status = '') {}

    public function toResponse($request): JsonResponse
    {
        return response()->json(['message' => __('passwords.reset_link_generic')]);
    }
}
