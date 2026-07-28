<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'developer_id', 'name', 'slug', 'project_type', 'project_status', 'listing_status',
    'tagline', 'description', 'logo_path', 'cover_image_path',
    'rera_number', 'rera_registered_at', 'rera_valid_till',
    'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
    'latitude', 'longitude', 'maps_link',
    'price_min', 'price_max', 'price_per_sqft', 'currency',
    'total_units', 'towers', 'floors_per_tower', 'flats_per_floor',
    'land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
    'launch_date', 'possession_date', 'construction_progress',
    'green_certification', 'vastu_compliant',
])]
class Property extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'rera_registered_at' => 'date',
            'rera_valid_till' => 'date',
            'launch_date' => 'date',
            'possession_date' => 'date',
            'vastu_compliant' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'land_parcel_acres' => 'decimal:2',
        ];
    }

    // ------------------------------------------------------------------ relations

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(PropertyDetail::class);
    }

    public function unitTypes(): HasMany
    {
        return $this->hasMany(PropertyUnitType::class)->orderBy('sort_order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->media()->where('kind', 'image');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * The requesting broker's own lead on this property, so a listing can show
     * "already interested" without a second round-trip. Eager-load as:
     *   ->with(['myLead' => fn ($q) => $q->where('broker_id', $userId)])
     */
    public function myLead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    // ------------------------------------------------------------------ scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('listing_status', 'active');
    }

    /**
     * FULLTEXT search on (name, locality, city). MySQL's minimum token length makes
     * FULLTEXT unreliable for 1–3 character terms, so those fall back to a prefix LIKE,
     * which can still use the name index. A leading-wildcard LIKE is never used —
     * it cannot be indexed and would table-scan at 8k concurrent users.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        if (DB::getDriverName() === 'mysql' && mb_strlen($term) >= 4) {
            // Boolean mode with a trailing * gives prefix matching ("azur" finds "Azure").
            $boolean = collect(preg_split('/\s+/', $term))
                ->filter()
                ->map(fn ($word) => '+' . str_replace(['+', '-', '*', '"', '(', ')', '~', '@'], '', $word) . '*')
                ->implode(' ');

            return $query->whereRaw(
                'MATCH(name, locality, city) AGAINST (? IN BOOLEAN MODE)',
                [$boolean]
            );
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term . '%')
                ->orWhere('locality', 'like', $term . '%')
                ->orWhere('city', 'like', $term . '%');
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['developer_id'] ?? null, fn ($q, $v) => $q->where('developer_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('listing_status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('project_type', $v))
            ->when($filters['project_status'] ?? null, fn ($q, $v) => $q->where('project_status', $v))
            ->when($filters['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->when($filters['zone'] ?? null, fn ($q, $v) => $q->where('zone', $v))
            ->when($filters['price_min'] ?? null, fn ($q, $v) => $q->where('price_max', '>=', $v))
            ->when($filters['price_max'] ?? null, fn ($q, $v) => $q->where('price_min', '<=', $v));
    }
}
