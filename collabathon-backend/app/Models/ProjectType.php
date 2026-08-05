<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An editable project type — the list the project intake form and the Projects filter
 * pick from, managed under Settings → Project types.
 *
 * Projects store the type's name, not its id; see the migration for why.
 */
#[Fillable(['name', 'requires_possession_date', 'is_active', 'sort_order'])]
class ProjectType extends Model
{
    protected function casts(): array
    {
        return [
            'requires_possession_date' => 'boolean',
            'is_active' => 'boolean',
        ];
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

    /** How many projects currently use this type. Drives the delete guard. */
    public function projectCount(): int
    {
        return Property::where('project_type', $this->name)->count();
    }

    /**
     * name => requires_possession_date, for the intake form's conditional field.
     *
     * @return array<string, bool>
     */
    public static function possessionMap(): array
    {
        return static::query()->pluck('requires_possession_date', 'name')
            ->map(fn ($required) => (bool) $required)
            ->all();
    }
}
