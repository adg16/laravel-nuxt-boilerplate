<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AcceptInvitationRequest;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    /**
     * Public endpoint the invite e-mail links to: the invitee sets their
     * password, which verifies their account and consumes the invitation.
     */
    public function accept(AcceptInvitationRequest $request, InvitationService $invitations): JsonResponse
    {
        $invitations->accept(
            $request->string('email'),
            $request->string('token'),
            $request->string('password'),
        );

        return response()->json(['message' => __('invitation.accepted')]);
    }
}
