<?php

namespace App\Enums;

/**
 * The concrete second factor a user has enrolled. TOTP is an authenticator app
 * (Fortify's built-in, secret + QR); Email delivers a one-time code to the
 * user's inbox. A user has exactly one active method (see the users table's
 * `two_factor_method` column); which methods may be chosen is constrained by the
 * `two_factor_methods` setting (see TwoFactorMethodPolicy).
 */
enum TwoFactorMethod: string
{
    case Totp = 'totp';
    case Email = 'email';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
