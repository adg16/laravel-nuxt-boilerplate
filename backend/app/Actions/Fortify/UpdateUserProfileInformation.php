<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\NotReservedName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the signed-in user's own profile information.
     *
     * Email verification is off (see config/fortify.php), so a changed email is
     * applied directly — there's no pending-email dance to handle.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        // The reserved names are off-limits to everyone but the protected accounts
        // that already hold them (and can't change them — see below).
        $nameRules = ['required', 'string', 'max:255'];
        if (! $user->isProtected()) {
            $nameRules[] = new NotReservedName;
        }

        Validator::make($input, [
            'name' => $nameRules,
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ])->validate();

        // A protected account's display name is fixed — they can still change
        // everything else (email, avatar, password), just not their name.
        if ($user->isProtected() && $input['name'] !== $user->name) {
            throw ValidationException::withMessages([
                'name' => [__('management.cannot_change_super_admin_name')],
            ]);
        }

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
        ])->save();
    }
}
