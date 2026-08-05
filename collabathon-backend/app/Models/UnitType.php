<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An editable unit type — the list the project intake form's unit rows pick from,
 * managed under Settings → Unit types.
 *
 * Distinct from PropertyUnitType, which is a configured row on one project. Projects
 * store the type's name, not its id; see the migration for why.
 */
#[Fillable(['name', 'is_active', 'sort_order'])]
class UnitType extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Types offerable on a form — the inactive ones stay valid on existing projects. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** How many configured unit rows currently use this type. Drives the delete guard. */
    public function usageCount(): int
    {
        return PropertyUnitType::where('label', $this->name)->count();
    }

    /**
     * The names the intake form offers, plus any label already saved on the project being
     * edited — a type switched off in Settings must not silently vanish from a row that
     * already uses it, or saving would blank it.
     *
     * @return list<string>
     */
    public static function optionsFor(?iterable $currentLabels = null): array
    {
        $options = static::active()->ordered()->pluck('name')->all();

        foreach ($currentLabels ?? [] as $label) {
            if (filled($label) && ! in_array($label, $options, true)) {
                $options[] = $label;
            }
        }

        return $options;
    }
}
