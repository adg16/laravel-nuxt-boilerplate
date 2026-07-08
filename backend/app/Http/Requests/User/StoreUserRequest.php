<?php

namespace App\Http\Requests\User;

use App\Enums\UserCreationMode;
use App\Services\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// Authorization is handled by the `permission:users.manage` route middleware.
class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];

        // Only honor a per-user `method` when the app is configured for the
        // admin to choose; otherwise the mode is fixed server-side and the
        // field is ignored.
        if ($this->configuredMode() === UserCreationMode::Choice) {
            $rules['method'] = ['sometimes', 'string', Rule::in(UserCreationMode::methods())];
        }

        // A password is required only when the effective method is set-password.
        if ($this->creationMethod() === UserCreationMode::SetPassword) {
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        return $rules;
    }

    /**
     * The effective per-user creation method, derived server-side: a fixed mode
     * wins; under `choice`, honor the request's `method` (defaulting to invite).
     */
    public function creationMethod(): UserCreationMode
    {
        $mode = $this->configuredMode();

        if ($mode !== UserCreationMode::Choice) {
            return $mode;
        }

        return UserCreationMode::tryFrom((string) $this->input('method')) === UserCreationMode::SetPassword
            ? UserCreationMode::SetPassword
            : UserCreationMode::Invite;
    }

    private function configuredMode(): UserCreationMode
    {
        return app(Settings::class)->userCreationMode();
    }
}
