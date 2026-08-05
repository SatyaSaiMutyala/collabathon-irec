<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use App\Models\Property;
use App\Services\PushNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The request/approval funnel, browsed the way an admin actually thinks about it:
 * which developer, which of their projects, who asked and how it was decided. Three
 * screens, each a drill-down into the one before —
 *
 *   index()     developers, ranked by request volume, with accept/decline/pending counts
 *   developer() one developer's projects, same shape of counts, per project
 *   project()   one project's individual requests — the old flat table, now scoped
 *
 * "Requests" means the broker got past a passive view — status is interested, accepted
 * or declined. A bare `viewed` row is not a request; nobody asked for anything yet.
 */
class LeadController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 12;
    }

    private const SORTABLE = [
        'created_at' => 'created_at',
        'status' => 'status',
    ];

    /** Shared SQL for the four counts every tier of this drill-down shows. */
    private const REQUESTS_SQL = "SUM(CASE WHEN leads.status != 'viewed' THEN 1 ELSE 0 END)";
    private const ACCEPTED_SQL = "SUM(CASE WHEN leads.status = 'accepted' THEN 1 ELSE 0 END)";
    private const DECLINED_SQL = "SUM(CASE WHEN leads.status = 'declined' THEN 1 ELSE 0 END)";
    private const PENDING_SQL = "SUM(CASE WHEN leads.status = 'interested' THEN 1 ELSE 0 END)";

    /** Tier 1 — every developer with a request funnel, ranked by volume. */
    public function index(Request $request): View
    {
        $propertyId = $request->query('property_id');
        $search = trim((string) $request->query('search', ''));

        // Picking a project narrows the roster to that project's one owner, with every
        // count scoped to just that project — "requests" then means requests on it,
        // not on the developer's whole portfolio.
        $ownerId = $propertyId ? Property::whereKey($propertyId)->value('developer_id') : null;

        $developers = Developer::query()
            ->leftJoin('leads', function ($join) use ($propertyId) {
                $join->on('leads.developer_id', '=', 'developers.id');
                if ($propertyId) {
                    $join->where('leads.property_id', $propertyId);
                }
            })
            ->when($ownerId, fn ($q) => $q->where('developers.id', $ownerId))
            ->when($search !== '', fn ($q) => $q->where('developers.company_name', 'like', "%{$search}%"))
            ->groupBy('developers.id')
            ->selectRaw(
                'developers.*, '
                . self::REQUESTS_SQL . ' as requests_count, '
                . self::ACCEPTED_SQL . ' as accepted_count, '
                . self::DECLINED_SQL . ' as declined_count, '
                . self::PENDING_SQL . ' as pending_count'
            )
            ->orderByDesc('requests_count')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $leadsForCounts = Lead::query()->when($propertyId, fn ($q) => $q->where('property_id', $propertyId));

        return view('admin.leads', [
            'developers' => $developers,
            'properties' => Property::orderBy('name')->pluck('name', 'id'),
            'selectedProperty' => $propertyId ? Property::find($propertyId) : null,
            'stats' => $this->funnelStats($leadsForCounts),
            'trend' => $this->requestTrend($propertyId),
            'topDevelopers' => $this->topDevelopers($propertyId),
        ]);
    }

    /** Tier 2 — one developer's projects, each with the same funnel. */
    public function developer(Request $request, Developer $developer): View
    {
        $search = trim((string) $request->query('search', ''));
        $projectType = $request->query('project_type');

        $properties = Property::query()
            ->leftJoin('leads', 'leads.property_id', '=', 'properties.id')
            ->where('properties.developer_id', $developer->id)
            ->when($search !== '', fn ($q) => $q->where('properties.name', 'like', "%{$search}%"))
            ->when($projectType, fn ($q) => $q->where('properties.project_type', $projectType))
            ->groupBy('properties.id')
            ->selectRaw(
                'properties.*, '
                . self::REQUESTS_SQL . ' as requests_count, '
                . self::ACCEPTED_SQL . ' as accepted_count, '
                . self::DECLINED_SQL . ' as declined_count, '
                . self::PENDING_SQL . ' as pending_count'
            )
            ->orderByDesc('requests_count')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('admin.leads.developer', [
            'developer' => $developer,
            'properties' => $properties,
            'projectTypes' => Property::where('developer_id', $developer->id)
                ->distinct()
                ->orderBy('project_type')
                ->pluck('project_type', 'project_type'),
            'stats' => $this->funnelStats(Lead::where('developer_id', $developer->id)),
            'trend' => $this->requestTrend(null, $developer->id),
        ]);
    }

    /** Tier 3 — one project's individual requests, the old flat table scoped to it. */
    public function project(Request $request, Developer $developer, Property $property): View
    {
        abort_unless($property->developer_id === $developer->id, 404);

        $toneMap = ['viewed' => 'neutral', 'interested' => 'warning', 'accepted' => 'success', 'declined' => 'danger'];

        $query = Lead::where('property_id', $property->id)
            ->with(['broker:id,name', 'broker.brokerProfile:id,user_id,photo_path'])
            ->search($request->query('search'))
            ->filter($this->filters($request, ['status']));

        $query = $this->applySort($query, $request, self::SORTABLE);

        return view('admin.leads.project', [
            'developer' => $developer,
            'property' => $property,
            'leads' => $this->paginate($query, $request),
            'stats' => $this->funnelStats(Lead::where('property_id', $property->id)),
            'trend' => $this->requestTrend((string) $property->id),
            'toneMap' => $toneMap,
        ]);
    }

    /** The whole interaction behind a row: who, which project, and how it progressed. */
    public function show(Lead $lead): View
    {
        $this->authorize('view-module', 'leads');

        $lead->load([
            'broker.brokerProfile',
            'property.developer',
            'developer',
        ]);

        return view('admin.leads.show', [
            'lead' => $lead,
            // Other activity by the same broker, for context on whether this is a serious lead.
            'brokerActivity' => Lead::where('broker_id', $lead->broker_id)
                ->where('id', '!=', $lead->id)
                ->with('property:id,name')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Move a lead to any stage, in either direction.
     *
     * Nothing here is one-way: an admin who accepts by mistake can decline, and a declined
     * lead can be reopened. The three timestamps and the contact gate are all *derived* from
     * the target status rather than nudged incrementally, which is what makes going backwards
     * leave a coherent record instead of a half-updated one.
     */
    public function update(Request $request, Lead $lead, PushNotifier $push): RedirectResponse
    {
        $this->authorize('edit-module', 'leads');

        $data = $request->validate([
            'status' => ['required', 'in:viewed,interested,accepted,declined'],
            'developer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = $data['status'];
        $past = in_array($status, [Lead::STATUS_INTERESTED, Lead::STATUS_ACCEPTED, Lead::STATUS_DECLINED], true);
        $responded = in_array($status, [Lead::STATUS_ACCEPTED, Lead::STATUS_DECLINED], true);

        $lead->update([
            'status' => $status,
            // The privacy gate follows the stage: contact is revealed at "interested" and
            // stays revealed. Reverting to "viewed" re-locks it for the developer going
            // forward — it cannot un-see what was already shown.
            'contact_unlocked' => $past,
            'viewed_at' => $lead->viewed_at ?? now(),
            'interested_at' => $past ? ($lead->interested_at ?? now()) : null,
            'responded_at' => $responded ? ($lead->responded_at ?? now()) : null,
            'developer_note' => $data['developer_note'] ?? $lead->developer_note,
        ]);

        // The admin can move a lead in either direction; only the two decisions the
        // broker is actually waiting on are worth a push.
        match ($status) {
            Lead::STATUS_ACCEPTED => $push->requestAccepted($lead->refresh()),
            Lead::STATUS_DECLINED => $push->requestDeclined($lead->refresh()),
            Lead::STATUS_INTERESTED => $push->requestReceived($lead->refresh()),
            default => null,
        };

        return back()->with('success', "Lead moved to {$status}.");
    }

    // ------------------------------------------------------------------ analytics

    /** Requests / accepted / pending / declined over the given lead scope, for stat cards. */
    private function funnelStats($query): object
    {
        return (clone $query)
            ->selectRaw(
                self::REQUESTS_SQL . ' as requests, '
                . self::ACCEPTED_SQL . ' as accepted, '
                . self::PENDING_SQL . ' as pending, '
                . self::DECLINED_SQL . ' as declined'
            )
            ->first();
    }

    /**
     * 12-week weekly requests/accepted/declined, optionally scoped to one project or
     * one developer's whole portfolio — one grouped query, not one per week.
     */
    private function requestTrend(?string $propertyId, ?int $developerId = null): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        $grouped = Lead::where('created_at', '>=', $start)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->when($developerId, fn ($q) => $q->where('developer_id', $developerId))
            ->selectRaw('YEARWEEK(created_at, 3) as yw')
            ->selectRaw(self::REQUESTS_SQL . ' as requests')
            ->selectRaw(self::ACCEPTED_SQL . ' as accepted')
            ->selectRaw(self::DECLINED_SQL . ' as declined')
            ->groupBy('yw')
            ->get()
            ->keyBy('yw');

        $points = [];

        for ($i = 0; $i < 12; $i++) {
            $week = $start->copy()->addWeeks($i);
            $row = $grouped[(int) $week->format('oW')] ?? null;

            $points[] = [
                'label' => $week->format('j M'),
                'requests' => (int) ($row->requests ?? 0),
                'accepted' => (int) ($row->accepted ?? 0),
                'declined' => (int) ($row->declined ?? 0),
            ];
        }

        return $points;
    }

    /** Top 5 developers by request volume, optionally scoped to one project. */
    private function topDevelopers(?string $propertyId): array
    {
        return Developer::query()
            ->leftJoin('leads', function ($join) use ($propertyId) {
                $join->on('leads.developer_id', '=', 'developers.id');
                if ($propertyId) {
                    $join->where('leads.property_id', $propertyId);
                }
            })
            ->groupBy('developers.id')
            ->selectRaw('developers.id, developers.company_name, developers.city, ' . self::REQUESTS_SQL . ' as requests_count')
            ->having('requests_count', '>', 0)
            ->orderByDesc('requests_count')
            ->limit(5)
            ->get()
            ->map(fn ($d) => ['label' => $d->company_name, 'meta' => $d->city, 'value' => (int) $d->requests_count])
            ->all();
    }
}
