<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The privacy rule is enforced here, at the serialisation boundary: broker contact
 * details are emitted only when the lead has unlocked them (broker marked "Interested").
 * A developer viewing a merely-"viewed" lead gets the name and nothing reachable.
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'contact_unlocked' => (bool) $this->contact_unlocked,

            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'interested_at' => $this->interested_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'developer_note' => $this->developer_note,

            'property' => new PropertyResource($this->whenLoaded('property')),
            'developer' => new DeveloperResource($this->whenLoaded('developer')),

            'broker' => $this->whenLoaded('broker', function () {
                return [
                    'id' => $this->broker->id,
                    'name' => $this->broker->name,
                    'company_name' => $this->broker->brokerProfile?->company_name,
                    'rera_number' => $this->broker->brokerProfile?->rera_number,
                    // Gated — never serialised until the broker marks interest.
                    'mobile' => $this->contact_unlocked ? $this->broker->mobile : null,
                    'email' => $this->contact_unlocked ? $this->broker->email : null,
                ];
            }),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
