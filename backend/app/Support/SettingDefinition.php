<?php

namespace App\Support;

use App\Enums\SettingType;

/**
 * The full description of one setting, returned by Setting::definition(). Holds
 * everything about a setting in one place (so adding a setting is a single
 * match arm) and delegates value handling to its SettingType.
 */
final class SettingDefinition
{
    /**
     * @param  array<int, string>  $options  allowed values for a Select
     */
    public function __construct(
        public readonly SettingType $type,
        public readonly string|bool|int $default,
        public readonly string $group,
        public readonly array $options = [],
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return $this->type->rules($this->options);
    }

    public function cast(string $stored): string|bool
    {
        return $this->type->cast($stored);
    }

    public function serialize(mixed $value): string
    {
        return $this->type->serialize($value);
    }
}
