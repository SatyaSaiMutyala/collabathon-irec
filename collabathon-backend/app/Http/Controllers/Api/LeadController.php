<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Mail\LeadInterestMail;
use App\Models\Lead;
use App\Models\Property;
use App\Services\PushNotifier;
use App\Support\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lead lifecycle. Scoping is by role, always derived from the token — never from a
 * client-supplied id — so a broker cannot read another broker's leads and a developer
 * cannot read another developer's.
 */
class LeadController extends Controller
{
    use HandlesListQueries;

    private const SORTABLE = [
        'created_at' => 'created_at',
        'status' => 'status',
        'interested_at' => 'interested_at',
    ];

    /**
     * What LeadResource needs off the broker's profile, listed rather than selected with
     * `*` so the identity and banking columns (PAN, Aadhaar, cheque details and every
     * document path) are never even loaded into memory on a developer-facing request.
     * GST is in the list on purpose — it is a public business registration, not an ID.
     */
    private const BROKER_PROFILE_COLUMNS = 'broker.brokerProfile:id,user_id,company_name,is_company,'
        . 'rera_number,gst_number,years_of_experience,team_size,city,state,'
        . 'segments,zones,operates_multiple_states,project_contributions,submitted_at,photo_path,'
        . 'alternate_mobile,company_website,instagram,facebook,youtube,twitter,linkedin,'
        . 'office_address,residence_address';

    /** GET /api/leads — the caller's own leads (broker) or inbox (developer). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Lead::query()
            // Price is selected here, not just shaped by the resource: PropertyResource
            // emits the `price` block unconditionally, so leaving the columns out of the
            // select produced a well-formed object of nulls that looked like a listing
            // with no price rather than a column that was never fetched.
            ->with(['property:id,name,slug,city,state,locality,cover_image_path,project_status,'
                . 'project_type,price_min,price_max,currency,developer_id'])
            ->search($request->query('search'))
            ->filter($this->filters($request, ['status', 'property_id', 'from', 'to']));

        if ($user->isBroker()) {
            // The broker's own side of the relationship. They get the whole developer,
            // not just the company name: on an accepted lead this list *is* the partner
            // directory, and a partner you cannot reach is not much of one. Nothing here
            // is privileged — DeveloperResource is what /developers/{id} already serves
            // to any signed-in broker.
            $query->where('broker_id', $user->id)
                ->with(['developer' => fn ($q) => $q->select(
                    'id', 'user_id', 'company_name', 'contact_person', 'mobile', 'email',
                    'city', 'state', 'rera_number', 'logo_path', 'about',
                    'cp_payout_percent', 'verified', 'status', 'created_at',
                )->withCount('properties')]);
        } elseif ($user->isDeveloper()) {
            $query->with('developer:id,company_name');
            $developerId = $user->developer?->id;
            abort_if($developerId === null, 403, 'No developer profile linked to this account.');

            $query->where('developer_id', $developerId)
                ->with(['broker:id,name,mobile,email,avatar_path,created_at', self::BROKER_PROFILE_COLUMNS]);
        } else {
            abort(403);
        }

        $query = $this->applySort($query, $request, self::SORTABLE);

        return LeadResource::collection($this->paginate($query, $request));
    }

    /**
     * POST /api/properties/{property}/view — idempotent; records the broker's first view.
     * Never unlocks contact.
     */
    public function view(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isBroker(), 403);

        $lead = DB::transaction(function () use ($user, $property) {
            $lead = Lead::firstOrNew([
                'property_id' => $property->id,
                'broker_id' => $user->id,
            ]);

            if (! $lead->exists) {
                $lead->fill([
                    'developer_id' => $property->developer_id,
                    'status' => Lead::STATUS_VIEWED,
                    'viewed_at' => now(),
                ])->save();

                // Counter increment, not a COUNT(*) at read time.
                $property->increment('views_count');
            }

            return $lead;
        });

        return response()->json(['data' => new LeadResource($lead)]);
    }

    /**
     * POST /api/properties/{property}/interest — files the request with the developer.
     * This is the "interested" stage, not the contact reveal: the developer still sees
     * starred details until they accept. `contact_unlocked` is set server-side and the
     * client cannot pass it.
     */
    public function interest(Request $request, Property $property, PushNotifier $push): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isBroker(), 403);

        $lead = DB::transaction(function () use ($user, $property) {
            $lead = Lead::firstOrNew([
                'property_id' => $property->id,
                'broker_id' => $user->id,
            ]);

            $wasInterested = $lead->exists && $lead->contact_unlocked;

            $lead->fill([
                'developer_id' => $property->developer_id,
                'status' => Lead::STATUS_INTERESTED,
                'contact_unlocked' => true,
                'viewed_at' => $lead->viewed_at ?? now(),
                'interested_at' => $lead->interested_at ?? now(),
            ])->save();

            if (! $wasInterested) {
                $property->increment('interests_count');
            }

            return $lead;
        });

        // Only on the transition into "interested" — re-tapping an existing request
        // must not notify the developer again.
        if ($lead->wasChanged('status') || $lead->wasRecentlyCreated) {
            $push->requestReceived($lead);
            $this->emailDeveloperOfInterest($lead);
        }

        return response()->json([
            'message' => 'Request sent. The developer sees your contact details once they accept.',
            'data' => new LeadResource($lead),
        ]);
    }

    /**
     * Emails the developer alongside the push notification above, for a developer who
     * isn't watching the app right then. Every failure path is swallowed on purpose —
     * the broker's request is already recorded and must not fail on a mail hiccup.
     */
    private function emailDeveloperOfInterest(Lead $lead): void
    {
        if (! MailSettings::apply()) {
            return;
        }

        $lead->loadMissing(['developer.user:id,email', 'broker:id,name', 'property:id,name']);
        $developerEmail = $lead->developer?->email ?: $lead->developer?->user?->email;

        if (! $developerEmail) {
            return;
        }

        try {
            Mail::to($developerEmail)->send(new LeadInterestMail($lead));
        } catch (\Throwable $e) {
            Log::error('Lead interest email failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** PATCH /api/leads/{lead} — developer accepts or declines an interested broker. */
    public function respond(Request $request, Lead $lead, PushNotifier $push): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isDeveloper(), 403);
        abort_unless($lead->developer_id === $user->developer?->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
            'developer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if(
            $lead->status === Lead::STATUS_VIEWED,
            422,
            'This broker has only viewed the listing — there is nothing to respond to yet.'
        );

        $lead->fill([
            'status' => $data['status'],
            'developer_note' => $data['developer_note'] ?? $lead->developer_note,
            'responded_at' => now(),
        ])->save();

        $data['status'] === Lead::STATUS_ACCEPTED
            ? $push->requestAccepted($lead)
            : $push->requestDeclined($lead);

        // Reloaded so the response already carries the post-decision view: on accept the
        // contact comes back real, on decline it stays starred.
        $lead->load(['property:id,name', 'broker:id,name,mobile,email,avatar_path,created_at', self::BROKER_PROFILE_COLUMNS]);

        return response()->json(['data' => new LeadResource($lead)]);
    }
}
