<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Address lookup for the developer form's "find on map" control.
 *
 * Proxied through the server rather than called from the browser for three reasons:
 * Nominatim's usage policy wants a real identifying User-Agent, which a browser cannot
 * set; the response is cacheable here so repeat searches for the same pincode cost
 * nothing; and swapping provider later is one method instead of a change in every view.
 *
 * OpenStreetMap needs no API key, which is why it is the default — the alternative was
 * asking for a Google Maps key and billing account before the field could work at all.
 * To move to Google, replace the request in `lookup()`; the shape returned to the client
 * is deliberately provider-agnostic.
 */
class GeocodeController extends Controller
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const REVERSE_ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    /** Identifies this app to the provider, as their policy requires. */
    private const AGENT = 'CollabathonAdmin/1.0 (+admin panel address lookup)';

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('edit-module', 'developers');

        // Dropping a pin is the other half of the map: the client sends coordinates and
        // needs the address back, which is the same contract in reverse.
        if ($request->filled('lat') && $request->filled('lon')) {
            return $this->reverse($request);
        }

        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:180'],
        ]);

        $query = trim($data['q']);

        $key = 'geocode:' . md5(mb_strtolower($query));

        /*
         * Only successful lookups are cached.
         *
         * Cache::remember would happily store an empty result, and `lookup()` returns
         * empty for a transport failure as well as for a genuine no-match — so one
         * outage would pin "no results" to that search for a full day, long after the
         * cause was fixed. Caching hits only means a failure costs one retry, not a day
         * of wrong answers.
         */
        if ($cached = Cache::get($key)) {
            return response()->json(['data' => $cached]);
        }

        $results = $this->lookup($query);

        if ($results !== []) {
            // A day is plenty: postcodes and street addresses do not move, and the cache
            // is what keeps an admin typing in the box from hammering a free service.
            Cache::put($key, $results, now()->addDay());
        }

        return response()->json(['data' => $results]);
    }

    /** Coordinates -> one address. Used when the admin drags the pin or clicks the map. */
    private function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Rounded to ~11 m before it becomes a cache key: a pin nudged by a pixel is the
        // same address, and keying on the raw float would miss every time.
        $key = sprintf('geocode:rev:%.4f,%.4f', $data['lat'], $data['lon']);

        if ($cached = Cache::get($key)) {
            return response()->json(['data' => $cached]);
        }

        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->timeout(8)
                ->get(self::REVERSE_ENDPOINT, [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'lat' => $data['lat'],
                    'lon' => $data['lon'],
                ]);
        } catch (\Throwable) {
            return response()->json(['data' => []]);
        }

        if (! $response->successful()) {
            return response()->json(['data' => []]);
        }

        $hit = $response->json();

        if (blank($hit['display_name'] ?? null)) {
            return response()->json(['data' => []]);
        }

        $results = [[
            'label' => $hit['display_name'],
            'address' => $hit['display_name'],
            'pincode' => $hit['address']['postcode'] ?? null,
            // The provider's own coordinates for the matched feature, not the click point:
            // the pin should settle on the building it resolved to.
            'latitude' => isset($hit['lat']) ? (float) $hit['lat'] : (float) $data['lat'],
            'longitude' => isset($hit['lon']) ? (float) $hit['lon'] : (float) $data['lon'],
        ]];

        Cache::put($key, $results, now()->addDay());

        return response()->json(['data' => $results]);
    }

    /** @return array<int, array{label:string, address:string, pincode:?string, latitude:?float, longitude:?float}> */
    private function lookup(string $query): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->timeout(8)
                ->get(self::ENDPOINT, [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 6,
                    'q' => $query,
                ]);
        } catch (\Throwable) {
            // Offline, DNS failure, provider down — the field still works by hand, so an
            // empty list is the right answer rather than a 500 that blocks the whole form.
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json() ?: [])
            ->map(function (array $hit) {
                $parts = $hit['address'] ?? [];

                return [
                    'label' => $hit['display_name'] ?? '',
                    // display_name is already the full comma-separated address, which is
                    // exactly what the textarea wants; the parts are only mined for the
                    // pincode, which has no reliable position inside that string.
                    'address' => $hit['display_name'] ?? '',
                    'pincode' => $parts['postcode'] ?? null,
                    'latitude' => isset($hit['lat']) ? (float) $hit['lat'] : null,
                    'longitude' => isset($hit['lon']) ? (float) $hit['lon'] : null,
                ];
            })
            ->filter(fn (array $hit) => filled($hit['address']))
            ->values()
            ->all();
    }
}
