<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /api/v1/dashboard — headline figures for the signed-in user. Developers get
 * the full listing/lead/views breakdown below; a broker gets the much smaller
 * `brokerSummary()` shape instead — just the two counts their own home screen's
 * board needs (requests sent, associations), mirroring the developer board's
 * "CP Interest"/"Introductions" pair but scoped to leads *this broker* sent
 * rather than leads a developer received.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isBroker()) {
            return $this->brokerSummary($user);
        }

        abort_unless($user->isDeveloper(), 403);

        $developerId = $user->developer?->id;
        abort_if($developerId === null, 403, 'No developer profile linked to this account.');

        $leadCounts = Lead::where('developer_id', $developerId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN contact_unlocked = 1 THEN 1 ELSE 0 END) as unlocked')
            ->selectRaw("SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested")
            ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            ->selectRaw("SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined")
            ->first();

        $propertyStats = Property::where('developer_id', $developerId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN listing_status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw('COALESCE(SUM(views_count), 0) as views')
            ->first();

        return response()->json([
            'data' => [
                'properties' => (int) $propertyStats->total,
                'active_properties' => (int) $propertyStats->active,
                'total_views' => (int) $propertyStats->views,
                'leads' => (int) $leadCounts->total,
                'interested' => (int) $leadCounts->interested,
                'accepted' => (int) $leadCounts->accepted,
                'declined' => (int) $leadCounts->declined,
                'weekly_views' => $this->series($developerId, 'week'),
                'monthly_views' => $this->series($developerId, 'month'),
            ],
        ]);
    }

    /**
     * The broker-side board: how many properties they've sent interest requests
     * for, and how many of those a developer has accepted (an "association" —
     * the same `accepted` status PartnersScreen already lists under that name).
     * `broker_id` scopes this to the signed-in broker alone, same trust boundary
     * `LeadController::index` already applies to every other broker-facing list.
     */
    private function brokerSummary(User $user): JsonResponse
    {
        $leadCounts = Lead::where('broker_id', $user->id)
            ->selectRaw("SUM(CASE WHEN status = 'interested' THEN 1 ELSE 0 END) as interested")
            ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            ->first();

        return response()->json([
            'data' => [
                'requests_sent' => (int) $leadCounts->interested,
                'associations' => (int) $leadCounts->accepted,
            ],
        ]);
    }

    /**
     * Lead activity bucketed for the trend chart: last 7 days, or the last 5 weeks.
     * One grouped query either way.
     *
     * @return array{labels: array<int,string>, values: array<int,int>}
     */
    private function series(int $developerId, string $range): array
    {
        if ($range === 'week') {
            $start = Carbon::now()->startOfDay()->subDays(6);

            $rows = Lead::where('developer_id', $developerId)
                ->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as bucket, COUNT(*) as c')
                ->groupBy('bucket')
                ->pluck('c', 'bucket');

            $labels = [];
            $values = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $start->copy()->addDays($i);
                $labels[] = $day->format('D');
                $values[] = (int) ($rows[$day->toDateString()] ?? 0);
            }

            return ['labels' => $labels, 'values' => $values];
        }

        $start = Carbon::now()->startOfWeek()->subWeeks(4);

        $rows = Lead::where('developer_id', $developerId)
            ->where('created_at', '>=', $start)
            ->selectRaw('YEARWEEK(created_at, 3) as bucket, COUNT(*) as c')
            ->groupBy('bucket')
            ->pluck('c', 'bucket');

        $labels = [];
        $values = [];

        for ($i = 0; $i < 5; $i++) {
            $week = $start->copy()->addWeeks($i);
            $labels[] = 'Wk ' . ($i + 1);
            $values[] = (int) ($rows[(int) $week->format('oW')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
