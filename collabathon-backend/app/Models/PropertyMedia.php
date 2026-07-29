<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Images, brochures, plans and external links for a property. */
#[Fillable(['property_id', 'kind', 'path', 'url', 'caption', 'sort_order'])]
class PropertyMedia extends Model
{
    protected $table = 'property_media';

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
