<?php

namespace App\Http\Resources;

use App\Support\RichText;
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

            /**
             * The developer's terms for this project — one artefact, supplied either as a
             * document or as typed content.
             *
             * `has_terms` is the flag both apps gate the button on, so neither has to
             * re-derive "type is set AND the matching column is filled". The unselected
             * payload is deliberately not emitted: a project switched from document to
             * text still has the old file on disk, and sending it would let a client
             * show terms that are no longer the live ones.
             */
            'terms' => $this->hasTerms() ? [
                'type' => $this->terms_type,
                'title' => $this->terms_title ?: 'Developer terms',
                'document_url' => $this->terms_type === 'document'
                    ? asset('storage/' . $this->terms_document_path)
                    : null,
                'document_name' => $this->terms_type === 'document'
                    ? basename($this->terms_document_path)
                    : null,
                'document_extension' => $this->terms_type === 'document'
                    ? strtolower(pathinfo($this->terms_document_path, PATHINFO_EXTENSION))
                    : null,
                // Already sanitised on write. Emitted as-is so the app renders the same
                // markup the admin composed.
                'content' => $this->terms_type === 'text' ? $this->terms_content : null,
                'excerpt' => $this->terms_type === 'text'
                    ? RichText::excerpt($this->terms_content)
                    : null,
            ] : null,
            'has_terms' => $this->hasTerms(),
        ];
    }
}
