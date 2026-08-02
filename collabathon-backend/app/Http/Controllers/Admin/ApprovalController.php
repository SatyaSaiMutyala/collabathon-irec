<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Mail\BrokerApprovedMail;
use App\Models\ApprovalDecision;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            // Newest registration first. Oldest-first treated this as a FIFO queue, which
            // buried a broker who signed up this morning behind every stale one — and the
            // page they land on is the one an admin actually watches.
            ->latest();

        return view('admin.approvals', [
            'pending' => $pending->paginate($this->perPage($request))->withQueryString(),
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
     * Decisions already made — its own page rather than a tab on the queue.
     *
     * The two lists answer different questions: the queue is work outstanding, this is an
     * audit trail that only grows. Sharing a page meant two paginators fighting over one
     * query string, and a filter meant for one list riding along on the other.
     */
    public function decided(Request $request): View
    {
        $decisions = ApprovalDecision::query()
            ->with(['broker:id,name,email,status,mobile', 'broker.brokerProfile:id,user_id,company_name,photo_path,city', 'decider:id,name'])
            ->when($request->query('search'), function ($q, $term) {
                $q->whereHas('broker', fn ($b) => $b
                    ->where('name', 'like', $term . '%')
                    ->orWhere('email', 'like', $term . '%')
                    ->orWhereHas('brokerProfile', fn ($p) => $p->where('company_name', 'like', $term . '%')));
            })
            ->when($request->query('outcome'), fn ($q, $v) => $q->where('decision', $v))
            ->when($request->query('decided_by'), fn ($q, $v) => $q->where('decided_by', $v))
            ->latest();

        return view('admin.approvals.decided', [
            'decisions' => $decisions->paginate($this->perPage($request))->withQueryString(),
            // Only admins who have actually decided something — a reviewer filter listing
            // people with no decisions offers choices that return nothing.
            'reviewers' => User::query()
                ->whereIn('id', ApprovalDecision::query()->select('decided_by')->whereNotNull('decided_by'))
                ->orderBy('name')
                ->pluck('name', 'id'),
            'stats' => [
                'approved' => ApprovalDecision::where('decision', 'approved')->count(),
                'rejected' => ApprovalDecision::where('decision', 'rejected')->count(),
                'last_30d' => ApprovalDecision::where('created_at', '>=', now()->subDays(30))->count(),
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
    public function approve(Request $request, User $user, PushNotifier $push): RedirectResponse
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

        // After the commit, never inside it: a mail server that hangs would otherwise hold
        // a write transaction open, and a mail failure would roll back an approval that
        // was already correct.
        $emailed = $this->notifyApproved($user);

        $message = $wasRejected
            ? "{$user->name} re-approved — they can sign in again."
            : "{$user->name} approved — they can now sign in.";

        $push->brokerApproved($user);

        return back()->with($emailed ? 'success' : 'warning', $emailed
            ? $message . " Sign-in details emailed to {$user->email}."
            : $message . ' No email was sent — check the Mailjet settings.');
    }

    /**
     * Issue a new password for a broker's login.
     *
     * Same shape as the developer and team equivalents: a blank field means "generate
     * one", and the plaintext is flashed once to x-credentials-dialog so the admin can
     * hand it over. It is never stored anywhere in the clear.
     *
     * Existing Sanctum tokens are revoked with it. A password reset that leaves the old
     * mobile session signed in has not actually locked anyone out — which is the usual
     * reason for doing this in the first place.
     */
    public function resetPassword(Request $request, User $user, PushNotifier $push): RedirectResponse
    {
        $this->authorize('edit-module', 'approvals');
        $this->guardIsBroker($user);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ], [
            'password.min' => 'The password must be at least 8 characters, or leave it blank to generate one.',
            'password.max' => 'The password cannot be longer than 72 characters.',
        ]);

        // `nullable` means validate() drops the key when it was not submitted at all.
        $password = ($data['password'] ?? '') ?: Str::password(14, symbols: false);

        DB::transaction(function () use ($user, $password) {
            $user->update(['password' => $password]);
            $user->tokens()->delete();
        });

        // The broker is told directly as well as the admin — an issued password that only
        // exists in a dialog on someone else's screen has to be relayed by hand.
        $emailed = $this->notifyApproved($user, $password);

        // Best-effort: their tokens were just revoked, so this only lands if the device
        // still holds a live FCM registration — which is exactly the case worth warning.
        $push->passwordReset($user);

        return back()
            ->with('success', "New password issued for {$user->name}."
                . ($emailed ? " Emailed to {$user->email}." : ' Email could not be sent — share it manually.'))
            ->with('credentials', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ]);
    }

    public function reject(Request $request, User $user, PushNotifier $push): RedirectResponse
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

        $wasActive ? $push->brokerAccessRevoked($user) : $push->brokerRejected($user);

        return back()->with('warning', $wasActive
            ? "{$user->name}'s access revoked — their sessions were signed out."
            : "{$user->name} rejected.");
    }

    /**
     * Emails the broker their sign-in details. Returns whether it actually went out.
     *
     * Every failure path is swallowed on purpose. Approving a broker is the action the
     * admin asked for and it has already been committed; an unreachable SMTP host, a
     * rejected key or a bounced address must not surface as a 500 on a page whose work
     * succeeded. The outcome is reported in the flash message instead, so nobody is left
     * assuming an email went out when it did not.
     */
    private function notifyApproved(User $user, ?string $password = null): bool
    {
        if (! MailSettings::apply()) {
            return false;
        }

        try {
            $user->loadMissing('brokerProfile');
            Mail::to($user->email)->send(new BrokerApprovedMail($user, $password));

            return true;
        } catch (\Throwable $e) {
            Log::error('Broker approval email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
