<?php

namespace App\Http\Resources;

use App\Enums\Setting;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Setting $resource
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

        return [
            'key' => $setting->value,
            'value' => app(Settings::class)->get($setting),
            'type' => $setting->type()->value,
            'options' => $setting->options(),
            'group' => $setting->group(),
        ];
    }
}
