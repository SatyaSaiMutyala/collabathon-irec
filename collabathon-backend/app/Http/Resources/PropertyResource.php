<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List shape. Detail-only relations are emitted with whenLoaded, so the same resource
 * serves both the index (narrow) and the show (full) without an N+1.
 */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'project_type' => $this->project_type,
            'project_status' => $this->project_status,
            'listing_status' => $this->listing_status,
            'tagline' => $this->tagline,
            'cover_image_url' => $this->cover_image_path ? asset('storage/' . $this->cover_image_path) : null,

            'location' => [
                'state' => $this->state,
                'city' => $this->city,
                'locality' => $this->locality,
                'zone' => $this->zone,
                'pincode' => $this->pincode,
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],

            'price' => [
                'min' => $this->price_min !== null ? (int) $this->price_min : null,
                'max' => $this->price_max !== null ? (int) $this->price_max : null,
                'per_sqft' => $this->price_per_sqft !== null ? (int) $this->price_per_sqft : null,
                'currency' => $this->currency,
            ],

            'possession_date' => $this->possession_date?->toDateString(),
            'construction_progress' => (int) $this->construction_progress,
            'views_count' => (int) $this->views_count,
            'interests_count' => (int) $this->interests_count,

            'developer' => new DeveloperResource($this->whenLoaded('developer')),

            // Detail screen only
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'detail' => new PropertyDetailResource($this->whenLoaded('detail')),
            'unit_types' => PropertyUnitTypeResource::collection($this->whenLoaded('unitTypes')),
            'media' => PropertyMediaResource::collection($this->whenLoaded('media')),

            // The requesting broker's own lead on this property, when loaded.
            'my_lead' => new LeadResource($this->whenLoaded('myLead')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
