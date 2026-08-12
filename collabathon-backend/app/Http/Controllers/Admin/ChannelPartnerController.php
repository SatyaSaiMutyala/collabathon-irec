<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\BrokerProfile;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Channel Partners — the brokers who are already through approval and working.
 *
 * Deliberately separate from the approvals queue rather than a fourth tab on it. That
 * screen exists to empty a backlog and is read newest-first; this one is a directory of
 * an active roster that only grows, and is searched rather than worked through.
 */
class ChannelPartnerController extends Controller
{
    use HandlesListQueries;

    /** Public filter name => real column, for the sort whitelist. */
    private const SORTABLE = [
        'created_at' => 'users.created_at',
        'name' => 'users.name',
        'city' => 'broker_profiles.city',
    ];

    /** Enough to fill a select; past this the search box is the right tool. */
    private const MAX_OPTIONS = 60;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    public function index(Request $request): View
    {
        // Active and inactive both belong on this roster — inactive is a broker who
        // deleted their own account (see AuthController::deleteAccount), not one who
        // was never approved, so it stays a visible row here rather than dropping out
        // the way a pending/rejected one correctly does. Stats and filter option lists
        // further down stay scoped to active only — an inactive broker shouldn't
        // inflate "Active partners" or offer a filter that returns a dead account.
        $partners = User::role(User::ROLE_BROKER)
            ->whereIn('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE])
            ->select('users.*')
            // Joined rather than filtered through whereHas so city and name can both be
            // sorted on, and so a broker with no profile row never disappears silently.
            ->leftJoin('broker_profiles', 'broker_profiles.user_id', '=', 'users.id')
            ->with('brokerProfile')
            // Counted here rather than per row: the roster is the whole point of the page,
            // and one aggregate beats a query per partner.
            ->withCount(['leads as accepted_leads_count' => fn ($q) => $q
                ->where('status', Lead::STATUS_ACCEPTED)])
            ->when($request->query('search'), function ($q, $term) {
                $q->where(fn ($w) => $w
                    ->where('users.name', 'like', $term . '%')
                    ->orWhere('users.email', 'like', $term . '%')
                    ->orWhere('users.mobile', 'like', $term . '%')
                    ->orWhere('broker_profiles.company_name', 'like', $term . '%')
                    ->orWhere('broker_profiles.rera_number', 'like', $term . '%'));
            })
            ->when($request->query('city'), fn ($q, $v) => $q->where('broker_profiles.city', $v))
            ->when($request->query('state'), fn ($q, $v) => $q->where('broker_profiles.state', $v))
            // segments/zones are JSON arrays, so this is a containment test, not equality.
            ->when($request->query('segment'), fn ($q, $v) => $q
                ->whereJsonContains('broker_profiles.segments', $v))
            ->when($request->query('type'), fn ($q, $v) => $q
                ->where('broker_profiles.is_company', $v === 'company'));

        $partners = $this->applySort($partners, $request, self::SORTABLE);

        return view('admin.cp', [
            'partners' => $partners->paginate($this->perPage($request))->withQueryString(),
            'cities' => $this->distinctProfileValues('city'),
            'states' => $this->distinctProfileValues('state'),
            'segments' => $this->distinctSegments(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Only values that belong to an *active* broker: offering a city whose only broker is
     * still pending would return an empty table from a filter the page itself suggested.
     */
    private function distinctProfileValues(string $column)
    {
        return BrokerProfile::query()
            ->join('users', 'users.id', '=', 'broker_profiles.user_id')
            ->where('users.role', User::ROLE_BROKER)
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull("broker_profiles.{$column}")
            ->where("broker_profiles.{$column}", '!=', '')
            ->distinct()
            ->orderBy("broker_profiles.{$column}")
            ->limit(self::MAX_OPTIONS)
            ->pluck("broker_profiles.{$column}");
    }

    /** Flattened out of the JSON column, so the select lists segments and not arrays. */
    private function distinctSegments()
    {
        return BrokerProfile::query()
            ->join('users', 'users.id', '=', 'broker_profiles.user_id')
            ->where('users.role', User::ROLE_BROKER)
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull('broker_profiles.segments')
            ->limit(self::MAX_OPTIONS * 10)
            ->pluck('broker_profiles.segments')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->take(self::MAX_OPTIONS)
            ->values();
    }

    private function stats(): array
    {
        $active = User::role(User::ROLE_BROKER)->status(User::STATUS_ACTIVE);

        return [
            'total' => (clone $active)->count(),
            'companies' => (clone $active)
                ->whereHas('brokerProfile', fn ($q) => $q->where('is_company', true))->count(),
            'joined_30d' => (clone $active)->where('users.created_at', '>=', now()->subDays(30))->count(),
            'cities' => $this->distinctProfileValues('city')->count(),
        ];
    }
}
