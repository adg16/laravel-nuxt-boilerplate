<?php

namespace App\Enums;

use Illuminate\Validation\Rule;

/**
 * The input control a setting maps to in the Settings UI — and the source of
 * that setting's value handling: validation rules, how a stored string is cast
 * back to a typed value, and how a typed value is serialized for storage. A new
 * setting picks a type and inherits all three for free.
 */
enum SettingType: string
{
    case Select = 'select';
    case Toggle = 'toggle';
    case Text = 'text';

    /**
     * Validation rules for an incoming value, derived from the type (+ the
     * allowed options for a Select).
     *
     * @param  array<int, string>  $options
     * @return array<int, mixed>
     */
    public function rules(array $options): array
    {
        return match ($this) {
            self::Select => ['required', Rule::in($options)],
            self::Toggle => ['required', 'boolean'],
            self::Text => ['present', 'string'],
        };
    }

    /**
     * Cast the stored string back to its typed value.
     */
    public function cast(string $stored): string|bool
    {
        return match ($this) {
            self::Toggle => filter_var($stored, FILTER_VALIDATE_BOOLEAN),
            self::Select, self::Text => $stored,
        };
    }

    /**
     * Serialize a typed value to the string kept in the database.
     */
    public function serialize(mixed $value): string
    {
        return match ($this) {
            self::Toggle => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::Select, self::Text => (string) $value,
        };
    }
}
