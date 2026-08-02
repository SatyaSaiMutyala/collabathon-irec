<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Http\Resources\PartnerResource;
use App\Models\BrokerProfile;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The developer's channel partners: the brokers they have actually accepted.
 *
 * One row per broker, not per lead — a broker who was accepted on four projects is one
 * partner with `projects_count` of 4, which is why this is its own endpoint rather than
 * /leads?status=accepted. Contact is unmasked throughout: every row here is by
 * definition an accepted relationship, which is exactly the gate PartnerResource wants.
 *
 * Scoping comes from the token, never from a query parameter, so a developer cannot read
 * another developer's roster.
 */
class PartnerController extends Controller
{
    use HandlesListQueries;

    private const SORTABLE = [
        'last_collaborated_at' => 'last_collaborated_at',
        'name' => 'users.name',
        'projects_count' => 'projects_count',
        'created_at' => 'last_collaborated_at',
    ];

    /**
     * GET /api/partners
     *
     * ?page, ?per_page — paged like every other list; per_page is capped server-side.
     * ?search          — broker name, company name or RERA number, prefix-matched.
     * ?city, ?state    — from the broker's registration profile.
     * ?segment         — one of the broker's segments.
     * ?from, ?to       — bounds on when the collaboration was accepted.
     * ?sort, ?direction— see self::SORTABLE.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $developerId = $this->developerId($request);

        $accepted = fn (Builder $q) => $q
            ->where('leads.developer_id', $developerId)
            ->where('leads.status', Lead::STATUS_ACCEPTED);

        $query = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.mobile', 'users.avatar_path', 'users.created_at')
            ->where('users.role', User::ROLE_BROKER)
            ->whereHas('leads', $accepted)
            ->withCount(['leads as projects_count' => $accepted])
            // Subquery rather than a GROUP BY: grouping would fight the paginator's
            // COUNT(*) and force a temp table, and (broker_id, status) is indexed.
            ->addSelect(['last_collaborated_at' => Lead::query()
                ->select('responded_at')
                ->whereColumn('leads.broker_id', 'users.id')
                ->where('leads.developer_id', $developerId)
                ->where('leads.status', Lead::STATUS_ACCEPTED)
                ->orderByDesc('responded_at')
                ->limit(1),
            ])
            ->with(['brokerProfile' => fn ($q) => $q->select(self::PROFILE_COLUMNS)]);

        $this->applyFilters($query, $request, $developerId);

        return PartnerResource::collectionWithContact($this->paginate($query, $request), true);
    }

    /** GET /api/partners/{broker} — one partner, only if the caller has accepted them. */
    public function show(Request $request, User $broker): JsonResponse
    {
        $developerId = $this->developerId($request);

        $lead = Lead::where('broker_id', $broker->id)
            ->where('developer_id', $developerId)
            ->where('status', Lead::STATUS_ACCEPTED)
            ->orderByDesc('responded_at')
            ->first();

        // 404 rather than 403: a developer should not be able to probe which brokers
        // exist by watching the status code change.
        abort_if($lead === null, 404);

        $broker->loadMissing(['brokerProfile' => fn ($q) => $q->select(self::PROFILE_COLUMNS)]);
        $broker->setAttribute('projects_count', Lead::where('broker_id', $broker->id)
            ->where('developer_id', $developerId)
            ->where('status', Lead::STATUS_ACCEPTED)
            ->count());
        $broker->setAttribute('last_collaborated_at', $lead->responded_at);

        return response()->json([
            'data' => (new PartnerResource($broker))->withContact(true),
        ]);
    }

    /**
     * GET /api/partners/{broker}/projects — the listings this partner was accepted on,
     * newest decision first. Paginated like every other list; a partner with hundreds of
     * accepted projects must not arrive as one response.
     *
     * Driven off leads rather than properties so the row carries the decision date, and
     * scoped to the caller's own developer id so this cannot be used to read which of a
     * broker's projects belong to someone else.
     */
    public function projects(Request $request, User $broker): AnonymousResourceCollection
    {
        $developerId = $this->developerId($request);

        $query = Lead::query()
            ->where('broker_id', $broker->id)
            ->where('developer_id', $developerId)
            ->where('status', Lead::STATUS_ACCEPTED)
            ->with(['property:id,name,slug,city,state,locality,cover_image_path,project_status,'
                . 'listing_status,project_type,developer_id'])
            ->whereHas('property');

        $query = $this->applySort($query, $request, [
            'responded_at' => 'responded_at',
            'interested_at' => 'interested_at',
            'created_at' => 'responded_at',
        ], 'responded_at');

        return LeadResource::collection($this->paginate($query, $request));
    }

    /**
     * Columns PartnerResource reads, listed rather than `*` so the identity and banking
     * columns are never loaded on a developer-facing request. See Api\LeadController.
     */
    private const PROFILE_COLUMNS = [
        'id', 'user_id', 'company_name', 'is_company', 'rera_number', 'rera_certificate_expiry',
        'gst_number', 'years_of_experience', 'team_size', 'city', 'state', 'segments', 'zones',
        'operates_multiple_states', 'project_contributions', 'submitted_at', 'photo_path',
        'alternate_mobile', 'company_website', 'social_media_handle', 'office_address',
        'residence_address',
    ];

    private function developerId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user->isDeveloper(), 403);

        $id = $user->developer?->id;
        abort_if($id === null, 403, 'No developer profile linked to this account.');

        return $id;
    }

    /** Every filter is a query parameter; a blank one is dropped, not treated as a value. */
    private function applyFilters(Builder $query, Request $request, int $developerId): void
    {
        $filters = $this->filters($request, ['search', 'city', 'state', 'segment', 'from', 'to']);

        // Prefix match on all three so the index on rera_number stays usable; a leading
        // wildcard would force a full scan of every broker profile.
        if ($term = $filters['search'] ?? null) {
            $query->where(function (Builder $q) use ($term) {
                $q->where('users.name', 'like', $term . '%')
                    ->orWhereHas('brokerProfile', fn (Builder $p) => $p
                        ->where('company_name', 'like', $term . '%')
                        ->orWhere('rera_number', 'like', $term . '%'));
            });
        }

        foreach (['city', 'state'] as $key) {
            if ($value = $filters[$key] ?? null) {
                $query->whereHas('brokerProfile', fn (Builder $p) => $p->where($key, $value));
            }
        }

        if ($segment = $filters['segment'] ?? null) {
            $query->whereHas('brokerProfile', fn (Builder $p) => $p->whereJsonContains('segments', $segment));
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if ($value = $filters[$key] ?? null) {
                $query->whereHas('leads', fn (Builder $q) => $q
                    ->where('leads.developer_id', $developerId)
                    ->where('leads.status', Lead::STATUS_ACCEPTED)
                    ->whereDate('leads.responded_at', $operator, $value));
            }
        }

        $this->applySort($query, $request, self::SORTABLE, 'last_collaborated_at');
    }

    /** Ceiling on the filter option lists, so no request can fan out with the roster. */
    private const MAX_OPTIONS = 100;

    /**
     * GET /api/partners/filters — the values actually present in this developer's roster,
     * so the filter controls only ever offer options that can return a row.
     *
     * Every branch is bounded. City and state are DISTINCT server-side against their own
     * indexes; segments is a JSON column that cannot be, so it reads a capped slice of
     * profiles and flattens it. A developer with more than MAX_OPTIONS distinct segments
     * would see a truncated list — not a realistic roster, and still better than an
     * unbounded scan on a shared box.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $developerId = $this->developerId($request);

        $partnerIds = Lead::query()
            ->select('broker_id')
            ->where('developer_id', $developerId)
            ->where('status', Lead::STATUS_ACCEPTED);

        $distinct = fn (string $column) => BrokerProfile::query()
            ->whereIn('user_id', $partnerIds)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit(self::MAX_OPTIONS)
            ->pluck($column);

        $segments = BrokerProfile::query()
            ->whereIn('user_id', $partnerIds)
            ->whereNotNull('segments')
            ->limit(self::MAX_OPTIONS * 5)
            ->pluck('segments')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->take(self::MAX_OPTIONS)
            ->values();

        return response()->json([
            'data' => [
                'cities' => $distinct('city'),
                'states' => $distinct('state'),
                'segments' => $segments,
            ],
        ]);
    }
}
