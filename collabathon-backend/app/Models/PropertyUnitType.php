<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 2BHK / 3BHK / Villa / Plot — each with its own areas, price band and floor plan. */
#[Fillable([
    'property_id', 'label', 'carpet_area_sqft', 'built_up_area_sqft',
    'super_built_up_area_sqft', 'price_min', 'price_max', 'units_count',
    'floor_plan_path', 'sort_order',
])]
class PropertyUnitType extends Model
{
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
