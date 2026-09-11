<?php

namespace App\Support;

use App\Models\City;
use Illuminate\Http\Request;

/**
 * Where the channel partner is standing, as the developer directory needs it.
 *
 * One request yields two answers, and they do different jobs:
 *
 *   - a point, which orders the list: nearest company first, then outward;
 *   - a state, which caps how far outward that is allowed to go.
 *
 * The point comes from `lat`/`lng` when the app sends them. Older builds send only
 * `city` — a name their GPS fix was reverse-geocoded into — and a name cannot be
 * measured from, so those callers get no point and the list falls back to its normal
 * order. Nothing is geocoded here on purpose: a third-party lookup on a list endpoint
 * every partner opens would add a round trip, a rate limit and someone else's outage
 * to the first screen of the app.
 *
 * The state comes from that same city name, resolved through the cities master data —
 * the one place an admin already curates. A name that isn't on file resolves to no
 * state, and no state means no cap. That fallback is the point: matching the city
 * exactly is what used to empty this screen, because every developer sits in one of
 * two city names while a GPS fix reverse-geocodes to whatever OpenStreetMap calls that
 * spot ("Secunderabad", "Kukatpally", sometimes just the state). Unknown now means
 * "show them everything, nearest first" rather than "show them nothing".
 */
final class DirectoryLocation
{
    private function __construct(
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $state,
    ) {}

    public static function fromRequest(Request $request): self
    {
        [$latitude, $longitude] = self::readPoint($request);

        return new self($latitude, $longitude, self::stateFor($request->query('city')));
    }

    /** True once there is somewhere to measure from. */
    public function hasPoint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Straight-line kilometres from here to a developer, or null if either end has no
     * point.
     *
     * Haversine, and deliberately in PHP rather than in the ordering query: a page is a
     * handful of rows, so the cost is nothing, and it keeps the SQL free of maths
     * functions whose availability differs between databases. This is the number a
     * partner reads; the query only has to decide sequence.
     */
    public function kilometresTo(?float $latitude, ?float $longitude): ?float
    {
        if (! $this->hasPoint() || $latitude === null || $longitude === null) {
            return null;
        }

        $earthRadiusKm = 6371;

        $deltaLat = deg2rad($latitude - $this->latitude);
        $deltaLng = deg2rad($longitude - $this->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($deltaLng / 2) ** 2;

        // min(1.0, ...) guards asin() against a hair over 1 from floating-point drift on
        // two points that are effectively the same place.
        return round($earthRadiusKm * 2 * asin(min(1.0, sqrt($a))), 1);
    }

    /**
     * What a degree of longitude is worth here, relative to a degree of latitude.
     *
     * Longitude lines converge towards the poles, so comparing raw degree differences
     * would rank an east-west neighbour as nearer than it really is. One cosine, taken
     * once for the partner's own latitude, corrects that across the few hundred
     * kilometres a state spans — which is all the ordering needs, since it only has to
     * sequence rows, not report a distance.
     */
    public function longitudeScale(): float
    {
        return $this->hasPoint() ? cos(deg2rad($this->latitude)) : 1.0;
    }

    /** @return array{0: ?float, 1: ?float} */
    private static function readPoint(Request $request): array
    {
        $latitude = $request->query('lat');
        $longitude = $request->query('lng');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [null, null];
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        // Off the globe: a truncated or malformed pair, not a place. Dropped rather than
        // trusted, so one bad reading cannot quietly reorder the whole directory.
        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return [null, null];
        }

        return [$latitude, $longitude];
    }

    private static function stateFor(mixed $city): ?string
    {
        if (! is_string($city) || blank(trim($city))) {
            return null;
        }

        return City::query()
            ->with('state')
            ->where('name', trim($city))
            ->first()?->state?->name;
    }
}
