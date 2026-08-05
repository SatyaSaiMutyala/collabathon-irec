<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Every decision made on the platform, in one feed: a channel partner marking
 * interest, a developer accepting or declining that interest, a developer accepting
 * or declining an assigned project, an admin approving or rejecting a broker, and
 * the registration that started it.
 *
 * There is no `activity_log` table — each of those is already a fact recorded on the
 * row that owns it (`leads.interested_at`, `approval_decisions.decision`, …), so this
 * reads them rather than duplicating them into a new table that could drift out of
 * sync with the source of truth. A raw UNION ALL merges the five shapes into one
 * timeline; `baseQuery()` is the single definition both this controller and the
 * dashboard's "Total actions" tile count against, so the two numbers can never disagree.
 */
class ActivityController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 20;
    }

    /** One row's shape, keyed by the `type` column `baseQuery()` produces. */
    public const TYPES = [
        'lead_interested' => ['label' => 'Interest submitted', 'icon' => 'sparkles', 'tone' => 'info'],
        'lead_accepted' => ['label' => 'Request accepted', 'icon' => 'check', 'tone' => 'success'],
        'lead_declined' => ['label' => 'Request declined', 'icon' => 'x', 'tone' => 'danger'],
        'broker_approved' => ['label' => 'Broker approved', 'icon' => 'user-check', 'tone' => 'success'],
        'broker_rejected' => ['label' => 'Broker rejected', 'icon' => 'x', 'tone' => 'danger'],
        'property_accepted' => ['label' => 'Project accepted', 'icon' => 'building', 'tone' => 'success'],
        'property_declined' => ['label' => 'Project declined', 'icon' => 'building', 'tone' => 'danger'],
        'broker_registered' => ['label' => 'Broker registered', 'icon' => 'users', 'tone' => 'neutral'],
    ];

    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $search = trim((string) $request->query('search', ''));

        $query = self::baseQuery()
            ->when($type !== '', fn (QueryBuilder $q) => $q->where('type', $type))
            ->when($search !== '', fn (QueryBuilder $q) => $q->where(function (QueryBuilder $qq) use ($search) {
                $qq->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('subject_name', 'like', "%{$search}%");
            }))
            ->orderByDesc('occurred_at')
            // Ties (same-second events) still need a stable order across pages.
            ->orderByDesc('ref_id');

        return view('admin.activity', [
            'activities' => $this->paginateQuery($query, $request),
            'types' => self::TYPES,
            'trend' => self::weeklyTrend(),
        ]);
    }

    /**
     * 12 weeks of per-week totals across all five sources — `occurred_at` is the one
     * column every branch of the union already produces, so this groups on it directly
     * rather than needing a per-source query. Period totals, not a running total: "how
     * much happened each week" is the question this page's chart answers.
     *
     * @return array<int,array{label:string,count:int}>
     */
    public static function weeklyTrend(): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks(11);

        $grouped = self::baseQuery()
            ->where('occurred_at', '>=', $start)
            ->selectRaw('YEARWEEK(occurred_at, 3) as yw, COUNT(*) as c')
            ->groupBy('yw')
            ->pluck('c', 'yw');

        $points = [];

        for ($i = 0; $i < 12; $i++) {
            $week = $start->copy()->addWeeks($i);
            $points[] = [
                'label' => $week->format('j M'),
                'count' => (int) ($grouped[(int) $week->format('oW')] ?? 0),
            ];
        }

        return $points;
    }

    /**
     * The merged timeline as a query builder, filters not yet applied — the one place
     * the five sources are defined, so `index()` and the dashboard's count can never
     * see a different total.
     *
     * @return QueryBuilder  columns: type, source, ref_id, actor_name, subject_name, occurred_at
     */
    public static function baseQuery(): QueryBuilder
    {
        return DB::table(DB::raw('(' . self::unionSql() . ') as activities'));
    }

    private static function unionSql(): string
    {
        return <<<'SQL'
            (SELECT 'lead_interested' AS type, 'lead' AS source, leads.id AS ref_id,
                    brokers.name AS actor_name, properties.name AS subject_name,
                    leads.interested_at AS occurred_at
             FROM leads
             INNER JOIN users AS brokers ON brokers.id = leads.broker_id
             INNER JOIN properties ON properties.id = leads.property_id
             WHERE leads.interested_at IS NOT NULL)

            UNION ALL

            (SELECT CASE leads.status WHEN 'accepted' THEN 'lead_accepted' ELSE 'lead_declined' END AS type,
                    'lead' AS source, leads.id AS ref_id,
                    developers.company_name AS actor_name, properties.name AS subject_name,
                    leads.responded_at AS occurred_at
             FROM leads
             INNER JOIN developers ON developers.id = leads.developer_id
             INNER JOIN properties ON properties.id = leads.property_id
             WHERE leads.responded_at IS NOT NULL AND leads.status IN ('accepted', 'declined'))

            UNION ALL

            (SELECT CASE approval_decisions.decision WHEN 'approved' THEN 'broker_approved' ELSE 'broker_rejected' END AS type,
                    'approval' AS source, approval_decisions.user_id AS ref_id,
                    admins.name AS actor_name, brokers.name AS subject_name,
                    approval_decisions.created_at AS occurred_at
             FROM approval_decisions
             INNER JOIN users AS brokers ON brokers.id = approval_decisions.user_id
             LEFT JOIN users AS admins ON admins.id = approval_decisions.decided_by)

            UNION ALL

            (SELECT CASE properties.developer_status WHEN 'accepted' THEN 'property_accepted' ELSE 'property_declined' END AS type,
                    'property' AS source, properties.id AS ref_id,
                    developers.company_name AS actor_name, properties.name AS subject_name,
                    properties.developer_responded_at AS occurred_at
             FROM properties
             INNER JOIN developers ON developers.id = properties.developer_id
             WHERE properties.developer_responded_at IS NOT NULL AND properties.developer_status IN ('accepted', 'declined'))

            UNION ALL

            (SELECT 'broker_registered' AS type, 'approval' AS source, users.id AS ref_id,
                    users.name AS actor_name, NULL AS subject_name,
                    users.created_at AS occurred_at
             FROM users
             WHERE users.role = 'broker')
            SQL;
    }

    /**
     * `HandlesListQueries::paginate()` type-hints an Eloquent builder; this is a plain
     * query builder over a raw subquery, so pagination is one line reimplemented here
     * rather than loosening that trait's contract for every other caller.
     */
    private function paginateQuery(QueryBuilder $query, Request $request): LengthAwarePaginator
    {
        return $query->paginate($this->perPage($request))->withQueryString();
    }
}
