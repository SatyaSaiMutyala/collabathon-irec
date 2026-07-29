<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\Property;
use App\Models\PropertyDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyController extends Controller
{
    use HandlesListQueries;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    private const SORTABLE = [
        'created_at' => 'created_at',
        'name' => 'name',
        'price' => 'price_min',
        'views' => 'views_count',
        'interests' => 'interests_count',
    ];

    public function index(Request $request): View
    {
        $query = Property::query()
            ->with('developer:id,company_name')
            ->search($request->query('search'))
            ->filter($this->filters($request, ['developer_id', 'type', 'city', 'status']));

        $query = $this->applySort($query, $request, self::SORTABLE);

        return view('admin.properties', [
            'properties' => $this->paginate($query, $request),
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'cities' => Property::query()->distinct()->orderBy('city')->pluck('city')->filter()->values(),
            'totals' => [
                'all' => Property::count(),
                'active' => Property::where('listing_status', 'active')->count(),
                'views' => (int) Property::sum('views_count'),
                'interests' => (int) Property::sum('interests_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $data = $request->validate([
            // A property always belongs to exactly one developer.
            'developer_id' => ['required', 'exists:developers,id'],
            'name' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'in:Residential,Commercial,Mixed-use,Plotted Development,Villa,Row House'],
            'city' => ['required', 'string', 'max:96'],
            'locality' => ['nullable', 'string', 'max:128'],
            'price_min' => ['required', 'integer', 'min:0'],
            'price_max' => ['required', 'integer', 'gte:price_min'],
            'cp_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'listing_status' => ['required', 'in:draft,active,archived'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $property = Property::create($data + [
                'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(5)),
            ]);

            PropertyDetail::create([
                'property_id' => $property->id,
                'cp_commission_percent' => $data['cp_commission_percent'] ?? null,
            ]);
        });

        return back()->with('status', 'Property added.');
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $data = $request->validate([
            'listing_status' => ['required', 'in:draft,active,archived'],
        ]);

        $property->update($data);

        return back()->with('status', 'Listing updated.');
    }
}
