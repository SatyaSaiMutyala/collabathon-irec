<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    use HandlesListQueries;

    /** Public sort names mapped to indexed columns. */
    private const SORTABLE = [
        'created_at' => 'created_at',
        'price' => 'price_min',
        'name' => 'name',
        'possession' => 'possession_date',
        'interests' => 'interests_count',
        'views' => 'views_count',
    ];

    /**
     * GET /api/properties
     * ?page&per_page&search&sort&direction&developer_id&type&city&zone&price_min&price_max&project_status
     *
     * Brokers only ever see active listings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Property::query()
            ->with('developer')                       // eager — avoids N+1 across the page
            ->search($request->query('search'))
            ->filter($this->filters($request, [
                'developer_id', 'type', 'city', 'zone', 'price_min', 'price_max', 'project_status',
            ]))
            ->brokerVisible();

        // Attach only this broker's own lead, so the card can render "Interested" state.
        if ($user && $user->isBroker()) {
            $query->with(['myLead' => fn ($q) => $q->where('broker_id', $user->id)]);
        }

        $query = $this->applySort($query, $request, self::SORTABLE);

        return PropertyResource::collection($this->paginate($query, $request));
    }

    /** GET /api/properties/{property} — records a view for brokers. */
    public function show(Request $request, Property $property): JsonResponse
    {
        // 404, not 403: a project the developer has not accepted must not be
        // discoverable by id either.
        abort_unless($property->isVisibleToBrokers(), 404);

        $property->load(['developer', 'detail', 'unitTypes', 'media']);

        $user = $request->user();
        if ($user && $user->isBroker()) {
            $property->load(['myLead' => fn ($q) => $q->where('broker_id', $user->id)]);
        }

        return response()->json(['data' => new PropertyResource($property)]);
    }
}
