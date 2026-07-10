<?php

namespace App\Enums;

/**
 * How two-factor authentication applies app-wide (see the `two_factor_mode`
 * setting). `Off` disables 2FA entirely (no enrollment, no challenges);
 * `Optional` lets each user enable it for themselves; `Required` forces every
 * user to enroll before they can use the app.
 */
enum TwoFactorMode: string
{
    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
