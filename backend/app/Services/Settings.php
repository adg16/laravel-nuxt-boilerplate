<?php

namespace App\Services;

use App\Enums\Setting;
use App\Enums\UserCreationMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves application settings: a code-defined key (App\Enums\Setting) yields
 * its database override if one exists, otherwise the enum's default — cast to
 * its declared type. The override map is cached (settings are read on hot paths
 * like user creation) and invalidated on write.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /**
     * The typed value (string for select/text, bool for toggle, …).
     */
    public function get(Setting $setting): string|bool|int
    {
        $definition = $setting->definition();
        $override = $this->overrides()[$setting->value] ?? null;

        return $override === null ? $definition->default : $definition->cast($override);
    }

    public function string(Setting $setting): string
    {
        return (string) $this->get($setting);
    }

    public function bool(Setting $setting): bool
    {
        return (bool) $this->get($setting);
    }

    public function set(Setting $setting, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $setting->value],
            ['value' => $setting->definition()->serialize($value), 'updated_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Convenience accessor returning the typed user-creation mode.
     */
    public function userCreationMode(): UserCreationMode
    {
        return UserCreationMode::from($this->string(Setting::UserCreationMode));
    }

    /**
     * @return array<string, string>
     */
    private function overrides(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => DB::table('settings')->pluck('value', 'key')->all(),
        );
    }
}
