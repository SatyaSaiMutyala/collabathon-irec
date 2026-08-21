<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormField;
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
                // Which non-core fields the Complete Profile screen should actually
                // render — see Settings > Form fields. Keyed by `form`, then
                // `field_key`, straight to a boolean: a core field (full_name, mobile,
                // email, rera_number, project_name, price_range, location) is never
                // sent as false — the admin panel does not let those be turned off —
                // but the app treats an *absent* key as enabled anyway (fail-open, so
                // a field this endpoint doesn't yet know about is never hidden by
                // default) rather than trusting that guarantee alone.
                'fields' => FormField::query()->get()
                    ->groupBy('form')
                    ->map(fn ($fields) => $fields->pluck('enabled', 'field_key')),
            ],
        ]);
    }
}
