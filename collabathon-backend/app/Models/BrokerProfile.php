<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The ~34 registration fields the mobile app collects, hanging off the broker's user row. */
#[Fillable([
    'user_id', 'alternate_mobile', 'residence_address', 'photo_path',
    'is_company', 'company_name', 'office_address', 'company_website',
    'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
    'years_of_experience', 'team_size',
    'pan_card', 'pan_card_path', 'aadhaar_card', 'aadhaar_path',
    'aadhaar_verified', 'aadhaar_verified_name', 'aadhaar_verified_at',
    'rera_number', 'rera_certificate_path', 'rera_certificate_expiry',
    'gst_number', 'gst_path', 'cheque_details', 'cheque_path',
    'state', 'city', 'segments', 'zones', 'operates_multiple_states', 'project_contributions',
    'confirm_accuracy', 'signature_path', 'submitted_at',
])]
class BrokerProfile extends Model
{
    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'zones' => 'array',
            'is_company' => 'boolean',
            'operates_multiple_states' => 'boolean',
            'confirm_accuracy' => 'boolean',
            'aadhaar_verified' => 'boolean',
            'aadhaar_verified_at' => 'datetime',
            'rera_certificate_expiry' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
