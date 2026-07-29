<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyUnitTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'carpet_area_sqft' => $this->carpet_area_sqft,
            'built_up_area_sqft' => $this->built_up_area_sqft,
            'super_built_up_area_sqft' => $this->super_built_up_area_sqft,
            'price_min' => $this->price_min !== null ? (int) $this->price_min : null,
            'price_max' => $this->price_max !== null ? (int) $this->price_max : null,
            'units_count' => $this->units_count,
            'floor_plan_url' => $this->floor_plan_path ? asset('storage/' . $this->floor_plan_path) : null,
        ];
    }
}
