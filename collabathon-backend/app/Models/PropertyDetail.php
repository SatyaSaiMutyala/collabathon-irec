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
    'special_incentives', 'cashback_schemes', 'registration_stamp_duty',
    'maintenance_charges', 'floor_rise', 'plc_charges', 'other_charges', 'payment_schedule',
    'sales_office_address', 'site_visit_timings', 'sales_contact_name',
    'sales_contact_number', 'booking_process',
])]
class PropertyDetail extends Model
{
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
