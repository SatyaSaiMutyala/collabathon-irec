<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An editable amenity — the checkbox list on the project intake form, managed under
 * Settings → Amenities.
 *
 * Projects store the amenity's name inside `property_details.amenities`, not its id;
 * see the migration for why.
 */
#[Fillable(['name', 'is_active', 'sort_order'])]
class Amenity extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deleted_at' => 'datetime'];
    }

    /** Amenities offerable on a form — the inactive ones stay valid on existing projects. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** How many projects currently list this amenity. Drives the delete guard. */
    public function usageCount(): int
    {
        return PropertyDetail::whereJsonContains('amenities', $this->name)->count();
    }

    /**
     * Every name in the catalogue, active or not, in curated order.
     *
     * This is what decides whether a name saved on a project is a *checkbox* or something
     * an admin typed into "Other amenities" — the edit form splits the saved list against
     * it. Inactive names count as known: retiring an amenity must not push it into the
     * free-text box on every project that already has it.
     *
     * @return list<string>
     */
    public static function catalogue(): array
    {
        return static::ordered()->pluck('name')->all();
    }

    /**
     * The checkboxes the intake form offers: the active ones, plus any retired amenity
     * the project being edited already lists — one switched off in Settings must not
     * silently vanish from a project that has it, or saving would drop it.
     *
     * Only catalogue names are re-offered. A name typed into "Other amenities" has no
     * record here and belongs in that box; adding it to the grid as well would show the
     * same amenity twice, checked in one place and typed in the other.
     *
     * Order matters here in a way it does not for a dropdown — these render as a grid, so
     * the curated order is the reading order. Retired-but-used names go last.
     *
     * @return list<string>
     */
    public static function optionsFor(?iterable $currentNames = null): array
    {
        $active = static::active()->ordered()->pluck('name')->all();

        if (! $currentNames) {
            return $active;
        }

        $retired = array_diff(static::catalogue(), $active);

        foreach ($currentNames as $name) {
            if (in_array($name, $retired, true) && ! in_array($name, $active, true)) {
                $active[] = $name;
            }
        }

        return $active;
    }
}
