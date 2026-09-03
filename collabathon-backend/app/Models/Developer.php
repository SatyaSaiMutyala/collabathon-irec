<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A developer company. Created by an admin — developers never self-register. */
#[Fillable([
    'user_id', 'external_reference_code', 'company_name', 'contact_person', 'mobile', 'email', 'city', 'state',
    'logo_path', 'about', 'cp_payout_percent', 'verified', 'status',
    // Contacts. `key_contact_*`'s number/email are masked to a broker until this
    // developer accepts their lead — see DeveloperResource::withContact().
    'contact_designation', 'key_contact_person', 'key_contact_designation',
    'key_contact_mobile', 'key_contact_email',
    // Location / geo-fence
    'country', 'pincode', 'address', 'latitude', 'longitude',
    // Presence
    'website', 'instagram', 'facebook', 'youtube', 'twitter', 'linkedin',
])]
class Developer extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'cp_payout_percent' => 'decimal:2',
            // Kept as strings by default, which makes a stored 17.4401 come back as
            // "17.4401000" and compare unequal to the float the form posted.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
