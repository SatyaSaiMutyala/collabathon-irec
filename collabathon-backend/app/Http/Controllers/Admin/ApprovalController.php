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

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->guardIsPendingBroker($user);

        $data = $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($user, $request, $data) {
            $user->update(['status' => User::STATUS_ACTIVE]);

            ApprovalDecision::create([
                'user_id' => $user->id,
                'decided_by' => $request->user()->id,
                'decision' => 'approved',
                'internal_note' => $data['internal_note'] ?? null,
            ]);
        });

        return back()->with('status', "{$user->name} approved — they can now sign in.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->guardIsPendingBroker($user);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($user, $request, $data) {
            $user->update(['status' => User::STATUS_REJECTED]);

            // Any token issued earlier is revoked, so a rejection takes effect immediately.
            $user->tokens()->delete();

            ApprovalDecision::create([
                'user_id' => $user->id,
                'decided_by' => $request->user()->id,
                'decision' => 'rejected',
                'reason' => $data['reason'],
                'internal_note' => $data['internal_note'] ?? null,
            ]);
        });

        return back()->with('status', "{$user->name} rejected.");
    }

    private function guardIsPendingBroker(User $user): void
    {
        abort_unless($user->isBroker(), 404);
        abort_unless($user->status === User::STATUS_PENDING, 422, 'This registration has already been decided.');
    }
}
