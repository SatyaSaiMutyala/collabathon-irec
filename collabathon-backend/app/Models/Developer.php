<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A developer company. Created by an admin — developers never self-register. */
#[Fillable([
    'user_id', 'external_reference_code', 'company_name', 'contact_person', 'mobile', 'email', 'city', 'state',
    'logo_path', 'about', 'cp_payout_percent', 'verified', 'status',
    // Where an admin pinned this company in the broker directory — see scopePinnedFirst().
    'priority',
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
            // Cast so a cleared field arrives as null and a set one compares as a number,
            // never as the "1" a form posts.
            'priority' => 'integer',
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

    // ------------------------------------------------------------------ scopes

    /**
     * Admin-pinned developers first, in the rank the admin gave them, then everything
     * else in whatever order the caller goes on to ask for.
     *
     * The pin is per developer and a developer belongs to exactly one city, so this is a
     * per-city ranking in practice: the broker Home screen has already narrowed the
     * directory to one city by the time this runs, and a rank set on a Hyderabad company
     * can never surface in the Bengaluru list.
     *
     * `priority is null` — a 0/1 expression on MySQL and SQLite alike — is what puts the
     * unpinned rows *below* the pinned ones. A plain `order by priority asc` does the
     * opposite on both engines, which sort NULLs first ascending, and would float every
     * unranked developer above the one deliberately pinned to rank 1.
     *
     * Call this before applySort(): the first ORDER BY term added wins, so the pins lead
     * and the caller's own sort becomes the tie-break among equals.
     */
    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('developers.priority is null')
            ->orderBy('developers.priority');
    }
}
