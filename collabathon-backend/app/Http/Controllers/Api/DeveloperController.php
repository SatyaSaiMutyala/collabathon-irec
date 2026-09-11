<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeveloperResource;
use App\Http\Resources\PropertyResource;
use App\Models\Developer;
use App\Models\Lead;
use App\Support\DirectoryLocation;
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

    /** GET /api/developers - browsable by brokers. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $here = DirectoryLocation::fromRequest($request);

        $query = Developer::query()
            ->where('status', 'active')
            // withCount, not a loaded relation - one aggregate per page, no N+1.
            ->withCount(['properties' => fn ($q) => $q->brokerVisible()])
            ->when($request->query('search'), fn ($q, $term) => $q->where('company_name', 'like', $term . '%'))
            /*
             * The partner's own state, and only when their city is one the admin has on
             * file. A cap on how far the list reaches, not a filter on the exact spot
             * they are standing in.
             *
             * Filtering on the city itself is what used to leave this screen empty: every
             * developer sits in one of a couple of city names, while a GPS fix
             * reverse-geocodes to whatever OpenStreetMap calls that point, so a partner in
             * Secunderabad or Kukatpally matched nothing and saw nothing. Crossing into
             * another state is a deliberate move instead - the partner changes the
             * location themselves. See DirectoryLocation.
             */
            ->when($here->state, fn ($q, $state) => $q->where('state', $state));

        /*
         * Pinned companies first, then everything else outward from the partner.
         *
         * Order of the ORDER BY terms is the feature. pinnedFirst() goes on first, so an
         * admin's rank always wins: a company pinned to 1 in its city opens the list even
         * when a dozen others are physically nearer. Distance is added next, which makes
         * it the tie-break inside a rank and the real order for everything unpinned -
         * nearest, then further out, which is what scrolling walks through. applySort()
         * lands last and only decides among rows the first two could not separate (no
         * point sent, or no coordinates on file), keeping pages stable.
         */
        $query = $query->pinnedFirst();

        if ($here->hasPoint()) {
            $query = $query->nearestTo($here->latitude, $here->longitude, $here->longitudeScale());
        }

        $resource = DeveloperResource::collection(
            $this->paginate($this->applySort($query, $request, self::SORTABLE), $request)
        );

        // One location for the whole page - every row measures from the same partner.
        $resource->collection->each(fn (DeveloperResource $item) => $item->withDistanceFrom($here));

        return $resource;
    }

    /**
     * GET /api/developers/{developer} — the developer's contact channels (public and
     * key contact alike) stay masked to a browsing broker until *this* developer has
     * accepted one of their leads, same rule as `PartnerResource` in the other
     * direction. A developer or admin viewing this always sees it masked too — the
     * `broker_id` scope below only ever matches a broker's own accepted lead.
     */
    public function show(Request $request, Developer $developer): JsonResponse
    {
        abort_unless($developer->status === 'active', 404);

        $developer->loadCount(['properties' => fn ($q) => $q->brokerVisible()]);

        $visible = Lead::where('broker_id', $request->user()->id)
            ->where('developer_id', $developer->id)
            ->where('status', Lead::STATUS_ACCEPTED)
            ->exists();

        return response()->json(['data' => (new DeveloperResource($developer))->withContact($visible)]);
    }

    /**
     * GET /api/developers/{developer}/properties — paginated in its own right, so a
     * developer with hundreds of listings never returns them all in the parent payload.
     */
    public function properties(Request $request, Developer $developer): AnonymousResourceCollection
    {
        $user = $request->user();

        // Chaining scopes on a HasMany relation keeps re-wrapping the result back into
        // the relation itself (Eloquent's __call proxies scope calls, and a scope
        // returning the same builder instance makes Relation::__call hand back `$this`
        // rather than the Builder) — getQuery() unwraps it to the real Eloquent Builder
        // that applySort()'s type hint requires.
        $query = $developer->properties()
            ->brokerVisible()
            ->with('developer')
            ->search($request->query('search'))
            ->getQuery();

        if ($user && $user->isBroker()) {
            $query->with(['myLead' => fn ($q) => $q->where('broker_id', $user->id)]);
        }

        $query = $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'price' => 'price_min',
            'name' => 'name',
        ]);

        // One determination for the whole page, not per row — every property here
        // belongs to the same `$developer`, so a single accepted-lead check answers
        // for all of them. See PropertyResource::withContact().
        $visible = $user && $user->isBroker()
            ? Lead::where('broker_id', $user->id)
                ->where('developer_id', $developer->id)
                ->where('status', Lead::STATUS_ACCEPTED)
                ->exists()
            : false;

        $resource = PropertyResource::collection($this->paginate($query, $request));
        $resource->collection->each(fn (PropertyResource $item) => $item->withContact($visible));

        return $resource;
    }
}
