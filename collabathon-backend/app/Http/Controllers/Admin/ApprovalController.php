<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\ApprovalDecision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The broker approval queue. Approving flips `users.status` to active — which is what
 * lets the broker obtain a Sanctum token — and records who decided, in one transaction.
 */
class ApprovalController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    public function index(Request $request): View
    {
        $pending = User::role(User::ROLE_BROKER)
            ->status(User::STATUS_PENDING)
            ->with('brokerProfile')
            ->when($request->query('search'), function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', $term . '%')
                    ->orWhere('email', 'like', $term . '%')
                    ->orWhereHas('brokerProfile', fn ($p) => $p
                        ->where('company_name', 'like', $term . '%')
                        ->orWhere('rera_number', 'like', $term . '%')));
            })
            ->when($request->query('city'), fn ($q, $v) => $q
                ->whereHas('brokerProfile', fn ($p) => $p->where('city', $v)))
            ->orderBy('created_at');

        // Decided list is its own paginator, on its own page param, so paging one
        // tab never resets the other.
        $decided = ApprovalDecision::query()
            ->with(['broker:id,name,email,status', 'broker.brokerProfile:id,user_id,company_name', 'decider:id,name'])
            ->when($request->query('outcome'), fn ($q, $v) => $q->where('decision', $v))
            ->latest();

        return view('admin.approvals', [
            'pending' => $pending->paginate($this->perPage($request), ['*'], 'page')->withQueryString(),
            'decided' => $decided->paginate($this->perPage($request), ['*'], 'decided_page')->withQueryString(),
            'cities' => User::role(User::ROLE_BROKER)
                ->join('broker_profiles', 'broker_profiles.user_id', '=', 'users.id')
                ->distinct()
                ->orderBy('broker_profiles.city')
                ->pluck('broker_profiles.city')
                ->filter()
                ->values(),
            'stats' => [
                'pending' => User::role(User::ROLE_BROKER)->status(User::STATUS_PENDING)->count(),
                'approved' => ApprovalDecision::where('decision', 'approved')
                    ->where('created_at', '>=', now()->subDays(30))->count(),
                'rejected' => ApprovalDecision::where('decision', 'rejected')
                    ->where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    /**
     * The whole registration behind a queue row — all ~34 profile fields plus every
     * uploaded document and the decision history.
     *
     * Open to any broker, not just pending ones: the Decided tab links here too, and an
     * approved broker's paperwork still needs to be auditable after the fact.
     */
    public function show(User $user): View
    {
        $this->authorize('view-module', 'approvals');

        abort_unless($user->isBroker(), 404);

        $user->load([
            'brokerProfile',
            'approvalDecisions' => fn ($q) => $q->with('decider:id,name')->latest(),
        ]);

        return view('admin.approvals.show', [
            'broker' => $user,
            'profile' => $user->brokerProfile,
        ]);
    }

    /**
     * Approve, or re-approve a broker who was rejected earlier.
     *
     * Deliberately not restricted to pending registrations. A decision made by mistake has
     * to be correctable, and `approval_decisions` is append-only, so reversing appends a
     * new row rather than editing the old one — the earlier rejection and its reason stay
     * on the record, which is exactly what that table exists for.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorize('edit-module', 'approvals');
        $this->guardIsBroker($user);

        $data = $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);

        $wasRejected = $user->status === User::STATUS_REJECTED;

        DB::transaction(function () use ($user, $request, $data) {
            $user->update(['status' => User::STATUS_ACTIVE]);

            ApprovalDecision::create([
                'user_id' => $user->id,
                'decided_by' => $request->user()->id,
                'decision' => 'approved',
                'internal_note' => $data['internal_note'] ?? null,
            ]);
        });

        return back()->with('success', $wasRejected
            ? "{$user->name} re-approved — they can sign in again."
            : "{$user->name} approved — they can now sign in.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->authorize('edit-module', 'approvals');
        $this->guardIsBroker($user);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasActive = $user->status === User::STATUS_ACTIVE;

        DB::transaction(function () use ($user, $request, $data) {
            $user->update(['status' => User::STATUS_REJECTED]);

            // Any token issued earlier is revoked, so a rejection takes effect immediately —
            // this is what makes revoking a previously approved broker actually bite.
            $user->tokens()->delete();

            ApprovalDecision::create([
                'user_id' => $user->id,
                'decided_by' => $request->user()->id,
                'decision' => 'rejected',
                'reason' => $data['reason'],
                'internal_note' => $data['internal_note'] ?? null,
            ]);
        });

        return back()->with('warning', $wasActive
            ? "{$user->name}'s access revoked — their sessions were signed out."
            : "{$user->name} rejected.");
    }

    /**
     * Only the "is this a broker" half of the old guard survives. Blocking an already
     * decided registration meant an accidental approval could never be undone, which is
     * the opposite of what an admin panel is for.
     */
    private function guardIsBroker(User $user): void
    {
        abort_unless($user->isBroker(), 404);
    }
}
