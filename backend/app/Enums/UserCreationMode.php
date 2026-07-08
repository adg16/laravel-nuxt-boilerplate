<?php

namespace App\Enums;

/**
 * How a new user gets access. `Choice` lets the admin pick per user; `Invite`
 * and `SetPassword` force one path app-wide (see the `user_creation_mode`
 * setting).
 */
enum UserCreationMode: string
{
    case Choice = 'choice';
    case Invite = 'invite';
    case SetPassword = 'set_password';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * The concrete per-user methods (everything except the "let the admin
     * choose" meta-value) — the options offered in the create-user form.
     *
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return [self::Invite->value, self::SetPassword->value];
    }
}
