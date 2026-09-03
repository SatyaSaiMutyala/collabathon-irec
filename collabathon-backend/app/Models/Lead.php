<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A broker's relationship with one property: viewed → interested → accepted/declined.
 *
 * `contact_unlocked` records that the broker reached "Interested". Despite the name it
 * is no longer the privacy gate — that is `revealsContact()` below, which waits for the
 * developer to accept. The column keeps its original meaning because the dashboards
 * count it as the interested stage of the funnel; see the note on `revealsContact()`.
 * It is set server-side, never from a request body, so no endpoint can move the stage
 * by passing a flag.
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

    /**
     * The developer sees the broker's reachable channels only once they have accepted
     * the request. Interest alone is a knock on the door, not an introduction — until
     * then the API sends starred placeholders (see App\Support\ContactMask).
     *
     * Derived from `status` rather than from `contact_unlocked` on purpose. That column
     * records the moment the broker expressed interest, and it is summed as the
     * *interested* figure in Admin\DashboardController, Api\DashboardController and the
     * admin lead stats. Repointing it at acceptance would silently rewrite every one of
     * those numbers, so the gate moved and the column did not.
     */
    public function revealsContact(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
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
            $q->whereHas('broker', fn ($b) => $b->where('name', 'like', '%' . $term . '%'))
                ->orWhereHas('property', fn ($p) => $p->where('name', 'like', '%' . $term . '%'));
        });
    }
}
