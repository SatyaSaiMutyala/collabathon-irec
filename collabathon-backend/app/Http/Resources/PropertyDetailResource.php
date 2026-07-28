<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connectivity_highlights' => $this->connectivity_highlights ?? [],
            'nearby_infrastructure' => $this->nearby_infrastructure ?? [],
            'construction_specifications' => $this->construction_specifications,
            'amenities' => $this->amenities ?? [],
            'amenities_size' => $this->amenities_size,
            'amenities_count' => $this->amenities_count,
            'parking_details' => $this->parking_details,
            'approving_authorities' => $this->approving_authorities ?? [],
            'bank_approvals' => $this->bank_approvals ?? [],
            'awards' => $this->awards ?? [],
            'payment_plan_options' => $this->payment_plan_options ?? [],
            'booking_amount' => $this->booking_amount !== null ? (int) $this->booking_amount : null,
            'cp_commission_percent' => $this->cp_commission_percent !== null ? (float) $this->cp_commission_percent : null,
            'special_incentives' => $this->special_incentives,
            'cashback_schemes' => $this->cashback_schemes,
            'registration_stamp_duty' => $this->registration_stamp_duty,
            'maintenance_charges' => $this->maintenance_charges,
            'floor_rise' => $this->floor_rise,
            'plc_charges' => $this->plc_charges,
            'other_charges' => $this->other_charges ?? [],
            'payment_schedule' => $this->payment_schedule,
            'sales_office_address' => $this->sales_office_address,
            'site_visit_timings' => $this->site_visit_timings,
            'sales_contact_name' => $this->sales_contact_name,
            'sales_contact_number' => $this->sales_contact_number,
            'booking_process' => $this->booking_process,
        ];
    }
}
