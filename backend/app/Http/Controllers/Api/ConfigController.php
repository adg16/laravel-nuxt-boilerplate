<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Non-sensitive, UI-shaping config the SPA needs (e.g. how the create-user
     * form should behave). Kept separate from the permissioned Settings API so
     * any signed-in user can read it — the values here aren't secrets, they just
     * decide which controls to render.
     */
    public function __invoke(Settings $settings): JsonResponse
    {
        return response()->json([
            'userCreationMode' => $settings->userCreationMode()->value,
            'twoFactorMode' => $settings->twoFactorMode()->value,
            'twoFactorMethods' => $settings->twoFactorMethodPolicy()->value,
        ]);
    }
}
