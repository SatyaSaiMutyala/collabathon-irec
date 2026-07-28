<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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

    /** GET /api/leads — the caller's own leads (broker) or inbox (developer). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Lead::query()
            ->with(['property:id,name,slug,city,cover_image_path,developer_id', 'developer:id,company_name'])
            ->search($request->query('search'))
            ->filter($this->filters($request, ['status', 'property_id', 'from', 'to']));

        if ($user->isBroker()) {
            $query->where('broker_id', $user->id);
        } elseif ($user->isDeveloper()) {
            $developerId = $user->developer?->id;
            abort_if($developerId === null, 403, 'No developer profile linked to this account.');

            $query->where('developer_id', $developerId)
                ->with(['broker:id,name,mobile,email', 'broker.brokerProfile:id,user_id,company_name,rera_number']);
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
     * POST /api/properties/{property}/interest — the moment contact details unlock.
     * `contact_unlocked` is set server-side; the client cannot pass it.
     */
    public function interest(Request $request, Property $property): JsonResponse
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

        return response()->json([
            'message' => 'Interest recorded. The developer can now see your contact details.',
            'data' => new LeadResource($lead),
        ]);
    }

    /** PATCH /api/leads/{lead} — developer accepts or declines an interested broker. */
    public function respond(Request $request, Lead $lead): JsonResponse
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

        $lead->load(['property:id,name', 'broker:id,name,mobile,email']);

        return response()->json(['data' => new LeadResource($lead)]);
    }
}
