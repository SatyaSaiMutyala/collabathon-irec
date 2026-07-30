<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
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
    public function update(Request $request, Lead $lead): RedirectResponse
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

        return back()->with('success', "Lead moved to {$status}.");
    }
}
