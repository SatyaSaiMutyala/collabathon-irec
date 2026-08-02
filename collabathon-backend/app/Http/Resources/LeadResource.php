<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lead itself. The privacy rule lives one level down in PartnerResource, which is
 * shared with the partner list so the gate has a single implementation — this class only
 * decides *which side* of it a given lead is on: `revealsContact()`, i.e. has the
 * developer accepted. Before that the broker's phone and email come back starred and
 * their address and links not at all, so the real values never cross the wire.
 */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            // Retained: the broker reached "Interested". Not the contact gate — see the
            // note on Lead::revealsContact(), which the dashboards depend on.
            'contact_unlocked' => (bool) $this->contact_unlocked,
            // The gate the UI should read.
            'contact_visible' => $this->revealsContact(),

            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'interested_at' => $this->interested_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'developer_note' => $this->developer_note,

            'property' => new PropertyResource($this->whenLoaded('property')),
            'developer' => new DeveloperResource($this->whenLoaded('developer')),

            'broker' => $this->whenLoaded(
                'broker',
                fn () => (new PartnerResource($this->broker))->withContact($this->revealsContact())
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
