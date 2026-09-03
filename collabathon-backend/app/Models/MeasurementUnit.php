<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An editable measurement unit — the list behind "Project extent metric" on the project
 * intake form, managed under Settings → Measurement units.
 *
 * Projects store the unit's name, not its id; see the migration for why.
 */
#[Fillable(['name', 'is_active', 'sort_order'])]
class MeasurementUnit extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deleted_at' => 'datetime'];
    }

    /** Units offerable on a form — the inactive ones stay valid on existing projects. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** How many projects currently use this unit. Drives the delete guard. */
    public function usageCount(): int
    {
        return Property::where('extent_metric', $this->name)->count();
    }

    /**
     * The names the intake form offers, plus whatever the project being edited already
     * has — a unit switched off in Settings must not vanish from a project using it, or
     * the select would fall back to another and silently re-save it.
     *
     * @return list<string>
     */
    public static function optionsFor(?string $current = null): array
    {
        $options = static::active()->ordered()->pluck('name')->all();

        if (filled($current) && ! in_array($current, $options, true)) {
            $options[] = $current;
        }

        return $options;
    }
}
