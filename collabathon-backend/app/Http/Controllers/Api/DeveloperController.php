<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeveloperResource;
use App\Http\Resources\PropertyResource;
use App\Models\Developer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeveloperController extends Controller
{
    use HandlesListQueries;

    private const SORTABLE = [
        'created_at' => 'created_at',
        'name' => 'company_name',
        'city' => 'city',
    ];

    /** GET /api/developers — browsable by brokers. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Developer::query()
            ->where('status', 'active')
            // withCount, not a loaded relation — one aggregate per page, no N+1.
            ->withCount(['properties' => fn ($q) => $q->where('listing_status', 'active')])
            ->when($request->query('search'), fn ($q, $term) => $q->where('company_name', 'like', $term . '%'))
            ->when($request->query('city'), fn ($q, $city) => $q->where('city', $city));

        $query = $this->applySort($query, $request, self::SORTABLE);

        return DeveloperResource::collection($this->paginate($query, $request));
    }

    /** GET /api/developers/{developer} */
    public function show(Developer $developer): JsonResponse
    {
        abort_unless($developer->status === 'active', 404);

        $developer->loadCount(['properties' => fn ($q) => $q->where('listing_status', 'active')]);

        return response()->json(['data' => new DeveloperResource($developer)]);
    }

    /**
     * GET /api/developers/{developer}/properties — paginated in its own right, so a
     * developer with hundreds of listings never returns them all in the parent payload.
     */
    public function properties(Request $request, Developer $developer): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = $developer->properties()
            ->active()
            ->with('developer')
            ->search($request->query('search'));

        if ($user && $user->isBroker()) {
            $query->with(['myLead' => fn ($q) => $q->where('broker_id', $user->id)]);
        }

        $query = $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'price' => 'price_min',
            'name' => 'name',
        ]);

        return PropertyResource::collection($this->paginate($query, $request));
    }
}
