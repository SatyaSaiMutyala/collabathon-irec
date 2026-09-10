<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 2BHK / 3BHK / Villa / Plot — each with its own areas, facing, price band and floor plan. */
#[Fillable([
    'property_id', 'label', 'carpet_area_sqft', 'built_up_area_sqft',
    'super_built_up_area_sqft', 'facing', 'price_min', 'price_max', 'units_count',
    'floor_plan_path', 'sort_order',
])]
class PropertyUnitType extends Model
{
    /**
     * The facing values the intake form offers and validation accepts.
     *
     * Kept here rather than in a master-data table like unit types and amenities: the
     * eight compass points are a closed set that will not grow, so there is nothing for
     * an admin to maintain. The form's dropdown and the controller's `Rule::in` both
     * read this list, so the two cannot drift apart.
     */
    public const FACINGS = [
        'East', 'West', 'North', 'South',
        'North-East', 'North-West', 'South-East', 'South-West',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
