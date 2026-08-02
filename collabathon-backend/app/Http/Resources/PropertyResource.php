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
        // Discriminates show from index. The previous `$request->routeIs('*.show')` test
        // could never fire: routeIs matches route NAMES, and the mobile API routes in
        // routes/api.php are unnamed — so `description` was silently dropped from every
        // response. PropertyController::show() is the only action that eager-loads
        // `detail`, which makes that the honest signal.
        $isDetail = $this->resource->relationLoaded('detail');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'project_type' => $this->project_type,
            'project_status' => $this->project_status,
            'listing_status' => $this->listing_status,

            // The developer-acceptance gate. `is_live` is the answer to the question
            // both clients actually ask — "can a broker see this?" — so neither has to
            // re-derive the two-key rule and risk getting it wrong.
            'developer_status' => $this->developer_status,
            'developer_responded_at' => $this->developer_responded_at?->toIso8601String(),
            'developer_decline_reason' => $this->developer_decline_reason,
            'is_live' => $this->listing_status === 'active'
                && $this->developer_status === \App\Models\Property::DEV_ACCEPTED,

            'tagline' => $this->tagline,
            'cover_image_url' => $this->cover_image_path ? asset('storage/' . $this->cover_image_path) : null,

            'location' => [
                'state' => $this->state,
                'city' => $this->city,
                'locality' => $this->locality,
                'full_address' => $this->full_address,
                'landmark' => $this->landmark,
                'maps_link' => $this->maps_link,
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

            // Detail screen only. These all live on the `properties` row that is already
            // selected, so gating them costs nothing to fetch — it only keeps the list
            // payload narrow, which is the same reason `description` is gated.
            'description' => $this->when($isDetail, $this->description),

            'rera' => $this->when($isDetail, fn () => [
                'number' => $this->rera_number,
                'registered_at' => $this->rera_registered_at?->toDateString(),
                'valid_till' => $this->rera_valid_till?->toDateString(),
            ]),

            'scale' => $this->when($isDetail, fn () => [
                'total_units' => $this->total_units,
                'towers' => $this->towers,
                'floors_per_tower' => $this->floors_per_tower,
                'flats_per_floor' => $this->flats_per_floor,
                'land_parcel_acres' => $this->land_parcel_acres !== null ? (float) $this->land_parcel_acres : null,
                'total_project_area_sqft' => $this->total_project_area_sqft,
                'open_space_percent' => $this->open_space_percent,
            ]),

            'compliance' => $this->when($isDetail, fn () => [
                'green_certification' => $this->green_certification,
                'vastu_compliant' => (bool) $this->vastu_compliant,
                'launch_date' => $this->launch_date?->toDateString(),
            ]),

            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'detail' => new PropertyDetailResource($this->whenLoaded('detail')),
            'unit_types' => PropertyUnitTypeResource::collection($this->whenLoaded('unitTypes')),
            'media' => PropertyMediaResource::collection($this->whenLoaded('media')),

            // The requesting broker's own lead on this property, when loaded.
            'my_lead' => new LeadResource($this->whenLoaded('myLead')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
