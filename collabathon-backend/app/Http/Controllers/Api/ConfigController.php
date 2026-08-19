<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\GoogleMapsSettings;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated config the app needs before anyone has signed in — there is
 * no token yet for the Welcome screen to have earned auth:sanctum access with.
 */
class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                // 'email' or 'mobile' — which OTP flow the Welcome screen's Channel
                // Partners card opens into. See SettingsController::updateCpLoginMethod.
                'cp_login_method' => Setting::get('cp_login_method', 'email'),
                // Safe to serve publicly — see GoogleMapsSettings' own docblock for why a
                // Maps key is meant to ship inside a client app rather than stay secret.
                // Android's map view still needs this copied into the native build
                // config and rebuilt; this alone doesn't make it dynamic there.
                'google_maps_api_key' => GoogleMapsSettings::get(),
            ],
        ]);
    }
}
