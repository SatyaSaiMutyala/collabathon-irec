<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A broker user plus their registration profile — used by the admin approvals queue. */
class BrokerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->whenLoaded('brokerProfile') instanceof \App\Models\BrokerProfile
            ? $this->brokerProfile
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),

            'profile' => $p ? [
                'company_name' => $p->company_name,
                'is_company' => (bool) $p->is_company,
                'rera_number' => $p->rera_number,
                'pan_card' => $p->pan_card,
                'gst_number' => $p->gst_number,
                'city' => $p->city,
                'state' => $p->state,
                'segments' => $p->segments ?? [],
                'zones' => $p->zones ?? [],
                'years_of_experience' => $p->years_of_experience,
                'team_size' => $p->team_size,
                'project_contributions' => $p->project_contributions,
                'submitted_at' => $p->submitted_at?->toIso8601String(),
            ] : null,
        ];
    }
}
