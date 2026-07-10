<?php

namespace App\Enums;

/**
 * Which two-factor methods an admin permits users to enroll (the
 * `two_factor_methods` setting). `Both` lets each user choose; `Totp`/`Email`
 * force a single method app-wide. Separate from `two_factor_mode` (off /
 * optional / required), which decides *whether* 2FA applies at all.
 */
enum TwoFactorMethodPolicy: string
{
    case Totp = 'totp';
    case Email = 'email';
    case Both = 'both';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Whether the given method is allowed under this policy.
     */
    public function permits(TwoFactorMethod $method): bool
    {
        return match ($this) {
            self::Both => true,
            self::Totp => $method === TwoFactorMethod::Totp,
            self::Email => $method === TwoFactorMethod::Email,
        };
    }
}
