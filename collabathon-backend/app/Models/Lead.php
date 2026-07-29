<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A broker's relationship with one property: viewed → interested → accepted/declined.
 *
 * `contact_unlocked` is the privacy gate — the developer only sees the broker's phone
 * and email once the broker marks "Interested". It is set here, not by the caller, so
 * no endpoint can leak contact details by passing a flag.
 */
#[Fillable([
    'property_id', 'broker_id', 'developer_id', 'status', 'contact_unlocked',
    'viewed_at', 'interested_at', 'responded_at', 'developer_note',
])]
class Lead extends Model
{
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_INTERESTED = 'interested';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected function casts(): array
    {
        return [
            'contact_unlocked' => 'boolean',
            'viewed_at' => 'datetime',
            'interested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------ relations

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broker_id');
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    // ------------------------------------------------------------------ helpers

    /** Contact is revealed at "interested" and stays revealed for the rest of the flow. */
    public function revealsContact(): bool
    {
        return $this->contact_unlocked;
    }

    // ------------------------------------------------------------------ scopes

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['developer_id'] ?? null, fn ($q, $v) => $q->where('developer_id', $v))
            ->when($filters['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($filters['broker_id'] ?? null, fn ($q, $v) => $q->where('broker_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /**
     * Search runs against the joined broker/property names. Kept as an EXISTS subquery
     * so the base index on leads is still usable.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereHas('broker', fn ($b) => $b->where('name', 'like', $term . '%'))
                ->orWhereHas('property', fn ($p) => $p->where('name', 'like', $term . '%'));
        });
    }
}
