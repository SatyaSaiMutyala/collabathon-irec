<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Mail\BrokerApprovedMail;
use App\Models\ApprovalDecision;
use App\Models\User;
use App\Services\AadhaarXmlReader;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        $data = [
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
        ];

        // The table (with its own pagination, search and city filter) refreshes itself
        // in place instead of a full reload — same `data-ajax-panel` mechanism as Team,
        // and the same reasoning: this fragment is the exact partial the full page
        // includes, just rendered without the surrounding layout.
        return $request->ajax()
            ? view('admin.approvals.partials.table', $data)
            : view('admin.approvals', $data);
    }

    /**
     * Registrations that never reached a real step-3 submit — signed up (or resumed a
     * session) and either hit Save Draft or simply closed the app partway through.
     *
     * Deliberately excluded from index()'s own query: STATUS_DRAFT is a distinct status
     * from STATUS_PENDING specifically so a half-finished registration never shows up
     * in the decision queue next to ones actually waiting on an admin — see the
     * docblock on User::STATUS_DRAFT. Before this page, that meant a draft was
     * invisible everywhere in the admin panel; this is the one place to see who has
     * started but not finished, and how far each one got.
     */
    public function drafts(Request $request): View
    {
        $this->authorize('view-module', 'approvals');

        $drafts = User::role(User::ROLE_BROKER)
            ->status(User::STATUS_DRAFT)
            ->with('brokerProfile')
            ->when($request->query('search'), function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', $term . '%')
                    ->orWhere('email', 'like', $term . '%')
                    ->orWhere('mobile', 'like', $term . '%'));
            })
            // registration_step is the high-water mark of the last step actually
            // reached — see AuthController::saveRegistrationStep's own note on why it
            // never regresses even after a Back. A row with no profile at all yet
            // (the very first instant after sign-up, before any save) reads as step 1.
            ->when($request->query('step'), fn ($q, $v) => $q
                ->whereHas('brokerProfile', fn ($p) => $p->where('registration_step', $v)))
            // Most recently active first — the point of this page is "who's mid-flow
            // right now and might be worth a nudge", not a FIFO of who signed up first.
            ->orderByDesc('updated_at');

        $data = [
            'drafts' => $drafts->paginate($this->perPage($request))->withQueryString(),
            'stats' => [
                'total' => User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT)->count(),
                'stalled' => User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT)
                    ->where('updated_at', '<=', now()->subDays(7))->count(),
                'today' => User::role(User::ROLE_BROKER)->status(User::STATUS_DRAFT)
                    ->where('updated_at', '>=', now()->startOfDay())->count(),
            ],
        ];

        return $request->ajax()
            ? view('admin.approvals.partials.drafts-table', $data)
            : view('admin.approvals.drafts', $data);
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
    public function show(Request $request, User $user): View
    {
        $this->authorize('view-module', 'approvals');

        abort_unless($user->isBroker(), 404);

        $user->load([
            'brokerProfile',
            'approvalDecisions' => fn ($q) => $q->with('decider:id,name')->latest(),
        ]);

        $data = ['broker' => $user, 'profile' => $user->brokerProfile];

        // Approve/Reject/Edit refresh this whole block in place instead of navigating
        // away — see the note above index() and the matching `#approval-detail`
        // mechanism in app.js. Same partial either way, so the two can never drift.
        return $request->ajax()
            ? view('admin.approvals.partials.detail', $data)
            : view('admin.approvals.show', $data);
    }

    /**
     * A formatted, human-readable rendering of the DigiLocker Aadhaar XML — the
     * "View" link for that one document points here instead of opening the raw
     * signed XML in the browser, which shows nothing an admin can actually read
     * (every browser just dumps the tag tree for an .xml file). Only ever
     * reached for that specific case: the Documents list only points here when
     * `aadhaar_path` ends in .xml — see the `$documents` array in
     * partials/detail.blade.php. A manually-attached photo/PDF Aadhaar still
     * opens directly, unchanged.
     */
    public function aadhaarPreview(User $user): View
    {
        $this->authorize('view-module', 'approvals');
        abort_unless($user->isBroker(), 404);

        $path = $user->brokerProfile?->aadhaar_path;
        abort_unless($path && \App\Support\FileStorage::exists($path), 404);

        $data = AadhaarXmlReader::read(\App\Support\FileStorage::get($path));
        abort_unless($data !== null, 404);

        return view('admin.approvals.aadhaar-preview', ['broker' => $user, ...$data]);
    }

    /**
     * Approve, or re-approve a broker who was rejected earlier.
     *
     * Deliberately not restricted to pending registrations. A decision made by mistake has
     * to be correctable, and `approval_decisions` is append-only, so reversing appends a
     * new row rather than editing the old one — the earlier rejection and its reason stay
     * on the record, which is exactly what that table exists for.
     */
    public function approve(Request $request, User $user, PushNotifier $push): RedirectResponse|JsonResponse
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

        $tone = $emailed ? 'success' : 'warning';
        $full = $emailed
            ? $message . " Sign-in details emailed to {$user->email}."
            : $message . ' No email was sent — check the Mailjet settings.';

        return $this->approvalResponse($request, $full, $tone);
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

    public function reject(Request $request, User $user, PushNotifier $push): RedirectResponse|JsonResponse
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

        $message = $wasActive
            ? "{$user->name}'s access revoked — their sessions were signed out."
            : "{$user->name} rejected.";

        return $this->approvalResponse($request, $message, 'warning');
    }

    /**
     * Same shape as SettingsController::settingsResponse(): a plain toast message over
     * JSON for the `#approval-detail` fetch path, a flash + redirect back otherwise —
     * both branches driven by the exact same decision that already ran above.
     */
    private function approvalResponse(Request $request, string $message, string $tone): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json(['message' => $message, 'tone' => $tone]);
        }

        return back()->with($tone, $message);
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
