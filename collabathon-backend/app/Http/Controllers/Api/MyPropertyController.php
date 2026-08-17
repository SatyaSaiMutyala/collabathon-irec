<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Support\PropertyDeveloperResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A developer's own inventory.
 *
 * Separate from `DeveloperController@properties`, which is the public, broker-facing
 * view of a developer and is gated to accepted+active. This one deliberately shows
 * every project assigned to the caller regardless of status — a developer who could
 * not see their pending assignments would have nothing to accept.
 */
class MyPropertyController extends Controller
{
    use HandlesListQueries;

    private const SORTABLE = [
        'created_at' => 'created_at',
        'name' => 'name',
        'price' => 'price_min',
        'interests' => 'interests_count',
    ];

    /** Enough to fill a filter drawer; a developer with more cities than this needs search, not chips. */
    private const MAX_OPTIONS = 40;

    /**
     * GET /api/v1/my/properties
     * ?developer_status=pending|accepted|declined &status &search &page &per_page &sort
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $developerId = $this->developerId($request);

        $query = Property::query()
            ->where('developer_id', $developerId)
            ->with('developer')
            ->search($request->query('search'))
            ->filter($this->filters($request, ['developer_status', 'status', 'type', 'city', 'project_status']));

        $query = $this->applySort($query, $request, self::SORTABLE);

        // Always this developer's own listings — every row's contact channels
        // (their own) are always visible to them. See PropertyResource::withContact().
        $resource = PropertyResource::collection($this->paginate($query, $request));
        $resource->collection->each(fn (PropertyResource $item) => $item->withContact(true));

        return $resource;
    }

    /** GET /api/v1/my/properties/{property} — full detail, any status. */
    public function show(Request $request, Property $property): JsonResponse
    {
        abort_unless($property->developer_id === $this->developerId($request), 403);

        $property->load(['developer', 'detail', 'unitTypes', 'media']);

        return response()->json(['data' => (new PropertyResource($property))->withContact(true)]);
    }

    /**
     * PATCH /api/v1/my/properties/{property}/response
     *
     * The developer accepts or declines the assignment. Accepting is what makes the
     * project visible to brokers (together with the admin's `listing_status`).
     */
    public function respond(Request $request, Property $property): JsonResponse
    {
        abort_unless($property->developer_id === $this->developerId($request), 403);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:status,declined'],
        ]);

        PropertyDeveloperResponse::apply($property, $data['status'], $data['reason'] ?? null);

        $property->load(['developer', 'detail', 'unitTypes', 'media']);

        return response()->json([
            'message' => $data['status'] === Property::DEV_ACCEPTED
                ? 'Project accepted — it is now visible to channel partners.'
                : 'Project declined. The admin has been notified.',
            'data' => (new PropertyResource($property))->withContact(true),
        ]);
    }

    /** Counts for the developer's inventory tabs. */
    public function summary(Request $request): JsonResponse
    {
        $developerId = $this->developerId($request);

        $counts = Property::where('developer_id', $developerId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN developer_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN developer_status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            ->selectRaw("SUM(CASE WHEN developer_status = 'declined' THEN 1 ELSE 0 END) as declined")
            ->selectRaw("SUM(CASE WHEN developer_status = 'accepted' AND listing_status = 'active' THEN 1 ELSE 0 END) as live")
            ->first();

        return response()->json([
            'data' => [
                'total' => (int) $counts->total,
                'pending' => (int) $counts->pending,
                'accepted' => (int) $counts->accepted,
                'declined' => (int) $counts->declined,
                'live' => (int) $counts->live,
            ],
        ]);
    }

    /**
     * GET /api/v1/my/properties/filters
     *
     * Only the values this developer's own inventory actually contains. Offering a chip
     * that returns nothing is worse than offering no chip, and the full city list would
     * be mostly dead options for a developer building in one metro.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $developerId = $this->developerId($request);

        $distinct = fn (string $column) => Property::query()
            ->where('developer_id', $developerId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(self::MAX_OPTIONS)
            ->pluck($column);

        return response()->json([
            'data' => [
                'cities' => $distinct('city'),
                'types' => $distinct('project_type'),
                'stages' => $distinct('project_status'),
            ],
        ]);
    }

    private function developerId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user->isDeveloper(), 403);

        $id = $user->developer?->id;
        abort_if($id === null, 403, 'No developer profile is linked to this account.');

        return $id;
    }
}
