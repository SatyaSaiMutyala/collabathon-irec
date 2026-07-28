<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeveloperResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'city' => $this->city,
            'state' => $this->state,
            'rera_number' => $this->rera_number,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'about' => $this->about,
            'cp_payout_percent' => (float) $this->cp_payout_percent,
            'verified' => (bool) $this->verified,
            'status' => $this->status,
            // Only present when the controller eager-loaded the count — never triggers a query.
            'properties_count' => $this->whenCounted('properties'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
