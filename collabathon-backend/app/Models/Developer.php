<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A developer company. Created by an admin — developers never self-register. */
#[Fillable([
    'user_id', 'company_name', 'contact_person', 'mobile', 'email', 'city', 'state',
    'rera_number', 'logo_path', 'about', 'cp_payout_percent', 'verified', 'status',
])]
class Developer extends Model
{
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'cp_payout_percent' => 'decimal:2',
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
