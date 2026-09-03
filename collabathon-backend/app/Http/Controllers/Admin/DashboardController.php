<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\ExportsList;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardController extends Controller
{
    use HandlesListQueries, ExportsList;

    /** A peek, not the full page's row count — "Open full page" is one click away. */
    protected function defaultPerPage(): int
    {
        return 8;
    }

    public function __invoke(Request $request): View|SymfonyResponse
    {
        // Same `?export=` convention every list screen uses, read before any of the page's
        // own work — the export answers from the identical URL, so it carries whichever
        // panel, search and filter the dashboard is currently showing.
        if ($format = $this->exportFormat($request)) {
            return $this->exportSectioned(
                $format,
                'dashboard',
                'Dashboard' . ($request->query('panel') ? ' — ' . $this->panelMeta($request->query('panel'))['title'] : ''),
                $this->exportSections($request),
            );
        }

        ['developers' => $developers, 'brokers' => $brokers, 'properties' => $properties,
            'pending' => $pending, 'matches' => $matches, 'drafts' => $drafts] = $this->counts();

        return view('admin.dashboard', [
            // Every tile drills into a row list scoped to that number, expanded inline
            // below the KPI row (`panel()`) rather than navigating to a different page —
            // "Open full page" in the panel header is the way out to the real page for
            // anything the peek doesn't cover. `color` tints only these six tiles (see
            // stat-card.blade.php) — every stat-card elsewhere in the app is untouched.
            'stats' => [
                ['key' => 'developers', 'icon' => 'building', 'label' => self::KPI_LABELS['developers'], 'value' => $developers,
                    'color' => 'info', 'route' => route('admin.dashboard', ['panel' => 'developers']) . '#panel',
                    'spark' => $this->weeklySeries(Developer::query())],
                ['key' => 'properties', 'icon' => 'list', 'label' => 'Listings', 'value' => $properties,
                    // Not chart-2 (a burnt sienna) — it sat right next to 'primary' (a
                    // burnt orange) and the two badges were indistinguishable. See the
                    // palette note in stat-card.blade.php.
                    'color' => 'danger', 'route' => route('admin.dashboard', ['panel' => 'properties']) . '#panel',
                    'spark' => $this->weeklySeries(Property::query())],
                ['key' => 'matches', 'icon' => 'chart', 'label' => 'Confirmed matches', 'value' => $matches,
                    'color' => 'success', 'route' => route('admin.dashboard', ['panel' => 'matches']) . '#panel',
                    'spark' => $this->weeklySeries(Lead::where('status', Lead::STATUS_ACCEPTED))],
                ['key' => 'pending', 'icon' => 'clock', 'label' => 'Pending approvals', 'value' => $pending,
                    'goodWhenUp' => false, 'color' => 'warning',
                    'route' => route('admin.dashboard', ['panel' => 'pending']) . '#panel',
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING))],
                ['key' => 'brokers', 'icon' => 'users', 'label' => 'Active channel partners', 'value' => $brokers,
                    'color' => 'primary', 'route' => route('admin.dashboard', ['panel' => 'brokers']) . '#panel',
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE))],
                // Registrations that were started and abandoned. `goodWhenUp` is false for
                // the same reason Pending approvals sets it: a rising number here is people
                // dropping out of sign-up, not progress.
                ['key' => 'drafts', 'icon' => 'clock', 'label' => 'Registration incomplete', 'value' => $drafts,
                    'goodWhenUp' => false, 'color' => 'neutral',
                    'route' => route('admin.dashboard', ['panel' => 'drafts']) . '#panel',
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT))],
            ],

            'trend' => $this->engagementTrend(),
            'funnel' => $this->funnel(),
            'topProperties' => $this->topProperties(),
            'activity' => $this->activity(),
            'pendingBrokers' => User::role(User::ROLE_BROKER)
                ->status(User::STATUS_PENDING)
                ->with('brokerProfile:id,user_id,company_name,city,photo_path')
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

    private const PANELS = ['developers', 'brokers', 'properties', 'pending', 'matches', 'drafts'];

    /** What each KPI tile is called, in the order the row renders them. */
    private const KPI_LABELS = [
        'developers' => 'Developers',
        'brokers' => 'Active channel partners',
        'properties' => 'Listings',
        'pending' => 'Pending approvals',
        'matches' => 'Confirmed matches',
        'drafts' => 'Registration incomplete',
    ];

    /**
     * The six figures on the KPI row, counted once and used by both the page and the
     * export — a spreadsheet that disagrees with the screen it was exported from is worse
     * than no spreadsheet.
     *
     * @return array<string,int>
     */
    private function counts(): array
    {
        return [
            'developers' => Developer::count(),
            'brokers' => User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)->count(),
            'properties' => Property::count(),
            'pending' => User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)->count(),
            'matches' => Lead::where('status', Lead::STATUS_ACCEPTED)->count(),
            // Channel partners who began the 3-step sign-up and never submitted it —
            // `draft` is the status startRegistration() creates and only a real step-3
            // submit moves off (see AuthController), so this is exactly "started, not
            // finished" rather than anything an admin has yet to action.
            'drafts' => User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT)->count(),
        ];
    }

    /**
     * The dashboard is a set of small tables rather than one filtered list, so it builds
     * its own sections and hands them to {@see ExportsList::exportSectioned()} instead of
     * calling exportList() the way every other screen does. Same two formats, same file
     * naming, same print sheet.
     *
     * Exports what is on screen: the summary always, plus the expanded panel's rows when
     * a tile is open, honouring the same `search`/`status` the panel is showing.
     *
     * @return array<int,array{title:string,headings:array<int,string>,rows:array<int,array<int,string|int>>,note?:string}>
     */
    private function exportSections(Request $request): array
    {
        $funnel = $this->funnel();
        [$viewed, $interested, $accepted] = array_map(fn ($stage) => $stage['value'], $funnel);

        $summary = [];

        foreach ($this->counts() as $key => $value) {
            $summary[] = [self::KPI_LABELS[$key], $value];
        }

        foreach ($funnel as $stage) {
            $summary[] = ['Interests — ' . $stage['stage'], $stage['value']];
        }

        // Percentages as text: a spreadsheet cell holding 62.5 and a cell holding "62.5%"
        // are different claims, and this one is a rate, not a count to be summed.
        $summary[] = ['Interest rate', $this->rate($interested, $viewed)];
        $summary[] = ['Accept rate', $this->rate($accepted, $interested)];
        $summary[] = ['Overall conversion', $this->rate($accepted, $viewed)];

        $sections = [
            [
                'title' => 'Summary',
                'headings' => ['Metric', 'Value'],
                'rows' => $summary,
            ],
            [
                'title' => 'Weekly engagement',
                'headings' => ['Week starting', 'Views', 'Interests'],
                'rows' => array_map(
                    fn ($point) => [$point['label'], $point['views'], $point['interests']],
                    $this->engagementTrend(),
                ),
            ],
            [
                'title' => 'Top listings',
                'headings' => ['Listing', 'Developer', 'Interests'],
                'rows' => array_map(
                    fn ($row) => [$row['label'], $row['meta'] ?? '—', $row['value']],
                    $this->topProperties(),
                ),
            ],
        ];

        if ($panel = $this->exportPanel($request)) {
            $sections[] = $panel;
        }

        return $sections;
    }

    /**
     * The expanded tile's rows, as their own section — the same query the panel on screen
     * is showing, unpaginated, so the file matches the peek that prompted the export.
     *
     * @return array{title:string,headings:array<int,string>,rows:array<int,array<int,string|int>>,note?:string}|null
     */
    private function exportPanel(Request $request): ?array
    {
        $panel = $request->query('panel');

        if (! in_array($panel, self::PANELS, true)) {
            return null;
        }

        $meta = $this->panelMeta($panel);
        $cap = $this->exportRowCap();
        // One row over the cap, so "there was more" is a fact rather than a guess.
        $rows = $this->panelQuery($panel, $request)->limit($cap + 1)->get();

        $truncated = $rows->count() > $cap;
        $rows = $rows->take($cap);

        $date = fn ($value) => $value ? Carbon::parse($value)->format('d M Y') : '—';

        [$headings, $mapped] = match ($meta['key']) {
            'developers' => [
                ['Company', 'Contact person', 'Email', 'Mobile', 'City', 'Status', 'Added'],
                $rows->map(fn ($d) => [
                    $d->company_name, $d->contact_person, $d->email, $d->mobile,
                    $d->city ?: '—', ucfirst((string) $d->status), $date($d->created_at),
                ])->all(),
            ],
            'properties' => [
                ['Listing', 'Developer', 'City', 'Status', 'Interests', 'Added'],
                $rows->map(fn ($p) => [
                    $p->name, $p->developer?->company_name ?: '—', $p->city ?: '—',
                    ucfirst(str_replace('_', ' ', (string) $p->listing_status)),
                    (int) $p->interests_count, $date($p->created_at),
                ])->all(),
            ],
            'activity' => [
                ['When', 'Action', 'By', 'Subject'],
                $rows->map(fn ($a) => [
                    $a->occurred_at ? Carbon::parse($a->occurred_at)->format('d M Y, H:i') : '—',
                    ActivityController::TYPES[$a->type]['label'] ?? $a->type,
                    $a->actor_name ?: '—',
                    $a->subject_name ?: '—',
                ])->all(),
            ],
            // brokers and pending are the same record at two stages, so one mapping.
            default => [
                ['Name', 'Company', 'Email', 'Mobile', 'City', $meta['key'] === 'pending' ? 'Submitted' : 'Joined'],
                $rows->map(fn ($u) => [
                    $u->name, $u->brokerProfile?->company_name ?: 'Individual', $u->email,
                    $u->mobile ?: '—', $u->brokerProfile?->city ?: '—', $date($u->created_at),
                ])->all(),
            ],
        };

        return array_filter([
            'title' => $meta['title'],
            'headings' => $headings,
            'rows' => $mapped,
            'note' => $truncated
                ? 'Showing the first ' . number_format($cap) . ' rows in this panel — open the full page '
                    . 'or narrow the search to capture the rest.'
                : null,
        ], fn ($value) => $value !== null);
    }

    private function rate(int $part, int $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';
    }

    /**
     * Row data for whichever KPI tile is expanded below the KPI row, if any.
     *
     * The heading and the query are split ({@see panelMeta()}, {@see panelQuery()})
     * because the export needs the same rows without a paginator around them — one
     * definition, so a filter that applies on screen cannot go missing from the file.
     */
    private function panel(Request $request): ?array
    {
        $panel = $request->query('panel');

        if (! in_array($panel, self::PANELS, true)) {
            return null;
        }

        return $this->panelMeta($panel) + [
            'rows' => $this->panelQuery($panel, $request)
                ->paginate($this->perPage($request))
                ->withQueryString(),
        ];
    }

    /**
     * Everything about a panel except its rows: what it is called, how it is tinted, and
     * where "Open full page" goes.
     *
     * @return array<string,mixed>
     */
    private function panelMeta(string $panel): array
    {
        return match ($panel) {
            'developers' => [
                'key' => 'developers', 'title' => 'Developers', 'color' => 'info',
                'subtitle' => 'Every developer company on the platform.',
                'label' => 'developers', 'fullRoute' => route('admin.developers'),
                'searchPlaceholder' => 'Search by company, contact or email…',
            ],
            'brokers' => [
                'key' => 'brokers', 'title' => 'Active channel partners', 'color' => 'primary',
                'subtitle' => 'Channel partners already through approval.',
                'label' => 'channel partners', 'fullRoute' => route('admin.cp'),
                'searchPlaceholder' => 'Search by name, company or email…',
            ],
            'properties' => [
                'key' => 'properties', 'title' => 'Listings', 'color' => 'danger',
                'subtitle' => 'Every project, live or not, across every developer.',
                'label' => 'listings', 'fullRoute' => route('admin.properties'),
                'searchPlaceholder' => 'Search by project name…',
            ],
            'pending' => [
                'key' => 'pending', 'title' => 'Pending approvals', 'color' => 'warning',
                'subtitle' => 'Channel partners waiting on a decision.',
                'label' => 'registrations', 'fullRoute' => route('admin.approvals'),
                'searchPlaceholder' => 'Search by name, company or email…',
            ],
            // `key` is what the panel view switches on to pick a row layout, not the panel
            // slug — 'pending' renders the channel-partner row (avatar, company, city),
            // which is exactly what a half-finished registration is.
            'drafts' => [
                'key' => 'pending', 'title' => 'Registration incomplete', 'color' => 'neutral',
                'subtitle' => 'Channel partners who started signing up and never submitted.',
                'label' => 'registrations', 'fullRoute' => route('admin.approvals'),
                'searchPlaceholder' => 'Search by name, company or email…',
            ],
            'matches' => [
                'key' => 'activity', 'title' => 'Confirmed matches', 'color' => 'success',
                'subtitle' => 'Every interest a developer has accepted.',
                'label' => 'matches',
                'fullRoute' => route('admin.activity', ['type' => 'lead_accepted']),
                'searchPlaceholder' => 'Search by person or project…',
            ],
        };
    }

    /**
     * The rows behind a panel, ordered but not yet paginated.
     *
     * Each branch reuses the exact same role/status scope its tile's count above is built
     * from, so what a tile says and what its panel lists can never disagree. Search only —
     * not the full filter set the dedicated page has, since this is a peek, not a
     * replacement for that page (still one click away via "Open full page").
     */
    private function panelQuery(string $panel, Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        return match ($panel) {
            'developers' => Developer::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('company_name', 'like', "{$search}%")
                    ->orWhere('contact_person', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%")))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest(),

            'brokers' => User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)
                ->with('brokerProfile:id,user_id,company_name,city,photo_path')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%")
                    ->orWhereHas('brokerProfile', fn ($p) => $p
                        ->where('company_name', 'like', "{$search}%"))))
                ->latest(),

            'properties' => Property::query()
                ->with('developer:id,company_name')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "{$search}%"))
                ->when($request->query('status'), fn ($q, $v) => $q->where('listing_status', $v))
                ->latest(),

            'pending' => User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)
                ->with('brokerProfile:id,user_id,company_name,city,photo_path')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%")
                    ->orWhereHas('brokerProfile', fn ($p) => $p
                        ->where('company_name', 'like', "{$search}%"))))
                ->latest(),

            'drafts' => User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT)
                ->with('brokerProfile:id,user_id,company_name,city,photo_path')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%")
                    ->orWhereHas('brokerProfile', fn ($p) => $p
                        ->where('company_name', 'like', "{$search}%"))))
                ->latest(),

            'matches' => ActivityController::baseQuery()
                ->where('type', 'lead_accepted')
                ->when($search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('subject_name', 'like', "%{$search}%")))
                ->orderByDesc('occurred_at')
                // Ties (same-second events) still need a stable order across pages.
                ->orderByDesc('ref_id'),
        };
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
