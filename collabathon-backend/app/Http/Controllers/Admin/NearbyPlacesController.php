<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/**
 * "What's near this pin" for the project form's Connectivity highlights and Nearby
 * social infrastructure fields — the same free, keyless OSM data source as
 * GeocodeController (Overpass, OpenStreetMap's query API), so a project's coordinates
 * return a first draft of both fields instead of an admin looking each one up by hand
 * and typing "Metro station — 850 m" from memory.
 *
 * One Overpass query for every category, not one per category: Overpass's public
 * instance rate-limits by request count as much as by load, so six small queries cost
 * more than one query with six clauses. Categorising happens in PHP afterward, by
 * reading back which tag each result actually matched.
 */
class NearbyPlacesController extends Controller
{
    private const ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /** Identifies this app to the provider — Overpass rejects requests without one. */
    private const AGENT = 'CollabathonAdmin/1.0 (+admin panel nearby-places lookup)';

    /**
     * Overpass tag filter => [search radius in metres, result group, fallback label
     * when the place has no `name` tag].
     */
    private const CATEGORIES = [
        '["railway"="station"]' => [5000, 'connectivity', 'Metro / rail station'],
        '["aeroway"="aerodrome"]' => [25000, 'connectivity', 'Airport'],
        '["amenity"="bus_station"]' => [3000, 'connectivity', 'Bus station'],
        '["amenity"="school"]' => [3000, 'social', 'School'],
        '["amenity"="hospital"]' => [5000, 'social', 'Hospital'],
        '["shop"="mall"]' => [5000, 'social', 'Mall'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        // Shared with the project form's location picker — see GeocodeController.
        abort_unless(
            Gate::allows('edit-module', 'developers') || Gate::allows('edit-module', 'properties'),
            403
        );

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lon = (float) $data['lon'];

        // Rounded before it becomes a cache key: two pins a few metres apart want the
        // same answer, and keying on the raw float would miss every time.
        $key = sprintf('nearby:%.3f,%.3f', $lat, $lon);

        if (($cached = Cache::get($key)) !== null) {
            return response()->json($cached);
        }

        $result = $this->lookup($lat, $lon);

        // Only a genuine "found nothing nearby" (both groups empty) skips the cache —
        // a transport failure returns the same empty shape, and caching that would pin
        // "nothing nearby" to a real location for a full day after the cause was fixed.
        if ($result['connectivity'] !== [] || $result['social'] !== [] || $result['reached'] === true) {
            Cache::put($key, $result, now()->addDay());
        }

        return response()->json($result);
    }

    /** @return array{connectivity:array<int,string>, social:array<int,string>, reached:bool} */
    private function lookup(float $lat, float $lon): array
    {
        $clauses = '';
        foreach (self::CATEGORIES as $filter => [$radius]) {
            $clauses .= "node{$filter}(around:{$radius},{$lat},{$lon});";
        }
        $query = "[out:json][timeout:15];({$clauses});out body;";

        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->asForm()->timeout(18)->post(self::ENDPOINT, ['data' => $query]);
        } catch (\Throwable) {
            return ['connectivity' => [], 'social' => [], 'reached' => false];
        }

        if (! $response->successful()) {
            return ['connectivity' => [], 'social' => [], 'reached' => false];
        }

        $nearest = []; // filter key => ['group' => .., 'label' => .., 'distance' => ..]

        foreach ($response->json('elements') ?: [] as $element) {
            $tags = $element['tags'] ?? [];
            $match = $this->matchCategory($tags);
            if ($match === null || ! isset($element['lat'], $element['lon'])) {
                continue;
            }

            [$slot, $group, $fallbackLabel] = $match;
            $distance = $this->haversine($lat, $lon, (float) $element['lat'], (float) $element['lon']);
            $label = $tags['name'] ?? $fallbackLabel;

            // Nearest match per *type of place* (one railway station, one school, one
            // mall, …), not one line per named place — a highlights list, not a
            // directory. The closest of each type wins the slot.
            if (! isset($nearest[$slot]) || $distance < $nearest[$slot]['distance']) {
                $nearest[$slot] = ['group' => $group, 'label' => $label, 'distance' => $distance];
            }
        }

        $lines = ['connectivity' => [], 'social' => []];

        foreach (collect($nearest)->sortBy('distance') as $hit) {
            $lines[$hit['group']][] = sprintf('%s — %s', $hit['label'], $this->formatDistance($hit['distance']));
        }

        return ['connectivity' => $lines['connectivity'], 'social' => $lines['social'], 'reached' => true];
    }

    /** @return array{0:string,1:string,2:string}|null [slot key, group, fallback label] for the matching tag. */
    private function matchCategory(array $tags): ?array
    {
        foreach (self::CATEGORIES as $filter => [$radius, $group, $fallbackLabel]) {
            // "[\"railway\"=\"station\"]" -> ['railway', 'station']
            preg_match('/\["([^"]+)"="([^"]+)"\]/', $filter, $m);
            if (($tags[$m[1]] ?? null) === $m[2]) {
                return [$filter, $group, $fallbackLabel];
            }
        }

        return null;
    }

    /** Metres between two points. */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatDistance(float $metres): string
    {
        // Rounded to the nearest 50 m: raw Overpass precision ("847 m") reads as more
        // exact than a straight-line distance to a point actually is.
        return $metres < 1000
            ? ((int) (round($metres / 50) * 50)) . ' m'
            : number_format($metres / 1000, 1) . ' km';
    }
}
