<?php

namespace App\Services\MasterData;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\MeasurementUnit;
use App\Models\ProjectType;
use App\Models\State;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Resolves a value the vendor typed against one of our editable master lists, adding it
 * when it is not there yet.
 *
 * irecexpo.com's fields are free text, and their `project_type` in particular is filled
 * with product-line names ("Sky Mansions", "The Villa Collection") rather than
 * categories — so an import that insisted on a known value would stop on nearly every
 * registration. Adding the value instead means an import never dead-ends on vocabulary.
 *
 * Two rules keep that from turning the settings screens into a junk drawer:
 *
 *  - Everything added is created INACTIVE. Every one of these lists already separates
 *    "offered on a form" from "valid on a record" — see each model's `active()` scope
 *    and the `optionsFor()` helpers — so an inactive entry keeps the imported listing
 *    editable and correctly labelled without putting a developer's sub-brand into the
 *    intake form's dropdown or a broker's filters. An admin who wants it offered turns
 *    it on in Settings; one who doesn't deletes it there.
 *  - Everything added is recorded in {@see additions()} and reported to the admin who
 *    pressed the button, so a value that arrived because someone filled in the wrong box
 *    on the vendor's form is visible immediately rather than discovered months later.
 *
 * Locations are the exception to "inactive", having no such flag: a country, state or
 * city either exists or does not, and the hierarchy means one is only created when
 * everything above it is known too.
 *
 * Matching is case-insensitive throughout and the STORED name wins, so "residential"
 * resolves to the existing "Residential" rather than adding a second row beside it.
 */
class MasterDataCatalogue
{
    /** @var list<string> What this import added, ready to show the admin. */
    private array $additions = [];

    /**
     * Each list read once, keyed by normalised name — see {@see findByName()}.
     *
     * @var array<string, \Illuminate\Support\Collection<string, Model>>
     */
    private array $cache = [];

    /**
     * The project's category. Blank is still refused by the caller — a listing has to
     * carry one, and there is nothing to add when there is nothing to add.
     */
    public function projectType(string $name): string
    {
        return $this->resolve(ProjectType::class, $name, 'project type');
    }

    /** The unit behind "Project extent metric" — "Acres", "Sq. Yards" and so on. */
    public function measurementUnit(?string $name): ?string
    {
        return $name === null ? null : $this->resolve(MeasurementUnit::class, $name, 'measurement unit');
    }

    /**
     * The amenity checkbox catalogue. Returned in the caller's order with our spelling
     * where we already have one, so two registrations spelling the same amenity
     * differently still count as the same amenity on the listings that follow.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function amenities(array $names): array
    {
        return array_map(fn (string $name) => $this->resolve(Amenity::class, $name, 'amenity'), $names);
    }

    /**
     * The unit-type list a project's configuration rows pick from — "3 BHK", "Duplex".
     * Returns the canonical spelling for the row's label.
     */
    public function unitTypeLabel(string $name): string
    {
        return $this->resolve(UnitType::class, $name, 'unit type');
    }

    /**
     * The country → state → city tree behind the location pickers.
     *
     * Strictly top-down: a city row needs a state and a state needs a country, so a
     * registration that names a city but no state adds nothing rather than inventing a
     * parent to hang it on. Nothing here affects the listing itself, which stores these
     * three as plain text — this is what makes the imported city selectable when someone
     * later edits that listing by hand.
     */
    public function location(?string $country, ?string $state, ?string $city): void
    {
        if ($country === null) {
            return;
        }

        // Cache keys carry the parent id, since "Hyderabad" under one state says nothing
        // about a same-named city under another.
        $countryRow = ($found = $this->findByName(Country::withTrashed(), $country, 'country'))
            ? $this->reviveIfTrashed($found, 'country')
            : $this->added(Country::create(['name' => $country]), 'country', 'country');

        if ($state === null) {
            return;
        }

        $stateKey = "state:{$countryRow->id}";
        $stateRow = ($found = $this->findByName($countryRow->states()->withTrashed(), $state, $stateKey))
            ? $this->reviveIfTrashed($found, 'state')
            : $this->added($countryRow->states()->create(['name' => $state]), 'state', $stateKey);

        if ($city === null) {
            return;
        }

        $cityKey = "city:{$stateRow->id}";

        if ($found = $this->findByName($stateRow->cities()->withTrashed(), $city, $cityKey)) {
            $this->reviveIfTrashed($found, 'city');

            return;
        }

        $this->added($stateRow->cities()->create(['name' => $city]), 'city', $cityKey);
    }

    /**
     * What this import added, as sentences an admin can act on.
     *
     * @return list<string>
     */
    public function additions(): array
    {
        return $this->additions;
    }

    // ------------------------------------------------------------------ internals

    /**
     * The stored name for `$name` in one of the flagged lists, creating it inactive if
     * it is new.
     *
     * @param  class-string<Model>  $model
     */
    private function resolve(string $model, string $name, string $label): string
    {
        if ($existing = $this->findByName($model::withTrashed(), $name, $model)) {
            return $this->reviveIfTrashed($existing, $label)->name;
        }

        $created = $model::create([
            'name' => $name,
            // Last in the list rather than first: an admin's own curated order should not
            // be reshuffled by whatever a developer typed on another website.
            'sort_order' => (int) $model::max('sort_order') + 1,
            'is_active' => false,
        ]);

        $this->remember($model, $created);
        $this->add($created, $label);

        return $created->name;
    }

    /**
     * Looks `$name` up by its normalised form rather than its exact text.
     *
     * Case alone is not enough. This project's unit types are stored as "2BHK" while the
     * vendor writes "2 BHK", and "Club House" against our "Clubhouse" is the same kind of
     * near-miss — matching literally would double every one of those lists rather than
     * top it up. Spacing and punctuation are therefore ignored on both sides, so the two
     * spellings resolve to the one entry we already have and the stored spelling wins.
     *
     * Done in PHP, not SQL: these lists are tens of rows and the comparison has no
     * portable SQL form, so the whole list is read once per model and kept for the rest
     * of the import — an amenity block of twenty names is one query, not twenty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Model>|\Illuminate\Database\Eloquent\Relations\Relation<Model>  $query
     */
    private function findByName($query, string $name, string $cacheKey): ?Model
    {
        $this->cache[$cacheKey] ??= $query->get()
            ->keyBy(fn (Model $row) => $this->normalise($row->name));

        return $this->cache[$cacheKey][$this->normalise($name)] ?? null;
    }

    /** Remembers a row just created, so a second mention of it in the same import finds it. */
    private function remember(string $cacheKey, Model $row): void
    {
        if (isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey][$this->normalise($row->name)] = $row;
        }
    }

    /** Lowercase, letters and digits only — "2 BHK", "2-BHK" and "2bhk" all collapse to one key. */
    private function normalise(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower($name));
    }

    /**
     * Brings a deleted entry back rather than inserting a second one beside it.
     *
     * Not a preference — every one of these tables has a unique index on the name, and a
     * soft-deleted row still occupies it, so creating "Sky Mansions" again while the
     * deleted one sits there is a constraint violation, not a duplicate. Restoring is
     * also mild: the entry comes back inactive, which means valid on records and offered
     * on nothing, so an admin's decision to stop offering it still stands. It is
     * reported like any other addition so they can delete it again if they meant it.
     */
    private function reviveIfTrashed(Model $entry, string $label): Model
    {
        if (! $entry->trashed()) {
            return $entry;
        }

        $entry->restore();

        if ($entry->getAttribute('is_active') !== null) {
            $entry->update(['is_active' => false]);
        }

        $this->additions[] = "\"{$entry->name}\" was restored to your {$label}s from Trash";

        return $entry;
    }

    private function add(Model $created, string $label): Model
    {
        $this->additions[] = "\"{$created->name}\" was added to your {$label}s";

        return $created;
    }

    /** {@see add()}, plus keeping the new row in the lookup it came from. */
    private function added(Model $created, string $label, string $cacheKey): Model
    {
        $this->remember($cacheKey, $created);

        return $this->add($created, $label);
    }
}
