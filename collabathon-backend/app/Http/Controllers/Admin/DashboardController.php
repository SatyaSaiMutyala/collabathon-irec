<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $developers = Developer::count();
        $brokers = User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE)->count();
        $properties = Property::count();
        $pending = User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)->count();
        $matches = Lead::where('status', Lead::STATUS_ACCEPTED)->count();

        return view('admin.dashboard', [
            'stats' => [
                ['key' => 'developers', 'icon' => 'building', 'label' => 'Developers', 'value' => $developers,
                    'spark' => $this->weeklySeries(Developer::query())],
                ['key' => 'brokers', 'icon' => 'users', 'label' => 'Active brokers', 'value' => $brokers,
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE))],
                ['key' => 'properties', 'icon' => 'list', 'label' => 'Listings', 'value' => $properties,
                    'spark' => $this->weeklySeries(Property::query())],
                ['key' => 'pending', 'icon' => 'clock', 'label' => 'Pending approvals', 'value' => $pending,
                    'goodWhenUp' => false,
                    'spark' => $this->weeklySeries(User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING))],
                ['key' => 'matches', 'icon' => 'chart', 'label' => 'Confirmed matches', 'value' => $matches,
                    'spark' => $this->weeklySeries(Lead::where('status', Lead::STATUS_ACCEPTED))],
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
        ]);
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
