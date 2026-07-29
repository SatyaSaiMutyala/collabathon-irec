<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    private const SORTABLE = [
        'created_at' => 'created_at',
        'status' => 'status',
    ];

    public function index(Request $request): View
    {
        $query = Lead::query()
            ->with([
                'broker:id,name',
                'property:id,name',
                'developer:id,company_name',
            ])
            ->search($request->query('search'))
            ->filter($this->filters($request, ['status', 'developer_id', 'from', 'to']));

        $query = $this->applySort($query, $request, self::SORTABLE);

        $counts = Lead::selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN contact_unlocked = 1 THEN 1 ELSE 0 END) as interested')
            ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            ->selectRaw("SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined")
            ->first();

        return view('admin.leads', [
            'leads' => $this->paginate($query, $request),
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'funnel' => [
                ['stage' => 'Viewed', 'value' => (int) $counts->total],
                ['stage' => 'Interested', 'value' => (int) $counts->interested],
                ['stage' => 'Accepted', 'value' => (int) $counts->accepted],
            ],
            'counts' => $counts,
        ]);
    }
}
