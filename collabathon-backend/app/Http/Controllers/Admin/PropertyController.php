<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\ExportsList;
use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Mail\ProjectAssignedMail;
use App\Models\Amenity;
use App\Models\Country;
use App\Models\Developer;
use App\Models\FormField;
use App\Models\MeasurementUnit;
use App\Models\ProjectType;
use App\Models\Property;
use App\Services\PropertyDeleter;
use App\Services\PushNotifier;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Models\UnitType;
use App\Support\CsvReader;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    use HandlesListQueries, ExportsList;

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

    /**
     * Single-file uploads that become a `property_media` row: request key => media kind.
     * `payment_schedule_file` is the attachment; `payment_schedule` on property_details is
     * the prose version — the client's sheet asks for both.
     */
    private const MEDIA_FILES = [
        'site_layout' => 'site_layout',
        'master_plan' => 'master_plan',
        'brochure' => 'brochure',
        'price_list' => 'price_list',
        'payment_schedule_file' => 'payment_schedule',
    ];

    /** External links stored as media rows with `url` instead of `path`. */
    private const MEDIA_LINKS = [
        'video_url' => 'video',
        'virtual_tour_url' => 'virtual_tour',
    ];

    /** Free-text areas the sheet describes as lists — stored one item per JSON array entry. */
    private const LIST_FIELDS = [
        'connectivity_highlights', 'nearby_infrastructure', 'other_charges',
        // approving_authorities, bank_approvals and awards came off this list with the
        // Timeline and Compliance steps. They must not be re-added while nothing collects
        // them: detailAttributes() writes every entry here on every save, so an
        // uncollected field would be blanked on each edit rather than simply left alone.
    ];

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $query = Property::query()
            ->with('developer:id,company_name,logo_path')
            ->search($request->query('search'))
            ->filter($this->filters($request, [
                'developer_id', 'type', 'project_status', 'city', 'status', 'developer_status',
            ]));

        $query = $this->applySort($query, $request, self::SORTABLE);

        $grouped = $this->exportColumns();

        if ($format = $this->exportFormat($request)) {
            return $this->exportList($format, $query, 'listings', 'Listings',
                $this->flattenGroupedColumns($grouped), $request);
        }

        // One grouped query for the header tiles rather than four COUNT(*) round trips.
        $counts = Property::selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN listing_status = 'active' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN developer_status = 'pending' THEN 1 ELSE 0 END) as awaiting")
            ->selectRaw("SUM(CASE WHEN developer_status = 'declined' THEN 1 ELSE 0 END) as declined")
            ->selectRaw("SUM(CASE WHEN listing_status = 'active' AND developer_status = 'accepted' THEN 1 ELSE 0 END) as live")
            ->first();

        return view('admin.properties', [
            'properties' => $this->paginate($query, $request),
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'cities' => Property::query()->distinct()->orderBy('city')->pluck('city')->filter()->values(),
            // Filter options track the editable list in Settings → Project types.
            'projectTypes' => ProjectType::ordered()->pluck('name', 'name'),
            'totals' => [
                'all' => (int) $counts->total,
                'published' => (int) $counts->published,
                // "Live" is the only number that means brokers can actually see it:
                // admin published AND developer accepted.
                'live' => (int) $counts->live,
                'awaiting' => (int) $counts->awaiting,
                'declined' => (int) $counts->declined,
            ],
            'export' => [
                'groups' => $this->exportGroupLabels($grouped),
                'templates' => $this->exportTemplates(),
            ],
        ]);
    }

    /**
     * Exportable columns, grouped for the picker: group label => key => [label, getter].
     * Flattened for the export by {@see \App\Http\Concerns\ExportsList}, in this order.
     *
     * @return array<string,array<string,array{0:string,1:callable}>>
     */
    private function exportColumns(): array
    {
        return [
            'Listing' => [
                'name' => ['Listing', fn (Property $p) => $p->name],
                'developer' => ['Developer', fn (Property $p) => $p->developer?->company_name],
                'type' => ['Type', fn (Property $p) => $p->project_type],
                'project_status' => ['Project status', fn (Property $p) => $p->project_status],
                'listing_status' => ['Listing status', fn (Property $p) => ucfirst((string) $p->listing_status)],
                'developer_status' => ['Developer status', fn (Property $p) => ucfirst((string) $p->developer_status)],
                'rera_number' => ['RERA number', fn (Property $p) => $p->rera_number],
            ],
            'Location' => [
                'city' => ['City', fn (Property $p) => $p->city],
                'state' => ['State', fn (Property $p) => $p->state],
                'locality' => ['Locality', fn (Property $p) => $p->locality],
                'pincode' => ['Pincode', fn (Property $p) => $p->pincode],
            ],
            'Pricing' => [
                'price_min' => ['Price min', fn (Property $p) => $p->price_min],
                'price_max' => ['Price max', fn (Property $p) => $p->price_max],
                'price_per_sqft' => ['Price / sqft', fn (Property $p) => $p->price_per_sqft],
                'currency' => ['Currency', fn (Property $p) => $p->currency],
            ],
            'Scale' => [
                'total_units' => ['Total units', fn (Property $p) => $p->total_units],
                'towers' => ['Towers', fn (Property $p) => $p->towers],
                'possession_date' => ['Possession', fn (Property $p) => $p->possession_date?->format('Y-m-d')],
            ],
            'Engagement' => [
                'views' => ['Views', fn (Property $p) => $p->views_count],
                'interests' => ['Interests', fn (Property $p) => $p->interests_count],
                'created' => ['Created', fn (Property $p) => $p->created_at?->format('Y-m-d')],
            ],
        ];
    }

    /**
     * Named starting points for the picker; Select all / Deselect all are always offered too.
     *
     * @return array<string,array<int,string>>
     */
    private function exportTemplates(): array
    {
        return [
            'Standard' => ['name', 'developer', 'city', 'type', 'listing_status', 'developer_status', 'created'],
            'Pricing' => ['name', 'developer', 'price_min', 'price_max', 'price_per_sqft', 'currency'],
            'Engagement' => ['name', 'developer', 'views', 'interests', 'listing_status'],
        ];
    }

    /** The nine-step intake form — one form, one POST. */
    public function create(): View
    {
        $this->authorize('edit-module', 'properties');

        return view('admin.properties.create', [
            'locationTree' => $this->locationTree(),
            'unitTypeOptions' => UnitType::optionsFor(),
            'extentMetricOptions' => MeasurementUnit::optionsFor(),
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'amenityOptions' => Amenity::optionsFor(),
            'fieldsEnabled' => $this->fieldsEnabled(),
        ] + $this->projectTypeData(old('project_type')));
    }

    /**
     * Non-core `property_listing` fields the admin has switched off under
     * Settings > Form fields — see the matching note on `isFieldEnabled` in
     * CompleteProfileScreen.js, the mobile-side equivalent of this same toggle.
     * `amenities`/`cp_commission_percent` are the only two fields on this form that
     * actually correspond to a seeded, non-core field_key; core ones (name, price_min,
     * the location section) can't be turned off from the admin panel at all, so this
     * never needs to gate them.
     *
     * @return array<string,bool>
     */
    private function fieldsEnabled(): array
    {
        return FormField::query()
            ->where('form', FormField::FORM_PROPERTY)
            ->pluck('enabled', 'field_key')
            ->all();
    }

    /**
     * Shared by create and edit: the selectable types, the name => requires-possession
     * map the form's conditional field reads, and which type is currently chosen.
     *
     * Inactive types are still offered when a project already uses one, so editing an
     * old project does not silently switch its type on save.
     */
    private function projectTypeData(?string $current): array
    {
        $types = ProjectType::query()
            ->where(fn ($q) => $q->active()->when($current, fn ($w) => $w->orWhere('name', $current)))
            ->ordered()
            ->get();

        return [
            'projectTypes' => $types,
            'possessionByType' => ProjectType::possessionMap(),
            'selectedProjectType' => $current ?? $types->first()?->name ?? '',
        ];
    }

    public function store(Request $request, PushNotifier $push): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $data = $request->validate($this->rules());

        $property = DB::transaction(function () use ($request, $data) {
            $property = Property::create($this->propertyAttributes($data));

            // Uploads live under the property id, so deleting a project takes its files
            // with it. That id only exists after the insert, hence the follow-up update.
            $branding = [];
            if ($file = $request->file('logo')) {
                $branding['logo_path'] = $this->upload($file, $property->id);
            }
            if ($file = $request->file('cover_image')) {
                $branding['cover_image_path'] = $this->upload($file, $property->id);
            }
            if ($branding) {
                $property->update($branding);
            }

            PropertyDetail::create($this->detailAttributes($data, $request, $property->id));

            $this->syncUnitTypes($request, $property->id);
            $this->syncMedia($request, $data, $property->id);

            return $property;
        });

        // Only for a listing that is actually live — a draft is not news to anyone, and
        // notifying on save would fire again every time the draft is edited.
        if ($data['listing_status'] === 'active') {
            $push->propertyAssigned($property);
            $this->notifyDeveloperByEmail($property);
        }

        return redirect()
            ->route('admin.properties')
            ->with('status', $data['listing_status'] === 'active'
                ? "\"{$data['name']}\" is live to brokers."
                : "\"{$data['name']}\" saved as a draft.");
    }

    /**
     * Emails the developer the full project sheet with one-click accept/decline links.
     *
     * Same swallow-every-failure shape as ApprovalController::notifyApproved — the
     * project is already saved and live to the push notification either way; an
     * unreachable SMTP host must not turn a successful save into a 500.
     */
    private function notifyDeveloperByEmail(Property $property): bool
    {
        if (! MailSettings::apply()) {
            return false;
        }

        $developer = $property->developer ?? $property->loadMissing('developer')->developer;
        if (! $developer?->email) {
            return false;
        }

        try {
            $property->loadMissing(['detail', 'unitTypes']);

            $expires = now()->addDays(14);
            $acceptUrl = URL::temporarySignedRoute('developer-response.show', $expires, [
                'property' => $property->id,
                'action' => 'accept',
            ]);
            $declineUrl = URL::temporarySignedRoute('developer-response.show', $expires, [
                'property' => $property->id,
                'action' => 'decline',
            ]);

            Mail::to($developer->email)->send(new ProjectAssignedMail($property, $acceptUrl, $declineUrl));

            return true;
        } catch (\Throwable $e) {
            Log::error('Project assignment email failed', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * A starter CSV for {@see bulkImport()} — the core intake fields only (basics,
     * location, pricing/scale). Media, unit types, commercial terms, developer terms and
     * sales info have no columns here on purpose: a spreadsheet cell cannot hold a photo
     * or a PDF, so every bulk-imported listing lands as a draft and gets those finished
     * by hand on its own edit page afterward — the same page a one-at-a-time listing
     * uses, nothing bulk-specific to learn.
     */
    public function bulkImportTemplate(): Response
    {
        $this->authorize('edit-module', 'properties');

        $columns = [
            'developer', 'name', 'project_type', 'project_status',
            'tagline', 'description',
            'country', 'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
            'latitude', 'longitude', 'maps_link',
            'price_min', 'price_max', 'extent_metric', 'total_units', 'towers', 'floors_per_tower',
            'land_parcel_acres', 'total_project_area_sqft', 'possession_date',
        ];

        $sample = [
            'Skyline Realty Group', 'Emerald Meadows Phase 2', 'Residential', 'Under Construction',
            'Where green living meets modern comfort', '',
            'India', 'Telangana', 'Hyderabad', 'Kokapet', '', '', '500075', 'West',
            '17.4065', '78.3269', '',
            '8500000', '16500000', 'Sq.ft.', '216', '3', '18',
            '4.2', '182000', '2028-04-05',
        ];

        $csv = implode(',', $columns) . "\n" . implode(',', array_map(
            fn ($v) => str_contains($v, ',') ? '"' . str_replace('"', '""', $v) . '"' : $v,
            $sample
        )) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="properties-import-template.csv"',
        ]);
    }

    /**
     * One row per project. Every row lands as a `draft` regardless of what the sheet
     * says — see the note on bulkImportTemplate() — so nothing here ever fires the
     * developer-assignment push/email a hundred times in one go; that only happens once
     * an admin deliberately publishes a finished listing, same as a one-at-a-time create.
     */
    public function bulkImport(Request $request): View
    {
        $this->authorize('edit-module', 'properties');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Choose the filled-in CSV file first.',
            'file.mimes' => 'That is not a CSV file. In Excel or Google Sheets use '
                . '"Save as"/"Download" and pick CSV — an .xlsx is not read directly.',
            'file.max' => 'That file is over 5 MB. Split it into a few smaller sheets and upload them one by one.',
        ]);

        // The columns a listing cannot be created without — checked as a header before any
        // row is read, so the wrong sheet fails once instead of on every row.
        $rows = CsvReader::rows($request->file('file'), [
            'developer', 'name', 'project_type', 'project_status', 'city', 'price_min',
        ]);

        $results = [];

        foreach ($rows as $number => $row) {
            $results[] = $this->importPropertyRow($number, $row);
        }

        return view('admin.bulk-import-result', [
            'type' => 'properties',
            'results' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'failed' => count(array_filter($results, fn ($r) => $r['status'] === 'failed')),
        ]);
    }

    /**
     * Every step below is inside one try/catch, not just the save — see the matching
     * note on {@see DeveloperController::importDeveloperRow()}. A hand-built CSV
     * missing an entire optional column used to throw an uncaught "Undefined array
     * key" here too and take down the whole import instead of just failing that row.
     *
     * @return array{row: int, status: string, name: ?string, property_id: ?int, error: ?string}
     */
    private function importPropertyRow(int $number, array $row): array
    {
        $name = null;

        try {
            $developerName = trim((string) ($row['developer'] ?? ''));
            $developer = $developerName !== ''
                ? Developer::whereRaw('LOWER(company_name) = ?', [Str::lower($developerName)])->first()
                : null;

            $data = [
                'developer_id' => $developer?->id,
                'name' => $row['name'] ?? '',
                'project_type' => $row['project_type'] ?? '',
                'project_status' => $row['project_status'] ?? '',
                'tagline' => $row['tagline'] ?? null,
                'description' => $row['description'] ?? null,
                'country' => $row['country'] ?? null,
                'state' => $row['state'] ?? null,
                'city' => $row['city'] ?? '',
                'locality' => $row['locality'] ?? null,
                'full_address' => $row['full_address'] ?? null,
                'landmark' => $row['landmark'] ?? null,
                'pincode' => $row['pincode'] ?? null,
                'zone' => $row['zone'] ?? null,
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
                'maps_link' => $row['maps_link'] ?? null,
                'price_min' => $row['price_min'] ?? null,
                'price_max' => $row['price_max'] ?? null,
                'extent_metric' => $row['extent_metric'] ?? null,
                'total_units' => $row['total_units'] ?? null,
                'towers' => $row['towers'] ?? null,
                'floors_per_tower' => $row['floors_per_tower'] ?? null,
                'land_parcel_acres' => $row['land_parcel_acres'] ?? null,
                'total_project_area_sqft' => $row['total_project_area_sqft'] ?? null,
                'possession_date' => $row['possession_date'] ?? null,
            ];

            // Blank optional cells arrive as '' (see CsvReader), which a `nullable`
            // rule treats as present-but-empty rather than absent — normalise once,
            // here, instead of relying on every field above to remember to do it.
            $required = ['developer_id', 'name', 'project_type', 'project_status', 'city'];
            foreach ($data as $key => $value) {
                if (! in_array($key, $required, true) && $value === '') {
                    $data[$key] = null;
                }
            }

            $name = $data['name'] ?: null;

            $validator = Validator::make($data, [
                'developer_id' => ['required', 'exists:developers,id'],
                'name' => ['required', 'string', 'max:255'],
                'project_type' => ['required', Rule::exists('project_types', 'name')],
                'project_status' => ['required', 'in:New Launch,Under Construction,Ready to Move,Nearing Completion'],
                'tagline' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
                'country' => ['nullable', 'string', 'max:96'],
                'state' => ['nullable', 'string', 'max:96'],
                'city' => ['required', 'string', 'max:96'],
                'locality' => ['nullable', 'string', 'max:128'],
                'full_address' => ['nullable', 'string', 'max:1000'],
                'landmark' => ['nullable', 'string', 'max:255'],
                'pincode' => ['nullable', 'string', 'max:12'],
                'zone' => ['nullable', 'in:East,West,North,South,Central'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'maps_link' => ['nullable', 'url', 'max:255'],
                'price_min' => ['required', 'integer', 'min:0'],
                'price_max' => ['nullable', 'integer', 'gte:price_min'],
                'extent_metric' => ['nullable', 'string', 'max:96'],
                'total_units' => ['nullable', 'integer', 'min:0'],
                'towers' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'floors_per_tower' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'land_parcel_acres' => ['nullable', 'numeric', 'min:0'],
                'total_project_area_sqft' => ['nullable', 'integer', 'min:0'],
                'possession_date' => ['nullable', 'date'],
            ], [
                'developer_id.required' => $developerName !== ''
                    ? "No developer found matching \"{$developerName}\"."
                    : 'The developer column is required.',
                'developer_id.exists' => "No developer found matching \"{$developerName}\".",
            ]);

            if ($validator->fails()) {
                return [
                    'row' => $number,
                    'status' => 'failed',
                    'name' => $name,
                    'property_id' => null,
                    'error' => implode(' ', $validator->errors()->all()),
                ];
            }

            $clean = $validator->validated();

            $property = DB::transaction(function () use ($clean) {
                $property = Property::create($clean + [
                    'listing_status' => 'draft',
                    'currency' => 'INR',
                    'slug' => Str::slug($clean['name']) . '-' . Str::lower(Str::random(5)),
                    'vastu_compliant' => false,
                ]);

                PropertyDetail::create(['property_id' => $property->id]);

                return $property;
            });
        } catch (\Throwable $e) {
            return [
                'row' => $number,
                'status' => 'failed',
                'name' => $name,
                'property_id' => null,
                'error' => 'Could not save this row: ' . $e->getMessage(),
            ];
        }

        return [
            'row' => $number,
            'status' => 'created',
            'name' => $property->name,
            'property_id' => $property->id,
            'error' => null,
        ];
    }

    /** Everything captured for one project, grouped the way the intake form groups it. */
    public function show(Property $property): View
    {
        $this->authorize('view-module', 'properties');

        $property->load(['developer', 'detail', 'unitTypes', 'media']);

        return view('admin.properties.show', [
            'property' => $property,
            'stats' => [
                'views' => $property->views_count,
                'interests' => $property->interests_count,
                'leads' => $property->leads()->count(),
                'units' => $property->unitTypes->count(),
            ],
        ]);
    }

    public function edit(Property $property): View
    {
        $this->authorize('edit-module', 'properties');

        $property->load(['detail', 'unitTypes', 'media']);

        return view('admin.properties.edit', [
            'locationTree' => $this->locationTree($property),
            'unitTypeOptions' => UnitType::optionsFor($property->unitTypes->pluck('label')),
            'extentMetricOptions' => MeasurementUnit::optionsFor($property->extent_metric),
            'property' => $property,
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            // Retired amenities this project already lists stay on the grid, checked —
            // see Amenity::optionsFor().
            'amenityOptions' => Amenity::optionsFor($property->detail?->amenities),
            // Flat map of form-field name => current value; see toFormValues().
            'formRecord' => $this->toFormValues($property),
            'fieldsEnabled' => $this->fieldsEnabled(),
        ] + $this->projectTypeData(old('project_type', $property->project_type)));
    }

    /**
     * Serves both the full edit form and the row menu's publish/draft/archive item.
     *
     * The quick action posts `listing_status` alone, so every other rule is `sometimes` and
     * the child records are only touched when the full form announces itself with `_full`.
     * Without that guard a one-field status change would wipe the detail row, the unit types
     * and every attachment.
     */
    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $isFullForm = $request->boolean('_full');

        $data = $request->validate($isFullForm
            ? $this->rules()
            : ['listing_status' => ['required', 'in:draft,active,archived']]);

        if (! $isFullForm) {
            $property->update($data);

            return back()->with('success', 'Listing status updated.');
        }

        DB::transaction(function () use ($request, $data, $property) {
            $attributes = $this->propertyAttributes($data);
            // The slug is part of the project's public identity — renaming must not break
            // links that are already out with brokers.
            unset($attributes['slug']);

            $property->update($attributes + $this->replacedBranding($request, $property));

            // A project created before this form existed may have no detail row yet.
            $property->detail()->updateOrCreate(
                ['property_id' => $property->id],
                $this->detailAttributes($data, $request, $property->id, $property->detail)
            );

            // Unit types are positional, not identified — rebuilt wholesale, with each row's
            // existing floor plan carried forward in a hidden field unless it is replaced.
            $property->unitTypes()->delete();
            $this->syncUnitTypes($request, $property->id);

            $this->removeMedia($request, $property);
            $this->syncMedia($request, $data, $property->id);
        });

        return redirect()
            ->route('admin.properties.show', $property)
            ->with('success', "\"{$property->name}\" updated.");
    }

    /**
     * Soft delete. `properties` is soft-deleting by design: `leads.property_id` cascades on
     * a hard delete, so purging a project would destroy the lead history that sits against
     * it. This removes the listing from every admin list and from broker view, and leaves
     * the row recoverable.
     */
    /**
     * The detail row, unit types, media and leads all cascade on properties.id; the
     * files they named do not, which is what PropertyDeleter is for.
     */
    /**
     * Moves the listing to Trash — reversible, see restore(). Only the row's own
     * `deleted_at` is set here; its files are left exactly where they are, since
     * restoring should bring back a fully working listing, not one missing its
     * gallery and brochure. See forceDelete() for the irreversible version this
     * action used to be.
     */
    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $name = $property->name;
        $property->delete();

        return redirect()
            ->route('admin.properties')
            ->with('success', "\"{$name}\" was moved to Trash.");
    }

    /** Undoes destroy() — the listing and its files (never touched) are both back. */
    public function restore(int $property): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $property = Property::onlyTrashed()->findOrFail($property);
        $property->restore();

        return redirect()
            ->route('admin.trash')
            ->with('success', "\"{$property->name}\" was restored.");
    }

    /**
     * The irreversible version of destroy() — only reachable from Trash. Deletes the
     * row for good and every file it owned.
     */
    public function forceDelete(int $property, PropertyDeleter $deleter): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $property = Property::onlyTrashed()->findOrFail($property);
        $name = $property->name;
        $paths = $deleter->filePathsFor($property);

        $property->forceDelete();

        foreach ($paths as $path) {
            \App\Support\FileStorage::delete($path);
        }

        return redirect()
            ->route('admin.trash')
            ->with('warning', "\"{$name}\" and all of its files were permanently deleted.");
    }

    // ------------------------------------------------------------------ edit support

    /**
     * The inverse of propertyAttributes()/detailAttributes(): one flat array keyed by form
     * field name, so the wizard can repopulate without every field knowing where its value
     * actually lives. Dates become Y-m-d for <input type="date">, and the JSON list columns
     * become the newline-per-item text their textareas expect.
     *
     * @return array<string, mixed>
     */
    private function toFormValues(Property $property): array
    {
        $detail = $property->detail;

        $values = $property->only([
            'developer_id', 'name', 'project_type', 'project_status', 'listing_status',
            'tagline', 'description',
            'country', 'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
            'latitude', 'longitude', 'maps_link',
            'price_min', 'price_max', 'extent_metric', 'currency',
            'total_units', 'towers', 'floors_per_tower',
            'land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
            'green_certification', 'vastu_compliant',
        ]);

        $values['possession_date'] = $property->possession_date?->format('Y-m-d');

        if ($detail) {
            $values += $detail->only([
                'booking_amount', 'cp_commission_percent', 'fos_commission_percent', 'special_incentives', 'cashback_schemes',
                'registration_stamp_duty', 'maintenance_charges', 'floor_rise', 'plc_charges',
                'payment_schedule', 'sales_office_address', 'site_visit_timings',
                'sales_contact_name', 'sales_contact_number', 'booking_process',
                'terms_type', 'terms_title', 'terms_content',
            ]);

            // Not a form value — the editor shows what is already attached so an admin
            // can tell "no document" from "a document I am about to replace".
            $values['terms_document_path'] = $detail->terms_document_path;

            foreach (self::LIST_FIELDS as $field) {
                $values[$field] = implode("\n", $detail->{$field} ?? []);
            }

            // Only the checkboxes are editable now, so only those are repopulated. Any
            // amenity saved outside that list is preserved on save — see amenities().
            //
            // Matched against the whole catalogue, not just the amenities currently
            // offered: one retired in Settings must stay a ticked checkbox here rather
            // than drop off the form, and Amenity::optionsFor() puts it back on the grid
            // for exactly this reason.
            $values['amenities'] = collect($detail->amenities ?? [])
                ->intersect(collect(Amenity::catalogue()))
                ->values()
                ->all();
            $values['payment_plan_options'] = $detail->payment_plan_options ?? [];
        }

        // External links live in property_media, not on a column.
        foreach (self::MEDIA_LINKS as $key => $kind) {
            $values[$key] = $property->media->firstWhere('kind', $kind)?->url;
        }

        return $values;
    }

    /** New logo/cover uploads, deleting what they replace only once stored. */
    private function replacedBranding(Request $request, Property $property): array
    {
        $branding = [];

        foreach (['logo' => 'logo_path', 'cover_image' => 'cover_image_path'] as $input => $column) {
            if (! $file = $request->file($input)) {
                continue;
            }

            $previous = $property->{$column};
            $branding[$column] = $this->upload($file, $property->id);

            if ($previous) {
                \App\Support\FileStorage::delete($previous);
            }
        }

        return $branding;
    }

    /** Attachments the admin ticked for removal on the edit form. */
    private function removeMedia(Request $request, Property $property): void
    {
        $ids = array_filter((array) $request->input('remove_media', []));
        if (! $ids) {
            return;
        }

        foreach ($property->media()->whereIn('id', $ids)->get() as $media) {
            if ($media->path) {
                \App\Support\FileStorage::delete($media->path);
            }
            $media->delete();
        }
    }

    // ------------------------------------------------------------------ validation

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        $anyDoc = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];

        return [
            // Marks a post as coming from the full intake form rather than the row menu's
            // one-field status action — see update().
            '_full' => ['nullable', 'boolean'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],

            // 1 · Project basic info
            'developer_id' => ['required', 'exists:developers,id'],
            'name' => ['required', 'string', 'max:255'],
            // Editable master data now — see Settings → Project types.
            'project_type' => ['required', Rule::exists('project_types', 'name')],
            'project_status' => ['required', 'in:New Launch,Under Construction,Ready to Move,Nearing Completion'],
            'listing_status' => ['required', 'in:draft,active,archived'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'logo' => ['nullable', 'image', 'max:2048'],

            // 2 · Location details
            'country' => ['nullable', 'string', 'max:96'],
            'state' => ['nullable', 'string', 'max:96'],
            'city' => ['required', 'string', 'max:96'],
            'locality' => ['nullable', 'string', 'max:128'],
            'full_address' => ['nullable', 'string', 'max:1000'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'zone' => ['nullable', 'in:East,West,North,South,Central'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'maps_link' => ['nullable', 'url', 'max:255'],
            'connectivity_highlights' => ['nullable', 'string', 'max:5000'],
            'nearby_infrastructure' => ['nullable', 'string', 'max:5000'],

            // 3 · Configuration & pricing
            'currency' => ['required', 'in:INR'],
            'price_min' => ['required', 'integer', 'min:0'],
            // No longer collected by the form — the price band collapsed to a single
            // "Starting from". Kept nullable rather than dropped so existing records and
            // the API contract are unaffected, and so a ceiling can be reintroduced later.
            'price_max' => ['nullable', 'integer', 'gte:price_min'],
            'extent_metric' => ['nullable', 'string', 'max:96'],
            'total_units' => ['nullable', 'integer', 'min:0'],
            'towers' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'floors_per_tower' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'unit_types' => ['nullable', 'array', 'max:25'],
            'unit_types.*.label' => ['nullable', 'string', 'max:64'],
            'unit_types.*.carpet_area_sqft' => ['nullable', 'integer', 'min:0'],
            'unit_types.*.built_up_area_sqft' => ['nullable', 'integer', 'min:0'],
            'unit_types.*.super_built_up_area_sqft' => ['nullable', 'integer', 'min:0'],
            'unit_types.*.price_min' => ['nullable', 'integer', 'min:0'],
            'unit_types.*.price_max' => ['nullable', 'integer', 'min:0', 'gte:unit_types.*.price_min'],
            'unit_types.*.units_count' => ['nullable', 'integer', 'min:0'],
            'unit_types.*.floor_plan' => $anyDoc,
            'unit_types.*.existing_floor_plan' => ['nullable', 'string', 'max:255'],
            'unit_plans' => ['nullable', 'array', 'max:20'],
            'unit_plans.*' => ['image', 'max:5120'],

            // 4 · Project specifications
            'land_parcel_acres' => ['nullable', 'numeric', 'min:0'],
            'total_project_area_sqft' => ['nullable', 'integer', 'min:0'],
            'open_space_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'amenities' => ['nullable', 'array', 'max:60'],
            'amenities.*' => ['string', 'max:96'],
            'green_certification' => ['nullable', 'string', 'max:64'],
            'vastu_compliant' => ['nullable', 'boolean'],

            // possession_date is collected in step 1, under the project type that reveals
            // it. Mandatory only for the types flagged in Settings — RERA requires a
            // completion date for built units, not for land-only types.
            'possession_date' => [
                Rule::requiredIf(fn () => (bool) ProjectType::where('name', request('project_type'))
                    ->value('requires_possession_date')),
                'nullable', 'date',
            ],

            // 5 · Media & marketing assets
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array', 'max:30'],
            'gallery.*' => ['image', 'max:5120'],
            'site_layout' => $anyDoc,
            'master_plan' => $anyDoc,
            'brochure' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'price_list' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'virtual_tour_url' => ['nullable', 'url', 'max:255'],
            'payment_schedule_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'payment_schedule' => ['nullable', 'string', 'max:5000'],

            // 7 · Commercial terms
            'payment_plan_options' => ['nullable', 'array', 'max:10'],
            'payment_plan_options.*' => ['string', 'max:64'],
            'booking_amount' => ['nullable', 'integer', 'min:0'],
            'cp_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fos_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'special_incentives' => ['nullable', 'string', 'max:5000'],
            'cashback_schemes' => ['nullable', 'string', 'max:5000'],
            'registration_stamp_duty' => ['nullable', 'string', 'max:255'],
            'maintenance_charges' => ['nullable', 'string', 'max:255'],
            'floor_rise' => ['nullable', 'string', 'max:255'],
            'plc_charges' => ['nullable', 'string', 'max:255'],
            'other_charges' => ['nullable', 'string', 'max:5000'],

            // 7 · Developer terms — one artefact per project, supplied either way.
            'terms_type' => ['nullable', 'in:document,text'],
            'terms_title' => ['nullable', 'string', 'max:255'],
            // required_if, so choosing "document" with nothing attached is caught here
            // rather than saving a project whose terms button opens nothing. On edit the
            // rule is relaxed by rules() when a document is already stored.
            'terms_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'terms_content' => ['nullable', 'string', 'max:200000'],

            // 8 · Contact & sales info
            'sales_office_address' => ['nullable', 'string', 'max:1000'],
            'site_visit_timings' => ['nullable', 'string', 'max:255'],
            'sales_contact_name' => ['nullable', 'string', 'max:255'],
            'sales_contact_number' => ['nullable', 'string', 'max:32'],
            'booking_process' => ['nullable', 'string', 'max:5000'],
        ];
    }

    // ------------------------------------------------------------------ mapping

    /**
     * The country → state → city cascade for the location step, shaped as
     * ['India' => ['Telangana' => ['Hyderabad', …], …], …].
     *
     * Read from the master list an admin maintains in Settings → Locations, eager-loaded
     * two levels deep so the whole tree is three queries rather than one per state.
     *
     * A project being edited has its saved triple folded in even when that city has since
     * been removed from the list. Without it the select would fall back to its first option
     * and quietly re-save a different city than the one on record.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function locationTree(?Property $property = null): array
    {
        $tree = Country::with(['states' => fn ($q) => $q->orderBy('name')->with(['cities' => fn ($c) => $c->orderBy('name')])])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Country $country) => [
                $country->name => $country->states
                    ->mapWithKeys(fn ($state) => [$state->name => $state->cities->pluck('name')->all()])
                    ->all(),
            ])
            ->all();

        if ($property && filled($property->country) && filled($property->state) && filled($property->city)) {
            $tree[$property->country] ??= [];
            $tree[$property->country][$property->state] ??= [];

            if (! in_array($property->city, $tree[$property->country][$property->state], true)) {
                $tree[$property->country][$property->state][] = $property->city;
                sort($tree[$property->country][$property->state]);
            }
        }

        return $tree;
    }

    /** Columns that live on `properties` — the ones listings filter and sort on. */
    private function propertyAttributes(array $data): array
    {
        $columns = [
            'developer_id', 'name', 'project_type', 'project_status', 'listing_status',
            // rera_number / rera_registered_at / rera_valid_till are no longer collected —
            // the columns stay so existing records keep their values, but the form owns none.
            'tagline', 'description',
            'country', 'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
            'latitude', 'longitude', 'maps_link',
            'price_min', 'price_max', 'extent_metric', 'currency',
            'total_units', 'towers', 'floors_per_tower',
            'land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
            'possession_date', 'green_certification',
        ];

        // launch_date and construction_progress are no longer collected. Leaving
        // construction_progress in the returned array would reset it to 0 on every edit,
        // so it is simply absent — the column default covers new rows and existing
        // records keep the figure they already hold.
        return array_intersect_key($data, array_flip($columns)) + [
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(5)),
            'vastu_compliant' => (bool) ($data['vastu_compliant'] ?? false),
        ];
    }

    /** The long tail, plus the two documents whose paths are stored on the detail row. */
    private function detailAttributes(array $data, Request $request, int $propertyId, ?PropertyDetail $existing = null): array
    {
        $columns = [
            'booking_amount', 'cp_commission_percent', 'fos_commission_percent', 'special_incentives', 'cashback_schemes',
            'registration_stamp_duty', 'maintenance_charges', 'floor_rise', 'plc_charges',
            'payment_schedule', 'sales_office_address', 'site_visit_timings',
            'sales_contact_name', 'sales_contact_number', 'booking_process',
        ];

        $attributes = array_intersect_key($data, array_flip($columns)) + [
            'property_id' => $propertyId,
            'amenities' => $this->amenities($data, $existing),
            'payment_plan_options' => $data['payment_plan_options'] ?? null,
        ];

        foreach (self::LIST_FIELDS as $field) {
            $attributes[$field] = $this->lines($data[$field] ?? null);
        }

        $attributes += $this->termsAttributes($data, $request, $propertyId, $existing);

        // legal_due_diligence went with the Compliance step. Nothing writes the column
        // now, so a record that already has a report keeps it and the file stays on disk.

        return $attributes;
    }

    /**
     * The developer terms block — title and the document.
     *
     * `terms_type` is derived here, not read from the form: the form no longer offers a
     * choice, only a document, so "document" is true whenever one is actually on file —
     * either just uploaded or already on record — and null otherwise. A property whose
     * terms were typed in before that option was removed keeps its `terms_content` and
     * `terms_type = 'text'` untouched unless this save also attaches a document.
     */
    private function termsAttributes(array $data, Request $request, int $propertyId, ?PropertyDetail $existing): array
    {
        $documentPath = $existing?->terms_document_path;

        if ($file = $request->file('terms_document')) {
            $documentPath = $this->upload($file, $propertyId);

            if ($existing?->terms_document_path) {
                \App\Support\FileStorage::delete($existing->terms_document_path);
            }
        }

        return [
            'terms_type' => $documentPath ? 'document' : ($existing?->terms_type),
            'terms_title' => filled($data['terms_title'] ?? null) ? $data['terms_title'] : null,
            'terms_content' => $existing?->terms_content,
            'terms_document_path' => $documentPath,
        ];
    }

    /**
     * The amenity checkboxes, plus anything already saved that the grid cannot represent.
     *
     * The "other amenities" free-text field is gone, so the checkboxes are the only input.
     * Rebuilding the list from them alone would drop every custom amenity a project already
     * carries the first time someone edits an unrelated field — the form would silently
     * delete data it never showed. Values outside the catalogue are therefore carried over
     * from the stored row.
     *
     * Note this is the catalogue, not the offered set: a retired amenity *is* rendered as a
     * checkbox on a project that has it, so unticking it has to remove it. Carrying it over
     * would make that checkbox impossible to clear.
     */
    private function amenities(array $data, ?PropertyDetail $existing = null): ?array
    {
        $custom = collect($existing?->amenities ?? [])
            ->diff(collect(Amenity::catalogue()));

        $all = collect($data['amenities'] ?? [])->concat($custom)->unique()->values()->all();

        return $all ?: null;
    }

    /**
     * A textarea the sheet describes as a list ("Metro — 800 m" per line) into a JSON array.
     * Returns null rather than [] so an untouched field reads as "not provided".
     *
     * @return array<int, string>|null
     */
    private function lines(?string $value): ?array
    {
        $items = collect(preg_split('/\R/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $items ?: null;
    }

    // ------------------------------------------------------------------ children

    private function syncUnitTypes(Request $request, int $propertyId): void
    {
        foreach (array_values($request->input('unit_types', []) ?? []) as $index => $row) {
            // A row with no label is an empty template row the admin never filled in.
            if (blank($row['label'] ?? null)) {
                continue;
            }

            $plan = $request->file("unit_types.{$index}.floor_plan");
            // Rows are rebuilt on every edit, so a row that keeps its existing plan sends
            // the stored path back in a hidden field rather than re-uploading the file.
            $keptPlan = $row['existing_floor_plan'] ?? null;

            PropertyUnitType::create([
                'property_id' => $propertyId,
                'label' => $row['label'],
                'carpet_area_sqft' => $row['carpet_area_sqft'] ?? null,
                'built_up_area_sqft' => $row['built_up_area_sqft'] ?? null,
                'super_built_up_area_sqft' => $row['super_built_up_area_sqft'] ?? null,
                'price_min' => $row['price_min'] ?? null,
                'price_max' => $row['price_max'] ?? null,
                'units_count' => $row['units_count'] ?? null,
                'floor_plan_path' => $plan ? $this->upload($plan, $propertyId) : $keptPlan,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncMedia(Request $request, array $data, int $propertyId): void
    {
        $rows = [];

        // Ordered galleries: sort_order is the position within the kind, not globally.
        foreach (['gallery' => 'image', 'unit_plans' => 'unit_plan'] as $key => $kind) {
            foreach ($request->file($key, []) ?? [] as $index => $file) {
                $rows[] = ['kind' => $kind, 'path' => $this->upload($file, $propertyId), 'sort_order' => $index];
            }
        }

        // Single-file kinds: a re-upload replaces the property's one row of that kind.
        // The previous row (and its file, on disk) is removed first — the same
        // delete-then-store order replacedBranding() already uses for logo/cover —
        // so a swap never leaves an orphaned file behind or piles up duplicate rows.
        foreach (self::MEDIA_FILES as $key => $kind) {
            if ($file = $request->file($key)) {
                $this->replaceSingleMedia($propertyId, $kind);
                $rows[] = ['kind' => $kind, 'path' => $this->upload($file, $propertyId), 'sort_order' => 0];
            }
        }

        // Same one-row-per-kind rule for link-based media — no file to leak, but
        // without this a project gains a fresh video_url/virtual_tour_url row on
        // every save instead of updating the one it already had.
        foreach (self::MEDIA_LINKS as $key => $kind) {
            if (filled($data[$key] ?? null)) {
                $this->replaceSingleMedia($propertyId, $kind);
                $rows[] = ['kind' => $kind, 'url' => $data[$key], 'sort_order' => 0];
            }
        }

        foreach ($rows as $row) {
            PropertyMedia::create($row + ['property_id' => $propertyId]);
        }
    }

    /**
     * Deletes any existing media row(s) of a single-instance kind for a property,
     * along with their files — the cleanup half of a replace, run immediately
     * before the new row for that kind is inserted in syncMedia().
     */
    private function replaceSingleMedia(int $propertyId, string $kind): void
    {
        PropertyMedia::where('property_id', $propertyId)
            ->where('kind', $kind)
            ->get()
            ->each(function (PropertyMedia $media) {
                if ($media->path) {
                    \App\Support\FileStorage::delete($media->path);
                }
                $media->delete();
            });
    }

    /** Files are grouped by property so a deleted project's assets are easy to reap. */
    private function upload(UploadedFile $file, int $propertyId): string
    {
        return $file->store("properties/{$propertyId}", \App\Support\FileStorage::diskName("properties/{$propertyId}"));
    }
}
