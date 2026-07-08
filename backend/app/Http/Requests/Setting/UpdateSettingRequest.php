<?php

namespace App\Http\Requests\Setting;

use App\Enums\Setting;
use Illuminate\Foundation\Http\FormRequest;

// Authorization is handled by the `permission:settings.manage` route middleware.
class UpdateSettingRequest extends FormRequest
{
    /**
     * The value is validated with the target setting's own rules, so each
     * setting constrains its allowed values (e.g. user_creation_mode must be one
     * of its options). Unknown keys are rejected in the controller (404).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $setting = Setting::tryFrom((string) $this->route('setting'));

        return [
            'value' => $setting?->rules() ?? ['required'],
        ];
    }
}
