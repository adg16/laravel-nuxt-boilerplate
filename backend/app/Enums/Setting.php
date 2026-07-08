<?php

namespace App\Enums;

use App\Support\SettingDefinition;

/**
 * The registry of application settings — the single source of truth for which
 * settings exist (like App\Enums\Permission is for permissions). Keys are
 * code-defined here and can't be added or removed from the UI; only their
 * *values* are editable (stored as overrides in the `settings` table, resolved
 * by App\Services\Settings).
 *
 * To add a setting: add a case + a single arm to definition() below (the type
 * decides validation/casting), read it via `app(Settings::class)->get(...)`, and
 * add its i18n labels under `settings.*` on the frontend.
 */
enum Setting: string
{
    case UserCreationMode = 'user_creation_mode';
    case RegistrationEnabled = 'registration_enabled';

    /**
     * The one place a setting is described — type, default, group, and (for a
     * Select) its options. Everything else derives from this.
     */
    public function definition(): SettingDefinition
    {
        return match ($this) {
            self::UserCreationMode => new SettingDefinition(
                type: SettingType::Select,
                default: (string) config('users.creation_mode', UserCreationMode::Choice->value),
                group: 'users',
                options: UserCreationMode::values(),
            ),
            self::RegistrationEnabled => new SettingDefinition(
                type: SettingType::Toggle,
                default: (bool) config('users.registration_enabled', true),
                group: 'authentication',
            ),
        };
    }

    public function type(): SettingType
    {
        return $this->definition()->type;
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return $this->definition()->options;
    }

    public function group(): string
    {
        return $this->definition()->group;
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return $this->definition()->rules();
    }
}
