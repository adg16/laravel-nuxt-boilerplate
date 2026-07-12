<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects the built-in account display names — the original super-admin
 * (`users.default_user.name`, default "Super Admin") and the System account
 * (`app.system_user_name`, default "System") — so no other account can be
 * created or renamed to impersonate them. Case-insensitive, whitespace-trimmed.
 */
class NotReservedName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $candidate = mb_strtolower(trim($value));

        foreach ([config('users.default_user.name'), config('app.system_user_name')] as $reserved) {
            if ($candidate === mb_strtolower(trim((string) $reserved))) {
                // Report the canonical name (config casing), not what was typed.
                $fail('management.name_reserved')->translate(['name' => $reserved]);

                return;
            }
        }
    }
}
