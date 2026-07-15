<?php

namespace App\Http\Controllers\Api;

use App\Enums\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Services\Settings;
use App\Support\ActivityLogger;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    /**
     * The full set of code-defined settings with their current values and the
     * metadata the UI needs to render them.
     */
    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::cases());
    }

    /**
     * Update one setting's value. The key must be a known setting — anything
     * else 404s, so the UI can only edit values, never add or remove keys.
     */
    public function update(UpdateSettingRequest $request, string $setting, Settings $settings): SettingResource
    {
        $key = Setting::tryFrom($setting);

        abort_if($key === null, 404);

        // Pass the raw (already-validated) typed value; Settings serializes it
        // per the setting's type.
        $old = $settings->get($key);
        $settings->set($key, $request->input('value'));
        // Audit the change with typed old/new values (no-op if unchanged).
        ActivityLogger::logSetting($key->value, $old, $settings->get($key));

        return SettingResource::make($key);
    }
}
