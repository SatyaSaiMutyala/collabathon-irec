<?php

namespace App\Services\MasterData;

use App\Exceptions\MasterDataImportException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One Master Data registration's `project_details` / `visual_assets` / `documents`
 * blocks, in the shapes {@see Property}, {@see \App\Models\PropertyDetail},
 * {@see \App\Models\PropertyUnitType} and {@see \App\Models\PropertyMedia} expect.
 *
 * The vendor's fields are free text typed by a developer on someone else's website, so
 * everything here is defensive: prices arrive as "₹1.2 Cr" as readily as 12000000,
 * dates as "Dec 2027", and half the optional fields simply do not appear. The rule
 * applied throughout is that a field we cannot read becomes null and the import
 * continues. The one exception is the handful of fields a listing genuinely cannot
 * exist without — its name, its city, a project type — which throw. Nothing else is
 * worth failing an import over, because every remaining field can be filled in
 * afterwards on the listing's own edit page.
 *
 * Values belonging to one of our editable lists — project type, extent metric,
 * amenities, unit labels, the location tree — are not required to be known in advance.
 * They go through {@see MasterDataCatalogue}, which adds whatever it has not seen
 * before. The vendor's vocabulary is theirs: their `project_type` holds sub-brand names
 * like "Sky Mansions", and an import that insisted on ours would stop on nearly every
 * registration.
 */
class ProjectMapper
{
    public function __construct(private readonly MasterDataCatalogue $catalogue) {}

    /** The intake form's own ceilings, applied here so an import cannot exceed what the form allows. */
    private const MAX_GALLERY_IMAGES = 30;

    private const MAX_UNIT_PLANS = 20;

    private const MAX_UNIT_TYPES = 25;

    private const MAX_AMENITIES = 60;

    /** The four values `properties.project_status` accepts, keyed by what the vendor might call them. */
    private const STATUS_SYNONYMS = [
        'new launch' => 'New Launch',
        'launch' => 'New Launch',
        'pre launch' => 'New Launch',
        'prelaunch' => 'New Launch',
        'under construction' => 'Under Construction',
        'ongoing' => 'Under Construction',
        'in progress' => 'Under Construction',
        'ready to move' => 'Ready to Move',
        'ready' => 'Ready to Move',
        'completed' => 'Ready to Move',
        'nearing completion' => 'Nearing Completion',
        'near completion' => 'Nearing Completion',
    ];

    /**
     * Vendor document keys mapped onto our `property_media.kind` values. Matched on a
     * substring so `brochure_url`, `e_brochure` and `brochure` all land in one place.
     * A document whose key matches nothing is skipped rather than guessed at.
     */
    private const DOCUMENT_KINDS = [
        'brochure' => 'brochure',
        'price' => 'price_list',
        'layout' => 'site_layout',
        'payment' => 'payment_schedule',
    ];

    /**
     * The `properties` row, minus `developer_id` and `listing_status` — the importer owns
     * both, since which developer a listing lands on is a resolution decision and not
     * anything the vendor's payload has an opinion about.
     *
     * @return array<string, mixed>
     *
     * @throws MasterDataImportException
     */
    public function propertyAttributes(array $record): array
    {
        $project = $record['project_details'] ?? [];
        $geo = $project['geo_coordinates'] ?? [];

        $name = $this->text(data_get($project, 'project_name'), 255)
            ?? throw MasterDataImportException::missingField('project name');

        // The vendor keeps the address on the project but the city on the developer's
        // profile, so the profile is the fallback rather than an odd second choice —
        // for most registrations it is the only place a city appears at all.
        $city = $this->text(data_get($project, 'city'), 96)
            ?? $this->text(data_get($record, 'developer_profile.city'), 96)
            ?? throw MasterDataImportException::missingField('city');

        $country = $this->text(data_get($project, 'country') ?: data_get($record, 'developer_profile.country'), 96);
        $state = $this->text(data_get($project, 'state') ?: data_get($record, 'developer_profile.state'), 96);

        // Registered in the location tree as well as stored on the listing. The listing
        // keeps these as plain text either way; this is what makes the imported city
        // selectable when someone opens that listing's edit form afterwards.
        $this->catalogue->location($country, $state, $city);

        return [
            'external_reference_code' => $record['reference_code'] ?? null,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'project_type' => $this->projectType(data_get($project, 'project_type')),
            'project_status' => $this->projectStatus(data_get($project, 'project_status')),
            'tagline' => $this->text(data_get($project, 'title_tagline'), 255),
            'description' => $this->text(data_get($project, 'project_description'), 20000),
            'rera_number' => $this->text(data_get($project, 'rera_number'), 64),

            'country' => $country,
            'state' => $state,
            'city' => $city,
            'locality' => $this->text(data_get($project, 'locality_area'), 128),
            'full_address' => $this->text(data_get($project, 'full_address'), 1000),
            'landmark' => $this->text(data_get($project, 'landmark'), 255),
            'pincode' => $this->text(data_get($project, 'pincode') ?: data_get($record, 'developer_profile.pincode'), 12),
            'zone' => $this->zone(data_get($project, 'zone')),
            'latitude' => $this->coordinate(data_get($geo, 'latitude') ?? data_get($geo, 'lat'), 90),
            'longitude' => $this->coordinate(data_get($geo, 'longitude') ?? data_get($geo, 'lng'), 180),
            'maps_link' => $this->url(data_get($geo, 'maps_link')),

            // Currency is fixed at INR platform-wide; the vendor is an Indian portal and
            // the intake form offers no other option either.
            'currency' => 'INR',
            'price_min' => $this->money(data_get($project, 'price_starts_from_inr')),
            'extent_metric' => $this->catalogue->measurementUnit($this->text(data_get($project, 'extent_metric'), 96)),

            'total_units' => $this->integer(data_get($project, 'total_units')),
            'towers' => $this->integer(data_get($project, 'blocks_towers'), 65535),
            'floors_per_tower' => $this->integer(data_get($project, 'number_of_floors'), 65535),
            'land_parcel_acres' => $this->decimal(data_get($project, 'land_parcel_acres')),
            'total_project_area_sqft' => $this->integer(data_get($project, 'total_project_area_sqft')),

            'possession_date' => $this->date(data_get($project, 'possession_date')),
        ];
    }

    /**
     * The `property_details` row — the long tail, minus `property_id`, which the caller
     * adds once the property has an id.
     *
     * @return array<string, mixed>
     */
    public function detailAttributes(array $record): array
    {
        $project = $record['project_details'] ?? [];
        $commercials = $project['channel_partner_commercials'] ?? [];

        return [
            'connectivity_highlights' => $this->lines(data_get($project, 'connectivity_highlights')),
            'nearby_infrastructure' => $this->lines(data_get($project, 'nearby_social_infra')),
            'amenities' => $this->amenities(data_get($project, 'amenities_list')),

            // A percentage on our side, so a vendor figure that isn't one is dropped
            // rather than stored — the same reasoning DeveloperMapper applies to the
            // developer's own payout percent.
            'cp_commission_percent' => $this->percent(data_get($commercials, 'cp_commission')),
            // A flat amount, not a percentage, so only the negative case is excluded.
            'fos_commission_amount' => $this->amount(data_get($commercials, 'fos_commission')),

            'sales_contact_name' => $this->text(data_get($commercials, 'sales_contact_name'), 255),
            'sales_contact_number' => $this->text(data_get($commercials, 'sales_contact_number'), 32),
            'sales_office_address' => $this->text(data_get($commercials, 'sales_office_address'), 5000),
            'site_visit_timings' => $this->text(data_get($commercials, 'site_visit_timings'), 255),
            'booking_process' => $this->text(data_get($commercials, 'booking_checklist'), 5000),
        ];
    }

    /**
     * The `property_unit_types` rows, minus `property_id`.
     *
     * The vendor's single "size" is stored as super built-up area: that is what a
     * configuration's headline square footage means on an Indian listing, and it is the
     * figure the app shows on the unit table. Carpet and built-up stay null rather than
     * repeating a number that was never measured as either.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unitTypes(array $record): array
    {
        $rows = [];

        foreach (array_slice((array) data_get($record, 'project_details.dynamic_unit_configurations', []), 0, self::MAX_UNIT_TYPES) as $index => $unit) {
            $label = $this->text(data_get($unit, 'bhk'), 64);

            // A configuration with no label is an empty row on the vendor's form, the
            // same thing syncUnitTypes() skips on ours.
            if ($label === null) {
                continue;
            }

            $rows[] = [
                // Registered in Settings → Unit types as well, so the label survives as a
                // dropdown option when this listing is edited rather than as loose text.
                'label' => $this->catalogue->unitTypeLabel($label),
                'super_built_up_area_sqft' => $this->integer(data_get($unit, 'size')),
                'price_min' => $this->money(data_get($unit, 'price')),
                'units_count' => $this->integer(data_get($unit, 'units')),
                'sort_order' => $index,
            ];
        }

        return $rows;
    }

    /**
     * Every remote file worth pulling across, as `['kind', 'url', 'caption', 'sort_order']`.
     *
     * `kind` is a `property_media.kind` value except for the single 'cover' entry, which
     * the caller writes to `properties.cover_image_path` instead of a media row — the
     * hero image is the listing's cover on our side, not one more gallery photo.
     *
     * @return array<int, array{kind: string, url: string, caption: ?string, sort_order: int}>
     */
    public function assets(array $record): array
    {
        $project = $record['project_details'] ?? [];
        $assets = $record['visual_assets'] ?? [];
        $specs = [];

        if ($url = $this->url(data_get($assets, 'hero_image_url'), 2048)) {
            $specs[] = $this->asset('cover', $url);
        }

        if ($url = $this->url(data_get($assets, 'master_layout_url'), 2048)) {
            $specs[] = $this->asset('master_plan', $url);
        }

        foreach (array_slice((array) data_get($assets, 'gallery_images', []), 0, self::MAX_GALLERY_IMAGES) as $index => $image) {
            if ($url = $this->url(data_get($image, 'image_url'), 2048)) {
                $specs[] = $this->asset('image', $url, $this->text(data_get($image, 'caption'), 255), $index);
            }
        }

        foreach (array_slice((array) data_get($project, 'floor_plans_each_unit', []), 0, self::MAX_UNIT_PLANS) as $index => $plan) {
            if ($url = $this->url(data_get($plan, 'image_url'), 2048)) {
                $caption = $this->text(data_get($plan, 'plan_type') ?: data_get($plan, 'title'), 255);
                $specs[] = $this->asset('unit_plan', $url, $caption, $index);
            }
        }

        // `documents` is a flat map whose keys the vendor chooses, so it is matched by
        // substring rather than by an exact key list that would rot the moment they
        // rename one. Anything unrecognised is left behind: a document filed under the
        // wrong kind is worse than one the admin re-uploads by hand.
        foreach ((array) ($record['documents'] ?? []) as $key => $url) {
            $kind = $this->documentKind((string) $key);
            $url = $this->url($url, 2048);

            if ($kind && $url) {
                $specs[] = $this->asset($kind, $url);
            }
        }

        return $specs;
    }

    /**
     * Master-data entries this mapping added, for the message shown to whoever pressed
     * Convert — a value that arrived because someone filled in the wrong box on the
     * vendor's form should be visible straight away, not found months later in Settings.
     *
     * @return list<string>
     */
    public function catalogueAdditions(): array
    {
        return $this->catalogue->additions();
    }

    // ------------------------------------------------------------------ field readers

    /**
     * The project's category, added to Settings → Project types if it is new — see
     * {@see MasterDataCatalogue} for why an unknown value is absorbed rather than
     * refused. Blank is still an error: the column is required and there is nothing to
     * add when nothing was typed.
     *
     * @throws MasterDataImportException
     */
    private function projectType(mixed $value): string
    {
        return $this->catalogue->projectType(
            $this->text($value, 96) ?? throw MasterDataImportException::missingField('project type')
        );
    }

    /**
     * Unlike project_type this is a fixed four-value enum nobody edits, and a wrong
     * guess is both visible on the listing and one dropdown away from being corrected —
     * so an unreadable value falls back to the column's own default rather than
     * blocking the import. The fallback is logged, since a vendor who starts sending a
     * fifth status should show up in the log rather than as silently mislabelled
     * listings.
     */
    private function projectStatus(mixed $value): string
    {
        $typed = $this->text($value, 96);

        if ($typed !== null) {
            $normalised = Str::lower(preg_replace('/[^a-z ]+/i', ' ', $typed) ?? '');
            $normalised = trim(preg_replace('/\s+/', ' ', $normalised) ?? '');

            if ($status = self::STATUS_SYNONYMS[$normalised] ?? null) {
                return $status;
            }

            Log::info('Master Data project status not recognised — defaulting', ['value' => $typed]);
        }

        return 'New Launch';
    }

    /** One of the five compass zones, or null — a value we cannot place is not guessed at. */
    private function zone(mixed $value): ?string
    {
        $typed = Str::lower((string) $this->text($value, 32));

        return match (true) {
            str_contains($typed, 'north') => 'North',
            str_contains($typed, 'south') => 'South',
            str_contains($typed, 'east') => 'East',
            str_contains($typed, 'west') => 'West',
            str_contains($typed, 'central') => 'Central',
            default => null,
        };
    }

    /**
     * A rupee figure, however it was typed.
     *
     * Indian listings quote prices in crore and lakh as often as in digits, and the
     * vendor's field is free text, so "₹1.2 Cr", "85 Lakhs" and "1,20,00,000" all have
     * to land on the same integer. Anything with no number in it at all ("Price on
     * request") reads as absent.
     */
    private function money(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }

        $text = Str::lower(trim((string) $value));

        if ($text === '' || ! preg_match('/(\d+(?:\.\d+)?)/', str_replace(',', '', $text), $matches)) {
            return null;
        }

        $multiplier = match (true) {
            str_contains($text, 'cr') => 10_000_000,
            str_contains($text, 'lakh'), str_contains($text, 'lac') => 100_000,
            default => 1,
        };

        return max(0, (int) round(((float) $matches[1]) * $multiplier));
    }

    /** A whole number, clamped to the column's ceiling so a stray value cannot overflow it. */
    private function integer(mixed $value, ?int $max = null): ?int
    {
        if (! is_numeric($value)) {
            $digits = preg_replace('/[^\d]/', '', (string) $value) ?? '';
            $value = $digits === '' ? null : $digits;
        }

        if ($value === null) {
            return null;
        }

        $number = max(0, (int) round((float) $value));

        return $max === null ? $number : min($number, $max);
    }

    /** decimal(10,2) — land parcel size, which the vendor may send as "12.5 acres". */
    private function decimal(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            if (! preg_match('/(\d+(?:\.\d+)?)/', str_replace(',', '', (string) $value), $matches)) {
                return null;
            }
            $value = $matches[1];
        }

        return max(0, round((float) $value, 2));
    }

    /** 0–100, or null. A CP commission outside that range is not a percentage. */
    private function percent(mixed $value): ?float
    {
        $number = $this->amount($value);

        return $number !== null && $number <= 100 ? $number : null;
    }

    /** A non-negative money-ish figure kept as typed, for the columns that are amounts, not percentages. */
    private function amount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            if (! preg_match('/(\d+(?:\.\d+)?)/', str_replace(',', '', (string) $value), $matches)) {
                return null;
            }
            $value = $matches[1];
        }

        return max(0, round((float) $value, 2));
    }

    /**
     * A date the vendor may have typed as "2027-12-01", "Dec 2027" or "Q4 2027". The
     * first two parse; the third does not, and reads as no date rather than as a wrong
     * one — possession drives what brokers see on the listing.
     */
    private function date(mixed $value): ?string
    {
        $typed = $this->text($value, 64);

        if ($typed === null) {
            return null;
        }

        try {
            return Carbon::parse($typed)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** A latitude/longitude inside its valid range, or null. */
    private function coordinate(mixed $value, float $bound): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return abs($number) <= $bound ? $number : null;
    }

    /** An absolute http(s) URL short enough for its column, or null. */
    private function url(mixed $value, int $max = 255): ?string
    {
        $typed = trim((string) $value);

        if ($typed === '' || mb_strlen($typed) > $max || ! filter_var($typed, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(Str::lower((string) parse_url($typed, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $typed
            : null;
    }

    /**
     * Free text a field describes as a list, as a JSON array — the same one-item-per-line
     * split PropertyController::lines() applies to the intake form's textareas, so an
     * imported listing renders identically to a typed one. Falls back to splitting on
     * commas when the vendor sent a single run-on line, which is the common case.
     *
     * @return array<int, string>|null
     */
    private function lines(mixed $value): ?array
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $parts = preg_split('/\R/', $text) ?: [];

        if (count($parts) === 1 && str_contains($text, ',')) {
            $parts = explode(',', $text);
        }

        $items = collect($parts)
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items ?: null;
    }

    /**
     * The project's amenities, each registered in the amenity catalogue.
     *
     * Registering matters more here than storing does. Amenities are saved on the
     * listing as plain strings, so an unknown one would show either way — but
     * PropertyController's edit form splits the saved list against the catalogue to
     * decide which are checkboxes, so an amenity that is not in it cannot be unticked
     * once imported. Putting it in the catalogue makes the imported listing behave
     * exactly like a hand-built one. The catalogue also returns our spelling where we
     * have one, so "Club House" and "Clubhouse" do not become two amenities.
     *
     * @return array<int, string>|null
     */
    private function amenities(mixed $value): ?array
    {
        $items = collect((array) $value)
            ->map(fn ($amenity) => $this->text($amenity, 96))
            ->filter()
            ->unique()
            ->take(self::MAX_AMENITIES)
            ->values()
            ->all();

        return $items ? $this->catalogue->amenities($items) : null;
    }

    private function documentKind(string $key): ?string
    {
        $key = Str::lower($key);

        foreach (self::DOCUMENT_KINDS as $needle => $kind) {
            if (str_contains($key, $needle)) {
                return $kind;
            }
        }

        return null;
    }

    /** Trimmed, null when empty, and never longer than the column that receives it. */
    private function text(mixed $value, int $max): ?string
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /**
     * @return array{kind: string, url: string, caption: ?string, sort_order: int}
     */
    private function asset(string $kind, string $url, ?string $caption = null, int $sortOrder = 0): array
    {
        return ['kind' => $kind, 'url' => $url, 'caption' => $caption, 'sort_order' => $sortOrder];
    }
}
