<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

// Authorization is handled by the `permission:roles.manage` route middleware.
class StoreRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
