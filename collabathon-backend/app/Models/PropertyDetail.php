<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Long-tail spec fields, split 1:1 off properties so list queries stay narrow. */
#[Fillable([
    'property_id', 'connectivity_highlights', 'nearby_infrastructure',
    'construction_specifications', 'amenities', 'amenities_size', 'amenities_count',
    'parking_details', 'approving_authorities', 'bank_approvals',
    'legal_due_diligence_path', 'awards',
    'payment_plan_options', 'booking_amount', 'cp_commission_percent',
    'terms_type', 'terms_title', 'terms_document_path', 'terms_content',
    'special_incentives', 'cashback_schemes', 'registration_stamp_duty',
    'maintenance_charges', 'floor_rise', 'plc_charges', 'other_charges', 'payment_schedule',
    'sales_office_address', 'site_visit_timings', 'sales_contact_name',
    'sales_contact_number', 'booking_process',
])]
class PropertyDetail extends Model
{
    public const TERMS_DOCUMENT = 'document';
    public const TERMS_TEXT = 'text';

    /**
     * True only when the chosen type actually has something behind it.
     *
     * Both columns can hold data at once — switching type does not erase the other — so
     * "has terms" is a question about the selected type, never about which column is
     * non-empty.
     */
    public function hasTerms(): bool
    {
        return match ($this->terms_type) {
            self::TERMS_DOCUMENT => filled($this->terms_document_path),
            self::TERMS_TEXT => filled($this->terms_content),
            default => false,
        };
    }

    protected function casts(): array
    {
        return [
            'connectivity_highlights' => 'array',
            'nearby_infrastructure' => 'array',
            'amenities' => 'array',
            'approving_authorities' => 'array',
            'bank_approvals' => 'array',
            'awards' => 'array',
            'payment_plan_options' => 'array',
            'other_charges' => 'array',
            'cp_commission_percent' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
