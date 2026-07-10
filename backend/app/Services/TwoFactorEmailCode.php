<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and verifies the one-time codes for email-based 2FA. Codes are 6-digit,
 * short-lived, and stored only as a hash in the cache (never in the DB), keyed by
 * user + purpose so an enrollment code can't satisfy a login challenge and vice
 * versa. A small attempt cap blunts online brute-force; the short TTL bounds it
 * further.
 */
class TwoFactorEmailCode
{
    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public const PURPOSE_ENROLL = 'enroll';

    public const PURPOSE_LOGIN = 'login';

    /**
     * Generate a fresh code, remember its hash, and email it to the user.
     */
    public function send(User $user, string $purpose): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            $this->key($user, $purpose),
            ['hash' => Hash::make($code), 'attempts' => 0],
            now()->addMinutes(self::TTL_MINUTES),
        );

        $user->notify(new TwoFactorCodeNotification($code));
    }

    /**
     * Check a submitted code. Consumes the code on success; enforces the attempt
     * cap (dropping the code once exceeded, so a fresh one must be requested).
     */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $key = $this->key($user, $purpose);
        $entry = Cache::get($key);

        if ($entry === null) {
            return false;
        }

        if (Hash::check($code, $entry['hash'])) {
            Cache::forget($key);

            return true;
        }

        $entry['attempts']++;
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
        } else {
            Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));
        }

        return false;
    }

    public function forget(User $user, string $purpose): void
    {
        Cache::forget($this->key($user, $purpose));
    }

    private function key(User $user, string $purpose): string
    {
        return "2fa-email:{$purpose}:{$user->getKey()}";
    }
}
