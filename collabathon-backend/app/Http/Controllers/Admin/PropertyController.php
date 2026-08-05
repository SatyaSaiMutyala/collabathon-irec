<?php

namespace App\Http\Controllers\Admin;

use App\Http\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\Developer;
use App\Models\ProjectType;
use App\Models\Property;
use App\Services\PushNotifier;
use App\Models\PropertyDetail;
use App\Models\PropertyMedia;
use App\Models\PropertyUnitType;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        'rera_certificate' => 'rera_certificate',
        'payment_schedule_file' => 'payment_schedule',
    ];

    /** External links stored as media rows with `url` instead of `path`. */
    private const MEDIA_LINKS = [
        'video_url' => 'video',
        'virtual_tour_url' => 'virtual_tour',
    ];

    /** Free-text areas the sheet describes as lists — stored one item per JSON array entry. */
    private const LIST_FIELDS = [
        'connectivity_highlights', 'nearby_infrastructure',
        'approving_authorities', 'bank_approvals', 'other_charges', 'awards',
    ];

    /**
     * The amenity checkboxes, enumerated on the client's field sheet. Lives here rather than
     * in the Blade so the edit form can split a saved list back into "known" and "other"
     * against the same source the form was built from.
     */
    public const AMENITY_OPTIONS = [
        'Clubhouse', 'Swimming Pool', 'Gym', "Kids' Play Area", 'Sports Court', 'Garden',
        'Jogging Track', 'Security/CCTV', 'Power Backup', 'Lift', 'Rainwater Harvesting', 'EV Charging',
    ];

    public function index(Request $request): View
    {
        $query = Property::query()
            ->with('developer:id,company_name')
            ->search($request->query('search'))
            ->filter($this->filters($request, [
                'developer_id', 'type', 'project_status', 'city', 'status', 'developer_status',
            ]));

        $query = $this->applySort($query, $request, self::SORTABLE);

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
        ]);
    }

    /** The nine-step intake form — one form, one POST. */
    public function create(): View
    {
        $this->authorize('edit-module', 'properties');

        return view('admin.properties.create', [
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'amenityOptions' => self::AMENITY_OPTIONS,
        ] + $this->projectTypeData(old('project_type')));
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
        }

        return redirect()
            ->route('admin.properties')
            ->with('status', $data['listing_status'] === 'active'
                ? "\"{$data['name']}\" is live to brokers."
                : "\"{$data['name']}\" saved as a draft.");
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
            'property' => $property,
            'developers' => Developer::orderBy('company_name')->get(['id', 'company_name']),
            'amenityOptions' => self::AMENITY_OPTIONS,
            // Flat map of form-field name => current value; see toFormValues().
            'formRecord' => $this->toFormValues($property),
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
    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('edit-module', 'properties');

        $name = $property->name;
        $property->delete();

        return redirect()
            ->route('admin.properties')
            ->with('success', "\"{$name}\" deleted.");
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
            'tagline', 'description', 'rera_number',
            'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
            'latitude', 'longitude', 'maps_link',
            'price_min', 'price_max', 'price_per_sqft', 'currency',
            'total_units', 'towers', 'floors_per_tower', 'flats_per_floor',
            'land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
            'construction_progress', 'green_certification', 'vastu_compliant',
        ]);

        foreach (['launch_date', 'possession_date'] as $date) {
            $values[$date] = $property->{$date}?->format('Y-m-d');
        }

        if ($detail) {
            $values += $detail->only([
                'construction_specifications', 'amenities_size', 'amenities_count', 'parking_details',
                'booking_amount', 'cp_commission_percent', 'special_incentives', 'cashback_schemes',
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

            // Amenities split back into the checkbox grid plus whatever was typed as extra.
            $known = collect(self::AMENITY_OPTIONS);
            $saved = collect($detail->amenities ?? []);
            $values['amenities'] = $saved->intersect($known)->values()->all();
            $values['amenities_extra'] = $saved->diff($known)->implode(', ');
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
                Storage::disk('public')->delete($previous);
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
                Storage::disk('public')->delete($media->path);
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
            'rera_number' => ['nullable', 'string', 'max:64'],

            // 2 · Location details
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
            'currency' => ['required', 'in:AED,INR,USD'],
            'price_min' => ['required', 'integer', 'min:0'],
            'price_max' => ['required', 'integer', 'gte:price_min'],
            'price_per_sqft' => ['nullable', 'integer', 'min:0'],
            'total_units' => ['nullable', 'integer', 'min:0'],
            'towers' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'floors_per_tower' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'flats_per_floor' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'parking_details' => ['nullable', 'string', 'max:255'],
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
            'construction_specifications' => ['nullable', 'string', 'max:20000'],
            'amenities' => ['nullable', 'array', 'max:60'],
            'amenities.*' => ['string', 'max:96'],
            'amenities_extra' => ['nullable', 'string', 'max:1000'],
            'amenities_size' => ['nullable', 'string', 'max:255'],
            'amenities_count' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'green_certification' => ['nullable', 'string', 'max:64'],
            'vastu_compliant' => ['nullable', 'boolean'],

            // 5 · Timeline & legal
            'launch_date' => ['nullable', 'date'],
            // Mandatory only for the types flagged in Settings — RERA requires a
            // completion date for built units, not for land-only types.
            'possession_date' => [
                Rule::requiredIf(fn () => (bool) ProjectType::where('name', request('project_type'))
                    ->value('requires_possession_date')),
                'nullable', 'date',
            ],
            'construction_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'approving_authorities' => ['nullable', 'string', 'max:5000'],
            'bank_approvals' => ['nullable', 'string', 'max:5000'],

            // 6 · Media & marketing assets
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

            // 9 · Compliance & trust signals
            'rera_certificate' => $anyDoc,
            'legal_due_diligence' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'awards' => ['nullable', 'string', 'max:5000'],
        ];
    }

    // ------------------------------------------------------------------ mapping

    /** Columns that live on `properties` — the ones listings filter and sort on. */
    private function propertyAttributes(array $data): array
    {
        $columns = [
            'developer_id', 'name', 'project_type', 'project_status', 'listing_status',
            // rera_registered_at / rera_valid_till are no longer collected — the columns
            // stay so existing records keep their dates, but the form owns neither.
            'tagline', 'description', 'rera_number',
            'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
            'latitude', 'longitude', 'maps_link',
            'price_min', 'price_max', 'price_per_sqft', 'currency',
            'total_units', 'towers', 'floors_per_tower', 'flats_per_floor',
            'land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
            'launch_date', 'possession_date', 'green_certification',
        ];

        return array_intersect_key($data, array_flip($columns)) + [
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(5)),
            'construction_progress' => $data['construction_progress'] ?? 0,
            'vastu_compliant' => (bool) ($data['vastu_compliant'] ?? false),
        ];
    }

    /** The long tail, plus the two documents whose paths are stored on the detail row. */
    private function detailAttributes(array $data, Request $request, int $propertyId, ?PropertyDetail $existing = null): array
    {
        $columns = [
            'construction_specifications', 'amenities_size', 'amenities_count', 'parking_details',
            'booking_amount', 'cp_commission_percent', 'special_incentives', 'cashback_schemes',
            'registration_stamp_duty', 'maintenance_charges', 'floor_rise', 'plc_charges',
            'payment_schedule', 'sales_office_address', 'site_visit_timings',
            'sales_contact_name', 'sales_contact_number', 'booking_process',
        ];

        $attributes = array_intersect_key($data, array_flip($columns)) + [
            'property_id' => $propertyId,
            'amenities' => $this->amenities($data),
            'payment_plan_options' => $data['payment_plan_options'] ?? null,
        ];

        foreach (self::LIST_FIELDS as $field) {
            $attributes[$field] = $this->lines($data[$field] ?? null);
        }

        $attributes += $this->termsAttributes($data, $request, $propertyId, $existing);

        if ($file = $request->file('legal_due_diligence')) {
            $attributes['legal_due_diligence_path'] = $this->upload($file, $propertyId);

            if ($existing?->legal_due_diligence_path) {
                Storage::disk('public')->delete($existing->legal_due_diligence_path);
            }
        } elseif ($existing) {
            // No replacement uploaded — an edit must not blank the stored path.
            $attributes['legal_due_diligence_path'] = $existing->legal_due_diligence_path;
        }

        return $attributes;
    }

    /**
     * The developer terms block — type, title, and whichever of the two payloads applies.
     *
     * The unselected payload is kept, not cleared: an admin who flips to "text" to draft
     * something and flips back must not find the signed PDF gone. `terms_type` is the
     * single source of which one is live, so keeping both is safe.
     */
    private function termsAttributes(array $data, Request $request, int $propertyId, ?PropertyDetail $existing): array
    {
        $type = $data['terms_type'] ?? null;

        $attributes = [
            'terms_type' => $type ?: null,
            'terms_title' => filled($data['terms_title'] ?? null) ? $data['terms_title'] : null,
            // Sanitised on the way in, never on the way out: the value is rendered inside
            // a WebView on two apps, and cleaning at every read is a guarantee that only
            // holds until someone adds a third reader.
            'terms_content' => RichText::sanitize($data['terms_content'] ?? null)
                ?? $existing?->terms_content,
            'terms_document_path' => $existing?->terms_document_path,
        ];

        if ($file = $request->file('terms_document')) {
            $attributes['terms_document_path'] = $this->upload($file, $propertyId);

            if ($existing?->terms_document_path) {
                Storage::disk('public')->delete($existing->terms_document_path);
            }
        }

        return $attributes;
    }

    /** Checkbox selections plus anything typed into "other amenities". */
    private function amenities(array $data): ?array
    {
        $extra = collect(explode(',', (string) ($data['amenities_extra'] ?? '')))
            ->map(fn ($item) => trim($item))
            ->filter();

        $all = collect($data['amenities'] ?? [])->concat($extra)->unique()->values()->all();

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

        foreach (self::MEDIA_FILES as $key => $kind) {
            if ($file = $request->file($key)) {
                $rows[] = ['kind' => $kind, 'path' => $this->upload($file, $propertyId), 'sort_order' => 0];
            }
        }

        foreach (self::MEDIA_LINKS as $key => $kind) {
            if (filled($data[$key] ?? null)) {
                $rows[] = ['kind' => $kind, 'url' => $data[$key], 'sort_order' => 0];
            }
        }

        foreach ($rows as $row) {
            PropertyMedia::create($row + ['property_id' => $propertyId]);
        }
    }

    /** Files are grouped by property so a deleted project's assets are easy to reap. */
    private function upload(UploadedFile $file, int $propertyId): string
    {
        return $file->store("properties/{$propertyId}", 'public');
    }
}
