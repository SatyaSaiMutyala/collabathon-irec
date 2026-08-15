<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/**
 * Address lookup for the developer form's and the project form's "find on map" controls.
 *
 * Proxied through the server rather than called from the browser for the same three
 * reasons this had under the previous (OpenStreetMap/Nominatim) provider: the response
 * is cacheable here so repeat searches for the same pincode cost nothing; the API key
 * never has to reach client-side JavaScript for this half of the feature (only the map
 * *render* — see `loadGoogleMaps()` in app.js — needs the key in the browser); and
 * swapping provider again later is one class instead of a change in every view. The
 * shape returned to the client is unchanged from the OSM days on purpose — Google's own
 * response shape is mapped into it here, not the other way around.
 */
class GeocodeController extends Controller
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __invoke(Request $request): JsonResponse
    {
        // Shared by both the developer form and the project form's location picker, so
        // either module's edit permission is enough — a team member who can edit
        // projects but not developers still needs this endpoint to work on that form.
        abort_unless(
            Gate::allows('edit-module', 'developers') || Gate::allows('edit-module', 'properties'),
            403
        );

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
         * of wrong answers. It also means a busy day of admins searching the same handful
         * of localities costs this Google account a small fraction of what it would
         * without it — the free monthly credit is generous but not infinite.
         */
        if ($cached = Cache::get($key)) {
            return response()->json(['data' => $cached]);
        }

        $results = $this->lookup($query);

        if ($results !== []) {
            // A day is plenty: postcodes and street addresses do not move, and the cache
            // is what keeps an admin typing in the box from hammering a billed API.
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

        $apiKey = config('services.google_maps.key');
        if (! $apiKey) {
            return response()->json(['data' => []]);
        }

        try {
            $response = Http::timeout(8)->get(self::ENDPOINT, [
                'latlng' => "{$data['lat']},{$data['lon']}",
                'key' => $apiKey,
            ]);
        } catch (\Throwable) {
            return response()->json(['data' => []]);
        }

        if (! $response->successful()) {
            return response()->json(['data' => []]);
        }

        $body = $response->json();
        $hit = $body['results'][0] ?? null;

        if (! $hit || blank($hit['formatted_address'] ?? null)) {
            return response()->json(['data' => []]);
        }

        $results = [$this->fromGoogleResult($hit, (float) $data['lat'], (float) $data['lon'])];

        Cache::put($key, $results, now()->addDay());

        return response()->json(['data' => $results]);
    }

    /** @return array<int, array{label:string, address:string, pincode:?string, latitude:?float, longitude:?float}> */
    private function lookup(string $query): array
    {
        $apiKey = config('services.google_maps.key');
        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::timeout(8)->get(self::ENDPOINT, [
                'address' => $query,
                // A bias, not a hard filter (no `components=country:IN`) — every seeded
                // developer and project is Indian, but a search should still find a
                // genuine match elsewhere rather than silently drop it.
                'region' => 'in',
                'key' => $apiKey,
            ]);
        } catch (\Throwable) {
            // Offline, DNS failure, provider down — the field still works by hand, so an
            // empty list is the right answer rather than a 500 that blocks the whole form.
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        // ZERO_RESULTS is a normal, successful answer, not an error — only OK carries
        // results. Every other status (REQUEST_DENIED, OVER_QUERY_LIMIT, …) means
        // something is actually wrong (bad/missing key, billing not enabled, quota),
        // which also just means "no results" from here rather than a form-blocking 500.
        if (($body['status'] ?? null) !== 'OK') {
            return [];
        }

        return collect($body['results'] ?? [])
            ->take(6)
            ->map(fn (array $hit) => $this->fromGoogleResult($hit))
            ->filter(fn (array $hit) => filled($hit['address']))
            ->values()
            ->all();
    }

    /**
     * Google's shape -> this endpoint's shape. `$fallbackLat`/`$fallbackLon` are used only
     * by reverse(): if a geocode result is somehow missing its own geometry (has not been
     * observed, but the previous provider's code guarded the same case), the map should
     * still settle on the point that was actually clicked rather than snap to (0, 0).
     */
    private function fromGoogleResult(array $hit, ?float $fallbackLat = null, ?float $fallbackLon = null): array
    {
        $postcode = collect($hit['address_components'] ?? [])
            ->first(fn (array $component) => in_array('postal_code', $component['types'] ?? [], true));

        return [
            'label' => $hit['formatted_address'] ?? '',
            'address' => $hit['formatted_address'] ?? '',
            'pincode' => $postcode['long_name'] ?? null,
            'latitude' => $hit['geometry']['location']['lat'] ?? $fallbackLat,
            'longitude' => $hit['geometry']['location']['lng'] ?? $fallbackLon,
        ];
    }
}
