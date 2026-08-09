<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use HandlesListQueries;

    /** A peek, not the full page's row count — "Open full page" is one click away. */
    protected function defaultPerPage(): int
    {
        return 8;
    }

    public function __invoke(Request $request): View
    {
        $developers = Developer::count();
        $brokers = User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)->count();
        $properties = Property::count();
        $pending = User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)->count();
        $matches = Lead::where('status', Lead::STATUS_ACCEPTED)->count();
        // Same definition ActivityController::index() paginates — counted from the
        // identical query so this tile and that page's total can never disagree.
        $actions = ActivityController::baseQuery()->count();

        return view('admin.dashboard', [
            // Every tile drills into a row list scoped to that number, expanded inline
            // below the KPI row (`panel()`) rather than navigating to a different page —
            // "Open full page" in the panel header is the way out to the real page for
            // anything the peek doesn't cover. `color` tints only these six tiles (see
            // stat-card.blade.php) — every stat-card elsewhere in the app is untouched.
            'stats' => [
                ['key' => 'developers', 'icon' => 'building', 'label' => 'Developers', 'value' => $developers,
                    'color' => 'info', 'route' => route('admin.dashboard', ['panel' => 'developers']) . '#panel',
                    'spark' => $this->weeklySeries(Developer::query())],
                ['key' => 'brokers', 'icon' => 'users', 'label' => 'Active brokers', 'value' => $brokers,
                    'color' => 'primary', 'route' => route('admin.dashboard', ['panel' => 'brokers']) . '#panel',
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE))],
                ['key' => 'properties', 'icon' => 'list', 'label' => 'Listings', 'value' => $properties,
                    // Not chart-2 (a burnt sienna) — it sat right next to 'primary' (a
                    // burnt orange) and the two badges were indistinguishable. See the
                    // palette note in stat-card.blade.php.
                    'color' => 'danger', 'route' => route('admin.dashboard', ['panel' => 'properties']) . '#panel',
                    'spark' => $this->weeklySeries(Property::query())],
                ['key' => 'pending', 'icon' => 'clock', 'label' => 'Pending approvals', 'value' => $pending,
                    'goodWhenUp' => false, 'color' => 'warning',
                    'route' => route('admin.dashboard', ['panel' => 'pending']) . '#panel',
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING))],
                ['key' => 'matches', 'icon' => 'chart', 'label' => 'Confirmed matches', 'value' => $matches,
                    'color' => 'success', 'route' => route('admin.dashboard', ['panel' => 'matches']) . '#panel',
                    'spark' => $this->weeklySeries(Lead::where('status', Lead::STATUS_ACCEPTED))],
                ['key' => 'actions', 'icon' => 'chart', 'label' => 'Total actions', 'value' => $actions,
                    'color' => 'neutral', 'route' => route('admin.dashboard', ['panel' => 'actions']) . '#panel',
                    'spark' => $this->activityWeeklySeries()],
            ],

            'trend' => $this->engagementTrend(),
            'funnel' => $this->funnel(),
            'topProperties' => $this->topProperties(),
            'activity' => $this->activity(),
            'pendingBrokers' => User::role(User::ROLE_BROKER)
                ->status(User::STATUS_PENDING)
                ->with('brokerProfile:id,user_id,company_name,city')
                ->latest()
                ->limit(4)
                ->get(),

            'panel' => $this->panel($request),
        ]);
    }

    /**
     * The panel alone, no layout — what `admin/dashboard/panel` in app.js's fetch()
     * calls resolves to. Same `panel()` build as the full page, so a hard refresh on
     * `?panel=…` and an AJAX open of the same tile can never render differently.
     */
    public function fragment(Request $request): View
    {
        return view('admin.partials.dashboard-panel', ['panel' => $this->panel($request)]);
    }

    private const PANELS = ['developers', 'brokers', 'properties', 'pending', 'matches', 'actions'];

    /**
     * Row data for whichever KPI tile is expanded below the KPI row, if any. Each
     * branch reuses the exact same role/status scope its tile's count above is built
     * from, so what a tile says and what its panel lists can never disagree. Search
     * only — not the full filter set the dedicated page has, since this is a peek,
     * not a replacement for that page (still one click away via "Open full page").
     */
    private function panel(Request $request): ?array
    {
        $panel = $request->query('panel');

        if (! in_array($panel, self::PANELS, true)) {
            return null;
        }

        $search = trim((string) $request->query('search', ''));

        return match ($panel) {
            'developers' => [
                'key' => 'developers', 'title' => 'Developers', 'color' => 'info',
                'subtitle' => 'Every developer company on the platform.',
                'label' => 'developers', 'fullRoute' => route('admin.developers'),
                'searchPlaceholder' => 'Search by company, contact or email…',
                'rows' => Developer::query()
                    ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                        ->where('company_name', 'like', "{$search}%")
                        ->orWhere('contact_person', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%")))
                    ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                    ->latest()
                    ->paginate($this->perPage($request))
                    ->withQueryString(),
            ],
            'brokers' => [
                'key' => 'brokers', 'title' => 'Active brokers', 'color' => 'primary',
                'subtitle' => 'Channel partners already through approval.',
                'label' => 'brokers', 'fullRoute' => route('admin.cp'),
                'searchPlaceholder' => 'Search by name, company or email…',
                'rows' => User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)
                    ->with('brokerProfile:id,user_id,company_name,city')
                    ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                        ->where('name', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%")
                        ->orWhereHas('brokerProfile', fn ($p) => $p
                            ->where('company_name', 'like', "{$search}%"))))
                    ->latest()
                    ->paginate($this->perPage($request))
                    ->withQueryString(),
            ],
            'properties' => [
                'key' => 'properties', 'title' => 'Listings', 'color' => 'danger',
                'subtitle' => 'Every project, live or not, across every developer.',
                'label' => 'listings', 'fullRoute' => route('admin.properties'),
                'searchPlaceholder' => 'Search by project name…',
                'rows' => Property::query()
                    ->with('developer:id,company_name')
                    ->when($search !== '', fn ($q) => $q->where('name', 'like', "{$search}%"))
                    ->when($request->query('status'), fn ($q, $v) => $q->where('listing_status', $v))
                    ->latest()
                    ->paginate($this->perPage($request))
                    ->withQueryString(),
            ],
            'pending' => [
                'key' => 'pending', 'title' => 'Pending approvals', 'color' => 'warning',
                'subtitle' => 'Channel partners waiting on a decision.',
                'label' => 'registrations', 'fullRoute' => route('admin.approvals'),
                'searchPlaceholder' => 'Search by name, company or email…',
                'rows' => User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)
                    ->with('brokerProfile:id,user_id,company_name,city')
                    ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                        ->where('name', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%")
                        ->orWhereHas('brokerProfile', fn ($p) => $p
                            ->where('company_name', 'like', "{$search}%"))))
                    ->latest()
                    ->paginate($this->perPage($request))
                    ->withQueryString(),
            ],
            'matches', 'actions' => $this->activityPanel($panel, $search, $request),
        };
    }

    /** "Confirmed matches" and "Total actions" are the same feed, one type-filtered. */
    private function activityPanel(string $panel, string $search, Request $request): array
    {
        $type = $panel === 'matches' ? 'lead_accepted' : '';

        $rows = ActivityController::baseQuery()
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('actor_name', 'like', "%{$search}%")
                ->orWhere('subject_name', 'like', "%{$search}%")))
            ->orderByDesc('occurred_at')
            ->orderByDesc('ref_id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return [
            'key' => 'activity',
            'title' => $panel === 'matches' ? 'Confirmed matches' : 'Total actions',
            'color' => $panel === 'matches' ? 'success' : null,
            'subtitle' => $panel === 'matches'
                ? 'Every interest a developer has accepted.'
                : 'Every recorded action across the platform.',
            'label' => $panel === 'matches' ? 'matches' : 'actions',
            'fullRoute' => route('admin.activity', $type !== '' ? ['type' => $type] : []),
            'searchPlaceholder' => 'Search by person or project…',
            'rows' => $rows,
        ];
    }

    /**
     * 12-week cumulative running total for a stat tile sparkline.
     * One grouped query per tile — never a query per week.
     */
    private function weeklySeries($query): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        $rows = (clone $query)
            ->where('created_at', '>=', $start)
            ->selectRaw('YEARWEEK(created_at, 3) as yw, COUNT(*) as c')
            ->groupBy('yw')
            ->pluck('c', 'yw');

        // Anything created before the window is the baseline for week 1.
        $baseline = (clone $query)->where('created_at', '<', $start)->count();

        $series = [];
        $running = $baseline;

        for ($i = 0; $i < 12; $i++) {
            $week = $start->copy()->addWeeks($i);
            $key = (int) $week->format('oW');
            $running += (int) ($rows[$key] ?? 0);
            $series[] = $running;
        }

        return $series;
    }

    /**
     * Same shape as {@see weeklySeries()}, for the one tile whose source isn't a
     * single Eloquent builder: the activity union groups on `occurred_at` (the column
     * every branch of that query already produces) instead of `created_at`.
     */
    private function activityWeeklySeries(): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        $rows = ActivityController::baseQuery()
            ->where('occurred_at', '>=', $start)
            ->selectRaw('YEARWEEK(occurred_at, 3) as yw, COUNT(*) as c')
            ->groupBy('yw')
            ->pluck('c', 'yw');

        $baseline = ActivityController::baseQuery()->where('occurred_at', '<', $start)->count();

        $series = [];
        $running = $baseline;

        for ($i = 0; $i < 12; $i++) {
            $week = $start->copy()->addWeeks($i);
            $key = (int) $week->format('oW');
            $running += (int) ($rows[$key] ?? 0);
            $series[] = $running;
        }

        return $series;
    }

    /** Weekly views vs interests for the trend chart — two grouped queries, not 24. */
    private function engagementTrend(): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        $grouped = Lead::where('created_at', '>=', $start)
            ->selectRaw('YEARWEEK(created_at, 3) as yw')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('SUM(CASE WHEN contact_unlocked = 1 THEN 1 ELSE 0 END) as interests')
            ->groupBy('yw')
            ->get()
            ->keyBy('yw');

        $points = [];

        for ($i = 0; $i < 12; $i++) {
            $week = $start->copy()->addWeeks($i);
            $row = $grouped[(int) $week->format('oW')] ?? null;

            $points[] = [
                'label' => $week->format('j M'),
                'views' => (int) ($row->views ?? 0),
                'interests' => (int) ($row->interests ?? 0),
            ];
        }

        return $points;
    }

    private function funnel(): array
    {
        $counts = Lead::selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN contact_unlocked = 1 THEN 1 ELSE 0 END) as interested')
            ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            ->first();

        return [
            ['stage' => 'Viewed', 'value' => (int) $counts->total],
            ['stage' => 'Interested', 'value' => (int) $counts->interested],
            ['stage' => 'Accepted', 'value' => (int) $counts->accepted],
        ];
    }

    private function topProperties(): array
    {
        return Property::query()
            ->select('id', 'name', 'developer_id', 'interests_count')
            ->with('developer:id,company_name')
            ->orderByDesc('interests_count')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'label' => $p->name,
                'meta' => $p->developer?->company_name,
                'value' => $p->interests_count,
            ])
            ->all();
    }

    /** Recent cross-entity activity, assembled from the three sources that matter. */
    private function activity(): array
    {
        $items = [];

        $registrations = User::role(User::ROLE_BROKER)
            ->status(User::STATUS_PENDING)
            ->latest()
            ->limit(3)
            ->get(['id', 'name', 'created_at']);

        foreach ($registrations as $u) {
            $items[] = [
                'icon' => 'users', 'tone' => 'info', 'at' => $u->created_at,
                'text' => '<strong>' . e($u->name) . '</strong> submitted a broker registration',
            ];
        }

        $decided = Lead::whereIn('status', [Lead::STATUS_ACCEPTED, Lead::STATUS_DECLINED])
            ->with(['broker:id,name', 'property:id,name'])
            ->latest('responded_at')
            ->limit(3)
            ->get();

        foreach ($decided as $lead) {
            $items[] = [
                'icon' => $lead->status === Lead::STATUS_ACCEPTED ? 'check' : 'x',
                'tone' => $lead->status === Lead::STATUS_ACCEPTED ? 'success' : 'danger',
                'at' => $lead->responded_at ?? $lead->updated_at,
                'text' => '<strong>' . e($lead->broker?->name) . '</strong>&rsquo;s interest in '
                    . e($lead->property?->name) . ' was ' . $lead->status,
            ];
        }

        $listings = Property::with('developer:id,company_name')
            ->latest()
            ->limit(2)
            ->get(['id', 'name', 'developer_id', 'created_at']);

        foreach ($listings as $p) {
            $items[] = [
                'icon' => 'building', 'tone' => 'neutral', 'at' => $p->created_at,
                'text' => '<strong>' . e($p->developer?->company_name) . '</strong> was assigned &ldquo;'
                    . e($p->name) . '&rdquo;',
            ];
        }

        return collect($items)
            ->sortByDesc('at')
            ->take(5)
            ->map(fn ($i) => $i + ['time' => optional($i['at'])->diffForHumans()])
            ->values()
            ->all();
    }
}
