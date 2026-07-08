<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated user-invitation flow (separate from password resets): stores a
 * hashed, expiring token in the `invitations` table and delivers a purpose-made
 * invite e-mail. Accepting an invite is what marks a user as verified
 * (email_verified_at) — i.e. they set their own password.
 */
class InvitationService
{
    /**
     * Issue (or re-issue) an invitation for the user and e-mail it. A new token
     * supersedes any previous one for that address.
     */
    public function send(User $user): void
    {
        $token = Str::random(64);

        DB::table('invitations')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        $user->notify(new UserInvitation($token));
    }

    /**
     * Accept an invitation: validate the token, set the user's chosen password
     * and mark them verified, then consume the invitation.
     *
     * @throws ValidationException when the token is missing, wrong, or expired.
     */
    public function accept(string $email, string $token, string $password): User
    {
        $invitation = DB::table('invitations')->where('email', $email)->first();

        if (! $invitation || ! Hash::check($token, $invitation->token)) {
            throw ValidationException::withMessages(['email' => [__('invitation.invalid')]]);
        }

        if (Carbon::parse($invitation->created_at)->addDays(config('invitation.expire_days'))->isPast()) {
            throw ValidationException::withMessages(['email' => [__('invitation.expired')]]);
        }

        $user = User::where('email', $email)->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        DB::table('invitations')->where('email', $email)->delete();

        return $user;
    }
}
