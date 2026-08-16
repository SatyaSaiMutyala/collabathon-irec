@php
    /**
     * The nine-step project intake form, shared by create and edit.
     *
     * Steps mirror the section headers on the client's field sheet 1:1, so a row on that
     * sheet can be traced to exactly one step here.
     *
     * All steps stay in the DOM (x-show, never x-if) — the wizard is one form and one POST,
     * so hiding a step must not detach its inputs.
     *
     * On edit, `$formRecord` (a flat field-name => value map from PropertyController) is what
     * repopulates every input; the field components read it themselves, so nothing here has
     * to wire :value per field. `$property` is null on create.
     */
    $property ??= null;
    $isEdit = (bool) $property;
    $detail = $property?->detail;

    /**
     * Blade components see their props plus globally shared data — never the including
     * view's variables — so passing $formRecord down the include chain would leave every
     * x-field blank on edit. Sharing is what puts it in reach of the components, and doing
     * it here (rather than in the controller) keeps the mechanism next to the template that
     * depends on it. Explicitly shared as null on create so nothing can leak in.
     */
    \Illuminate\Support\Facades\View::share('formRecord', $formRecord ?? null);
    /*
     * Seven steps, not nine. "Timeline & legal" and "Compliance & trust signals" were
     * dropped — the columns behind them stay on the model and in the API so existing
     * records keep what they hold, but nothing collects them any more. possession_date
     * was the one field in those two steps that is still wanted; it already lived in
     * step 1 under the project type that reveals it, so it was unaffected.
     */
    $steps = [
        1 => ['label' => 'Project basics',   'icon' => 'building',    'hint' => 'Identity, RERA and the owning developer'],
        2 => ['label' => 'Location',         'icon' => 'map-pin',     'hint' => 'Address, zone and connectivity'],
        3 => ['label' => 'Configuration',    'icon' => 'list',        'hint' => 'Unit types, areas and pricing'],
        4 => ['label' => 'Specifications',   'icon' => 'sparkles',    'hint' => 'Land, build quality and amenities'],
        5 => ['label' => 'Master plan and gallery', 'icon' => 'palette', 'hint' => 'Gallery, plans and brochures'],
        6 => ['label' => 'Commercial terms', 'icon' => 'chart',       'hint' => 'Payment plans, charges and CP payout'],
        7 => ['label' => 'Contact & sales',  'icon' => 'phone',       'hint' => 'Sales office and booking process'],
    ];

    // A failed submit lands back here — open the first step that actually has an error
    // rather than step 1, so the message is not hidden behind the rail.
    $stepFields = [
        // possession_date sits here, not with the other dates in step 5: it is revealed by
        // project_type, and a conditional field the type control cannot show is invisible.
        1 => ['name', 'developer_id', 'project_type', 'possession_date', 'project_status',
              'tagline', 'description',
              'logo', 'rera_number', 'listing_status'],
        2 => ['country', 'state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
              'latitude', 'longitude', 'maps_link', 'connectivity_highlights', 'nearby_infrastructure'],
        3 => ['price_min', 'price_max', 'extent_metric', 'currency', 'total_units', 'towers',
              'floors_per_tower', 'land_parcel_acres', 'total_project_area_sqft', 'unit_types', 'unit_plans'],
        4 => ['amenities', 'green_certification', 'vastu_compliant'],
        5 => ['cover_image', 'gallery', 'site_layout', 'master_plan', 'brochure', 'price_list',
              'video_url', 'virtual_tour_url', 'payment_schedule_file'],
        6 => ['cp_commission_percent', 'fos_commission_percent', 'terms_title', 'terms_document'],
        7 => ['sales_office_address', 'site_visit_timings', 'sales_contact_name', 'sales_contact_number', 'booking_process'],
    ];

    $initialStep = 1;
    foreach ($stepFields as $number => $keys) {
        if ($errors->hasAny($keys)) {
            $initialStep = $number;
            break;
        }
    }

    /**
     * Seeds the repeatable unit-type rows: what was typed on a failed submit, else what is
     * saved, else one blank row. `existing_floor_plan` rides along so a row that keeps its
     * plan does not have to re-upload it — the rows are rebuilt from scratch on every save.
     */
    $unitTypeRows = old('unit_types') ?: ($property
        ? $property->unitTypes->map(fn ($u) => [
            'label' => $u->label,
            'carpet_area_sqft' => $u->carpet_area_sqft,
            'built_up_area_sqft' => $u->built_up_area_sqft,
            'super_built_up_area_sqft' => $u->super_built_up_area_sqft,
            'price_min' => $u->price_min,
            'price_max' => $u->price_max,
            'units_count' => $u->units_count,
            'existing_floor_plan' => $u->floor_plan_path,
        ])->all()
        : []);

    $unitTypeRows = $unitTypeRows ?: [['label' => '']];

    // Attachments already on record, so the media step can show and offer to remove them.
    $mediaByKind = $property ? $property->media->groupBy('kind') : collect();
    $firstMedia = fn (string $kind) => $mediaByKind->get($kind)?->first();

    /**
     * The browser needs the server's real upload ceilings so it can say "this file is too
     * big" while the form is still on screen. Exceeding post_max_size aborts the request
     * before PHP runs, so there is no way to recover the typed fields after the fact —
     * catching it client-side is the only place the data survives.
     */
    $toBytes = static function (string $size): int {
        $number = (int) $size;
        return match (strtolower(substr(trim($size), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    };

    // 0 means unlimited in php.ini; PHP_INT_MAX makes the client-side check a no-op.
    $postMax = $toBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX;
    $uploadMax = $toBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX;

    // Headroom for the text fields and multipart boundaries riding along with the files.
    $postMaxBytes = max(0, $postMax - 512 * 1024);
    $uploadMaxBytes = $uploadMax;
@endphp

    {{-- `novalidate` is deliberate. Steps are hidden with x-show, and the browser refuses to
         report a constraint violation on a control it cannot focus ("An invalid form control
         with name='x' is not focusable") — it just blocks the submit with no visible reason.
         So we own the check: reveal the offending step first, then report on it there.

         The save goes out through submit(), not requestSubmit(). requestSubmit() re-fires the
         submit event, which re-enters this handler, and it returns silently while the form's
         "firing submission events" flag is still set — and a promise callback runs in a
         microtask inside exactly that window. That is why saving with no images attached did
         nothing at all: compression resolved immediately, the replay was swallowed, and the
         buttons stayed disabled. submit() fires no event and skips validation, which is right
         here because validation is already done above. --}}
    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $isEdit ? route('admin.properties.update', $property) : route('admin.properties.store') }}"
          x-data="{
              step: {{ $initialStep }},
              last: {{ count($steps) }},
              rows: @js($unitTypeRows),
              busy: false,
              saving: false,
              uploadError: '',
              maxPost: {{ $postMaxBytes }},
              maxFile: {{ $uploadMaxBytes }},

              /* Settings → Project types drives which types ask for a RERA possession
                 date, so the rule lives in the data rather than being hard-coded here.
                 Read as a plain method, not a getter: an x-data getter is reached
                 through Alpine's merged scope proxy, which invokes it without the
                 reactive receiver, so `this.projectType` inside it is untracked and
                 x-show never re-evaluates when the type changes. */
              projectType: @js($selectedProjectType),
              possessionByType: @js($possessionByType),

              needsPossessionDate() { return this.possessionByType[this.projectType] === true },

              go(n) { this.step = Math.min(Math.max(n, 1), this.last); window.scrollTo({ top: 0, behavior: 'smooth' }) },

              /** Reveal the step holding the first invalid control, then report on it there. */
              reportInvalid() {
                  const invalid = this.$el.querySelector(':invalid');
                  if (! invalid) return;

                  const section = invalid.closest('[data-step]');
                  if (section) this.go(Number(section.dataset.step));

                  // Wait for x-show to un-hide the step — focus() is a no-op while display:none.
                  this.$nextTick(() => { invalid.focus(); invalid.reportValidity() });
              },

              async submit(event) {
                  event.preventDefault();

                  const form = this.$el;

                  if (! form.checkValidity()) { this.reportInvalid(); return }

                  this.busy = true;
                  this.uploadError = '';

                  let total = 0;
                  try {
                      // Optional call: a missing bundle must not stop the save.
                      ({ after: total = 0 } = await window.compressFileInputs?.(form) ?? {});
                  } catch (error) {
                      console.error('Image compression failed; uploading originals.', error);
                  }
                  this.busy = false;

                  // Even compressed, PDFs can outrun the server's limits. Saying which file and
                  // by how much beats a raw 413 that discards every field on the way out.
                  const mb = (bytes) => (bytes / 1048576).toFixed(1) + ' MB';

                  const oversized = [...form.querySelectorAll('input[type=\'file\']')]
                      .flatMap((input) => Array.from(input.files ?? []))
                      .find((file) => file.size > this.maxFile);

                  if (oversized) {
                      this.uploadError = `“${oversized.name}” is ${mb(oversized.size)}. `
                          + `The server accepts files up to ${mb(this.maxFile)} each.`;
                      return;
                  }

                  if (total > this.maxPost) {
                      this.uploadError = `These attachments total ${mb(total)}, over the `
                          + `${mb(this.maxPost)} the server accepts in one submit. `
                          + `Save as draft with fewer files, then add the rest.`;
                      return;
                  }

                  this.saving = true;
                  // form.submit() fires no submit event, so the layout's page skeleton
                  // has to be asked for directly.
                  window.dispatchEvent(new CustomEvent('navigate-start'));
                  form.submit();

                  // Safety net: if the navigation is blocked for any reason, hand the buttons
                  // back rather than leaving the admin staring at a dead form.
                  setTimeout(() => { this.busy = false; this.saving = false }, 4000);
              },
          }"
          x-on:submit="submit($event)">
        @csrf
        @if($isEdit) @method('PATCH') @endif

        {{-- Tells update() this is the full form, not the row menu's one-field status action,
             so it is safe to rebuild the detail row, unit types and attachments. --}}
        <input type="hidden" name="_full" value="1">

        {{-- The publish choice is a field rather than the submit button's name/value, because
             submit() ignores the submitter. Editing keeps the listing's current state unless
             a button changes it. --}}
        <input type="hidden" name="listing_status" x-ref="publish"
               value="{{ old('listing_status', $property->listing_status ?? 'draft') }}">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            {{-- Step rail ------------------------------------------------------------- --}}
            <nav aria-label="Form steps" class="lg:col-span-3 lg:sticky lg:top-5">
                <x-panel flush>
                    <ol class="p-2 flex lg:flex-col gap-1 overflow-x-auto">
                        @foreach($steps as $number => $step)
                            <li class="shrink-0 lg:shrink">
                                <button type="button" @click="go({{ $number }})"
                                        :class="step === {{ $number }}
                                            ? 'bg-canvas text-ink'
                                            : 'text-ink-2 hover:bg-canvas hover:text-ink'"
                                        class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-left transition-colors">
                                    <span :class="step === {{ $number }} ? 'bg-primary text-white border-primary' : 'text-ink-3 border-line'"
                                          class="grid place-items-center w-6 h-6 rounded-md border text-[11px] font-semibold nums shrink-0 transition-colors">
                                        {{ $number }}
                                    </span>
                                    <span class="min-w-0 hidden lg:block">
                                        <span class="block text-[12.5px] font-medium leading-tight">{{ $step['label'] }}</span>
                                        <span class="block text-[11px] text-ink-3 truncate mt-0.5">{{ $step['hint'] }}</span>
                                    </span>
                                    <span class="lg:hidden text-[12.5px] font-medium whitespace-nowrap">{{ $step['label'] }}</span>
                                    @if($errors->hasAny($stepFields[$number]))
                                        <x-icon name="x" class="w-3.5 h-3.5 text-danger shrink-0 ml-auto" />
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </x-panel>
            </nav>

            {{-- Step content ---------------------------------------------------------- --}}
            <div class="lg:col-span-9 min-w-0">
                <x-panel padded>

                    {{-- 1 · Project Basic Info ------------------------------------------ --}}
                    <section x-show="step === 1" data-step="1" x-cloak class="space-y-4">
                        <x-wizard-heading :step="1" :of="count($steps)" title="Project basic info"
                                          subtitle="Who owns the project, what it is, and its RERA registration." />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Project name" name="name" placeholder="e.g. Azure Bay Residences" required />

                            {{-- A property always belongs to exactly one developer. Options are
                                 passed as an id-keyed map so the component resolves the current
                                 selection itself, on edit as well as after a failed submit.
                                 Searchable — the roster only grows, and scanning a plain <select>
                                 stops working well past a couple dozen developers. --}}
                            <x-combobox label="Developer / builder" name="developer_id" required
                                        :options="$developers->pluck('company_name', 'id')"
                                        placeholder="Search developers…"
                                        hint="Leads from this listing route to this developer." />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Options come from Settings → Project types. `x-model` feeds
                                 the conditional possession-date field in step 5. --}}
                            <x-select-field label="Project type" name="project_type"
                                            x-model="projectType"
                                            :options="$projectTypes->pluck('name', 'name')" />
                            <x-select-field label="Project status" name="project_status"
                                            :options="['New Launch', 'Under Construction', 'Ready to Move', 'Nearing Completion']" />
                        </div>

                        {{-- Directly under the control that reveals it. It is a date, so it
                             would sit naturally in step 5 with launch date — but a conditional
                             field five steps from its trigger reads as the trigger doing
                             nothing. Proximity wins over grouping here. --}}
                        {{-- x-bind:class, not x-show. x-show's effect on this element only ran
                             on init and never re-evaluated when projectType changed, so the
                             field stayed visible for every type; x-bind on the same expression
                             updates correctly, so visibility rides on that instead. --}}
                        <div data-possession-field
                             x-bind:class="needsPossessionDate() ? 'grid' : 'hidden'"
                             class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Possession date (as per RERA)" name="possession_date" type="date"
                                     x-bind:required="needsPossessionDate()"
                                     hint="Committed handover date. Required for this project type." />
                        </div>

                        <x-field label="Title" name="tagline" placeholder="Short marketing line" />

                        {{-- Paired on one row: both are short, single-value identity fields, and
                             stacking them left a full-width text input above a full-width
                             dashed dropzone with the description wedged between them. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="RERA registration number" name="rera_number" placeholder="e.g. RERA-DXB-24817" />
                            <x-file-field label="Project logo / branding" name="logo" accept="image/*" :current="$property?->logo_path"
                                          hint="PNG or JPG, up to 2 MB." />
                        </div>

                        <x-field label="Project description" name="description" type="textarea" rows="4"
                                 placeholder="Detailed overview of the project" />
                    </section>

                    {{-- 2 · Location Details -------------------------------------------- --}}
                    <section x-show="step === 2" data-step="2" x-cloak class="space-y-4">
                        <x-wizard-heading :step="2" :of="count($steps)" title="Location details"
                                          subtitle="Where the project sits, and what it is close to." />

                        {{-- Country → state → city, driven by the list an admin maintains in
                             Settings → Locations. Free text let three spellings of one city
                             into the data, which then split every city filter in the app.

                             Each level narrows the next, and changing a parent clears the
                             children rather than leaving a city that no longer belongs to the
                             selected state. On a new project the seeded default is preselected;
                             on an edit the saved triple wins, and PropertyController::locationTree()
                             has already folded it in even if Settings no longer lists it. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3"
                             x-data="locationPicker(
                                 @js($locationTree ?? []),
                                 @js(old('country', data_get($formRecord ?? null, 'country') ?: 'India')),
                                 @js(old('state', data_get($formRecord ?? null, 'state') ?: 'Telangana')),
                                 @js(old('city', data_get($formRecord ?? null, 'city') ?: 'Hyderabad'))
                             )">
                            <div>
                                <label for="country" class="block text-[12.5px] font-medium text-ink mb-1.5">Country</label>
                                <select id="country" name="country" x-ref="countrySel" x-model="country" x-on:change="onCountry()"
                                        class="w-full h-10 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                               focus:border-primary-ring focus:outline-none transition-colors">
                                    <template x-for="name in countries" :key="name">
                                        <option :value="name" x-text="name"></option>
                                    </template>
                                </select>
                                @error('country')<p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="state" class="block text-[12.5px] font-medium text-ink mb-1.5">State</label>
                                <select id="state" name="state" x-ref="stateSel" x-model="state" x-on:change="onState()"
                                        class="w-full h-10 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                               focus:border-primary-ring focus:outline-none transition-colors">
                                    <template x-for="name in states" :key="name">
                                        <option :value="name" x-text="name"></option>
                                    </template>
                                </select>
                                @error('state')<p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="city" class="flex items-center gap-1 text-[12.5px] font-medium text-ink mb-1.5">
                                    City <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <select id="city" name="city" x-ref="citySel" x-model="city" required
                                        class="w-full h-10 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                               focus:border-primary-ring focus:outline-none transition-colors">
                                    <template x-for="name in cities" :key="name">
                                        <option :value="name" x-text="name"></option>
                                    </template>
                                </select>
                                @error('city')<p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <x-field label="Locality / area" name="locality" placeholder="e.g. Gachibowli" />

                        <x-field label="Full address" name="full_address" type="textarea"
                                 placeholder="Plot, street, community, city" />

                        <x-location-finder :latitude="data_get($formRecord ?? null, 'latitude')"
                                            :longitude="data_get($formRecord ?? null, 'longitude')">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <x-field label="Landmark / reference point" name="landmark" placeholder="e.g. opposite Marina Mall" />
                                <x-field label="Pincode" name="pincode" placeholder="e.g. 00000" x-ref="pincode" />
                                <x-select-field label="Zone" name="zone" :options="['East', 'West', 'North', 'South', 'Central']">
                                    <option value="">Not set</option>
                                </x-select-field>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                                {{-- step="any": a fixed 7-dp step makes a pasted coordinate with more
                                     precision a step-mismatch, which is a needless way to fail. The
                                     column is decimal(10,7) and the server range-checks it. --}}
                                <x-field label="Latitude" name="latitude" type="number" step="any" placeholder="25.0762" x-ref="latitude" />
                                <x-field label="Longitude" name="longitude" type="number" step="any" placeholder="55.1390" x-ref="longitude" />
                                <x-field label="Google Maps link" name="maps_link" type="url" placeholder="https://maps.app.goo.gl/…" x-ref="mapsLink" />
                            </div>

                            <button type="button" @click="openMap()"
                                    class="inline-flex items-center gap-1.5 text-[12px] text-primary-dark hover:underline mt-2">
                                <x-icon name="map-pin" class="w-3.5 h-3.5" />
                                Fetch location from map
                            </button>
                        </x-location-finder>

                        <div x-data="nearbyPlacesFinder({ endpoint: @js(route('admin.nearby-places')) })">
                            <div class="flex items-center justify-between gap-3 mb-1.5">
                                <p class="text-[12.5px] text-ink-3">
                                    Or fetch a first draft from the project's latitude/longitude above.
                                </p>
                                <button type="button" @click="fetchNearby()" :disabled="busy"
                                        class="inline-flex items-center gap-1.5 text-[12px] text-primary-dark hover:underline shrink-0 disabled:opacity-50">
                                    <x-icon name="map-pin" class="w-3.5 h-3.5" />
                                    <span x-text="busy ? 'Fetching…' : 'Fetch nearby places'"></span>
                                </button>
                            </div>
                            <p x-show="error" x-cloak x-text="error" class="text-[11.5px] text-danger mb-1.5"></p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <x-field label="Connectivity highlights" name="connectivity_highlights" type="textarea" rows="4"
                                         placeholder="Metro station — 800 m&#10;Airport — 22 km&#10;Highway access — 5 min"
                                         hint="One per line." x-ref="connectivity" />
                                <x-field label="Nearby social infrastructure" name="nearby_infrastructure" type="textarea" rows="4"
                                         placeholder="GEMS School — 1.2 km&#10;Mediclinic — 2 km&#10;Marina Mall — 900 m"
                                         hint="One per line — schools, hospitals, malls, IT parks." x-ref="social" />
                            </div>
                        </div>
                    </section>

                    {{-- 3 · Configuration & Pricing ------------------------------------- --}}
                    <section x-show="step === 3" data-step="3" x-cloak class="space-y-4">
                        <x-wizard-heading :step="3" :of="count($steps)" title="Configuration & pricing"
                                          subtitle="Project-wide price band, then one row per unit type." />

                        {{-- One price, not a band — at the project level and in every unit-type
                             row below. The upper end was never a number anyone could state
                             honestly at intake, so only the entry price is asked for. Both
                             `price_max` columns stay on their models and are simply left null;
                             every reader of them already had to handle that, since they have
                             always been nullable. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- INR only for now. The column, the validation rule and this
                                 list are the three places a new currency has to be added;
                                 a single option also means it is always pre-selected. --}}
                            <x-select-field label="Currency" name="currency" :options="['INR']" />
                            <x-field label="Starting from" name="price_min" type="number" placeholder="1800000" required />
                            {{-- Options come from Settings → Measurement units, plus whatever
                                 this project already has, so a unit retired there does not
                                 vanish from a project still using it. --}}
                            <x-select-field label="Project extent metric" name="extent_metric"
                                            :options="$extentMetricOptions"
                                            placeholder="Select a unit…" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="Total units" name="total_units" type="number" placeholder="480" />
                            <x-field label="Towers / blocks" name="towers" type="number" placeholder="3" />
                            <x-field label="No. of floors" name="floors_per_tower" type="number" placeholder="24" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Land parcel size" name="land_parcel_acres" type="number" step="0.01"
                                     placeholder="12.50" hint="In acres." />
                            <x-field label="Total project area" name="total_project_area_sqft" type="number"
                                     placeholder="544500" hint="In sq.ft." />
                        </div>

                        {{-- Repeatable unit types ------------------------------------- --}}
                        <div class="border-t border-line-soft pt-4">
                            <div class="mb-2.5">
                                <p class="text-[12.5px] font-medium text-ink">Unit types available</p>
                                <p class="text-[11.5px] text-ink-3 mt-0.5">
                                    One row per configuration on offer.
                                </p>
                            </div>

                            <div class="space-y-2.5">
                                <template x-for="(row, i) in rows" :key="i">
                                    <div class="rounded-lg border border-line bg-canvas p-3 space-y-2.5">
                                        {{-- Carries a saved floor plan through the rebuild. --}}
                                        <input type="hidden" :name="`unit_types[${i}][existing_floor_plan]`"
                                               :value="rows[i].existing_floor_plan ?? ''">

                                        {{-- The three area figures and the upper price are no longer asked for,
                                             but an edit deletes every unit-type row and recreates it from this
                                             payload — so dropping the inputs outright would silently blank these
                                             columns on the next save of a project that already has them. They
                                             ride through hidden instead: new rows leave them empty, existing rows
                                             keep whatever is on record. Same reason `existing_floor_plan` above
                                             is here. --}}
                                        <template x-for="carried in ['carpet_area_sqft', 'built_up_area_sqft', 'super_built_up_area_sqft', 'price_max']"
                                                  :key="carried">
                                            <input type="hidden" :name="`unit_types[${i}][${carried}]`"
                                                   :value="rows[i][carried] ?? ''">
                                        </template>

                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-[11.5px] font-medium text-ink-3 nums"
                                                  x-text="`Unit type ${i + 1}`"></span>
                                            <button type="button" x-show="rows.length > 1"
                                                    x-on:click="rows.splice(i, 1)"
                                                    class="text-ink-3 hover:text-danger rounded-md p-1 -m-1 transition-colors"
                                                    :aria-label="`Remove unit type ${i + 1}`">
                                                <x-icon name="x" class="w-3.5 h-3.5" />
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Unit type</span>
                                                <select :name="`unit_types[${i}][label]`" x-model="rows[i].label"
                                                        class="w-full h-9 pl-3 pr-8 rounded-lg bg-panel border border-line text-[13px] text-ink appearance-none focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                                    <option value="">Select…</option>
                                                    {{-- From Settings → Unit types, plus any label already saved on
                                                         this project so a type switched off there does not vanish
                                                         from a row that uses it. --}}
                                                    @foreach($unitTypeOptions as $label)
                                                        <option value="{{ $label }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Starting from</span>
                                                <input type="number" :name="`unit_types[${i}][price_min]`"
                                                       x-model="rows[i].price_min" placeholder="1800000"
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">No. of units</span>
                                                <input type="number" :name="`unit_types[${i}][units_count]`"
                                                       x-model="rows[i].units_count" placeholder="120"
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Upload floor plan</span>
                                                <input type="file" :name="`unit_types[${i}][floor_plan]`"
                                                       accept="image/*,application/pdf"
                                                       class="w-full h-9 text-[11.5px] text-ink-2 file:mr-2 file:h-9 file:px-2.5 file:rounded-lg file:border-0 file:bg-canvas file:text-[11.5px] file:text-ink-2 file:cursor-pointer">
                                            </label>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Under the rows, not in the header: the button sits where the
                                 row it creates will appear, so adding one never scrolls the
                                 new row out of view on a list that is already several long.
                                 Right-aligned, matching the wizard's other forward actions. --}}
                            <div class="flex justify-end mt-2.5">
                                <x-button variant="outline" size="sm" tag="button" type="button" icon="plus"
                                          x-on:click="rows.push({})">Add unit type</x-button>
                            </div>

                            @error('unit_types')
                                <p class="text-[11.5px] text-danger mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-file-field label="Unit plan / layout images" name="unit_plans[]" multiple accept="image/*"
                                      hint="Project-wide layout images, separate from each unit type's floor plan." />
                    </section>

                    {{-- 4 · Project Specifications -------------------------------------- --}}
                    <section x-show="step === 4" data-step="4" x-cloak class="space-y-4">
                        <x-wizard-heading :step="4" :of="count($steps)" title="Project specifications"
                                          subtitle="Build quality and the amenity set." />

                        <div class="space-y-3">
                            <x-checkbox-group label="Amenities" name="amenities" :options="$amenityOptions" :columns="3" />

                        </div>

                        <div class="border-t border-line-soft pt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                            <x-field label="Green building certification" name="green_certification"
                                     placeholder="e.g. IGBC Gold / LEED Platinum" hint="Leave blank if none." />
                            <x-switch-field label="Vastu compliant" name="vastu_compliant" class="pb-2" />
                        </div>
                    </section>

                    {{-- 5 · Media & Marketing Assets ------------------------------------ --}}
                    <section x-show="step === 5" data-step="5" x-cloak class="space-y-4">
                        <x-wizard-heading :step="5" :of="count($steps)" title="Master plan and gallery"
                                          subtitle="Everything channel partners see and share." />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-file-field label="Cover image" name="cover_image" accept="image/*" :current="$property?->cover_image_path"
                                          hint="The hero image on listing cards." />
                            <x-file-field label="Site layout plan" name="site_layout" accept="image/*,application/pdf" :current="$firstMedia('site_layout')?->path" />
                        </div>

                        <x-file-field label="Project images" name="gallery[]" multiple accept="image/*"
                                      :hint="$isEdit
                                          ? 'Anything chosen here is added to the gallery — existing images stay.'
                                          : 'Exterior, interior and amenity shots — select several at once.'" />

                        {{-- Existing gallery, with per-image removal. Ticking the box posts the
                             media id in remove_media[]; nothing is deleted until the form saves. --}}
                        @if($isEdit && $mediaByKind->get('image')?->isNotEmpty())
                            <div>
                                <p class="text-[12.5px] font-medium text-ink mb-2">
                                    In the gallery
                                    <span class="text-ink-3 font-normal">({{ $mediaByKind['image']->count() }})</span>
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-2.5">
                                    @foreach($mediaByKind['image'] as $image)
                                        <label class="group relative block cursor-pointer">
                                            <input type="checkbox" name="remove_media[]" value="{{ $image->id }}"
                                                   class="peer sr-only">
                                            <img src="{{ $image->url ?: asset('storage/' . $image->path) }}" alt=""
                                                 class="w-full aspect-[4/3] object-cover rounded-lg border border-line
                                                        transition-opacity peer-checked:opacity-30">
                                            <span class="absolute inset-x-1 bottom-1 flex items-center justify-center gap-1
                                                         rounded-md bg-panel/90 py-1 text-[10.5px] font-medium text-ink-2
                                                         opacity-0 group-hover:opacity-100 peer-checked:opacity-100
                                                         peer-checked:text-danger transition-opacity">
                                                <span class="peer-checked:hidden">Remove</span>
                                            </span>
                                            <span class="absolute top-1.5 right-1.5 hidden peer-checked:grid place-items-center
                                                         w-5 h-5 bg-danger text-white">
                                                <x-icon name="x" class="w-3 h-3" />
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-[11.5px] text-ink-3 mt-2">Tick an image to remove it when you save.</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-file-field label="Master plan" name="master_plan" accept="image/*,application/pdf" hint="Image or PDF." :current="$firstMedia('master_plan')?->path" />
                            <x-file-field label="Brochure" name="brochure" accept="application/pdf" hint="PDF." :current="$firstMedia('brochure')?->path" />
                            <x-file-field label="Price list" name="price_list" accept="application/pdf" hint="PDF." :current="$firstMedia('price_list')?->path" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Project video" name="video_url" type="url"
                                     placeholder="https://youtube.com/…" icon="external" />
                            <x-field label="Walkthrough link" name="virtual_tour_url" type="url"
                                     placeholder="https://my.matterport.com/…" icon="external" />
                        </div>

                        <div class="border-t border-line-soft pt-4">
                            <x-file-field label="Payment schedule" name="payment_schedule_file" accept="application/pdf" hint="PDF." :current="$firstMedia('payment_schedule')?->path" />
                        </div>
                    </section>

                    {{-- 6 · Commercial Terms -------------------------------------------- --}}
                    <section x-show="step === 6" data-step="6" x-cloak class="space-y-4">
                        <x-wizard-heading :step="6" :of="count($steps)" title="Commercial terms"
                                          subtitle="What the channel partner earns, and the terms they work under." />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="CP commission %" name="cp_commission_percent" type="number" step="0.01"
                                     placeholder="2.50" hint="Overrides the developer default for this project." />
                            <x-field label="FOS commission %" name="fos_commission_percent" type="number" step="0.01"
                                     placeholder="1.00" hint="Payout for feet-on-street field sales agents." />
                        </div>

                        {{-- Developer terms ------------------------------------------------
                             One artefact per project, shown to the developer and to every
                             broker in the app. No type toggle any more — the form only ever
                             offers a document, so the server derives `terms_type` from
                             whether one is actually on file rather than the client stating
                             it (see PropertyController::termsAttributes). --}}
                        <div class="border-t border-line-soft pt-4">
                            <div class="flex items-baseline justify-between gap-3 mb-1">
                                <h3 class="text-[13.5px] font-medium text-ink">Developer terms</h3>
                                <span class="text-[11.5px] text-ink-3">Visible to the developer and to channel partners</span>
                            </div>
                            <p class="text-[12px] text-ink-3 mb-3 max-w-[68ch] leading-relaxed">
                                The terms document for this project. Attach the signed copy —
                                channel partners can read it in the app and download it.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <x-field label="Title shown in the app" name="terms_title"
                                         placeholder="e.g. Channel Partner Agreement"
                                         hint="Leave blank to use “Developer terms”." />
                                <x-file-field label="Terms document" name="terms_document"
                                              accept=".pdf,.doc,.docx"
                                              :current="data_get($formRecord ?? null, 'terms_document_path')"
                                              hint="PDF, DOC or DOCX, up to 20 MB." />
                            </div>
                        </div>
                    </section>

                    {{-- 7 · Contact & Sales Info ---------------------------------------- --}}
                    <section x-show="step === 7" data-step="7" x-cloak class="space-y-4">
                        <x-wizard-heading :step="7" :of="count($steps)" title="Contact & sales info"
                                          subtitle="Where channel partners take clients, and who they ask for." />

                        <x-field label="Sales office address" name="sales_office_address" type="textarea"
                                 placeholder="Building, street, community, city" />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="Site visit timings" name="site_visit_timings"
                                     placeholder="e.g. Mon–Sat, 10:00–19:00" icon="clock" />
                            <x-field label="Sales contact name" name="sales_contact_name" placeholder="Full name" />
                            <x-field label="Sales contact number" name="sales_contact_number"
                                     placeholder="+971 5X XXX XXXX" icon="phone" />
                        </div>

                        <x-field label="Booking process / documents checklist" name="booking_process" type="textarea" rows="4"
                                 placeholder="Steps to book, and the documents a client must bring" />
                    </section>

                    {{-- Footer: step nav + submit --------------------------------------- --}}
                    <x-slot:footer>
                        <div x-show="uploadError" x-cloak
                             class="flex items-start gap-2.5 rounded-lg bg-danger-soft ring-1 ring-inset ring-danger-ring px-3 py-2.5 mb-3">
                            <x-icon name="x" class="w-4 h-4 text-danger shrink-0 mt-px" />
                            <p class="text-[12px] text-ink-2 leading-relaxed" x-text="uploadError"></p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <x-button variant="outline" size="sm" tag="button" type="button" icon="chevron-left"
                                          x-on:click="go(step - 1)" x-bind:disabled="step === 1">Back</x-button>
                                <x-button variant="outline" size="sm" tag="button" type="button" icon-right="chevron-right"
                                          x-on:click="go(step + 1)" x-show="step < last">Next</x-button>
                                <p class="text-[11.5px] text-ink-3 nums ml-1" x-show="! busy">
                                    Step <span x-text="step"></span> of {{ count($steps) }}
                                </p>
                                <p class="text-[11.5px] text-ink-2 ml-1" x-show="busy" x-cloak>
                                    Optimising images…
                                </p>
                            </div>

                            {{-- The publish choice is a hidden field, not the submit button's own
                                 name/value: submit() ignores the submitter, so a button-borne
                                 value would silently vanish. On create the default is draft,
                                 which is also what Enter-to-submit gets; on edit the listing
                                 keeps whatever state it already had unless a button changes it. --}}
                            <div class="flex items-center gap-2">
                                @if($isEdit)
                                    <x-button variant="outline" size="sm" tag="a"
                                              href="{{ route('admin.properties.show', $property) }}">Cancel</x-button>
                                    @if($property->listing_status !== 'active')
                                        <x-button variant="subtle" size="sm" tag="button" type="submit"
                                                  x-on:click="$refs.publish.value = 'active'"
                                                  x-bind:disabled="busy || saving">Save &amp; publish</x-button>
                                    @endif
                                    <x-button variant="gold" size="sm" tag="button" type="submit" icon="check"
                                              x-bind:disabled="busy || saving">Save changes</x-button>
                                @else
                                    <x-button variant="subtle" size="sm" tag="button" type="submit"
                                              x-on:click="$refs.publish.value = 'draft'"
                                              x-bind:disabled="busy || saving">Save as draft</x-button>
                                    <x-button variant="gold" size="sm" tag="button" type="submit" icon="check"
                                              x-on:click="$refs.publish.value = 'active'"
                                              x-bind:disabled="busy || saving">Save &amp; publish</x-button>
                                @endif
                            </div>
                        </div>
                    </x-slot:footer>
                </x-panel>
            </div>
        </div>
    </form>

@push('scripts')
<script>
    /**
     * The country → state → city cascade on step 2.
     *
     * `tree` is the whole list, shipped with the page: it is a few hundred rows that every
     * admin sees, so fetching it per level would be three round trips for data that fits
     * in the HTML.
     *
     * Selections are validated against the tree on every change rather than trusted, so a
     * country switch can never leave a state from the previous one still selected — which
     * is how a project ends up saved as "India / Dubai".
     */
    function locationPicker(tree, initialCountry, initialState, initialCity) {
        return {
            tree,
            country: '',
            state: '',
            city: '',

            init() {
                this.country = this.tree[initialCountry] ? initialCountry : (this.countries[0] ?? '');
                this.state = this.statesOf(this.country)[initialState] !== undefined
                    ? initialState
                    : (this.states[0] ?? '');
                this.city = this.cities.includes(initialCity) ? initialCity : (this.cities[0] ?? '');
                this.syncSelects();
            },

            /**
             * Pushes the chosen values back onto the <select> elements after Alpine has
             * rendered their <option>s.
             *
             * x-model writes the value immediately, but the options come from x-for and do
             * not exist yet on that pass — assigning a value a <select> has no option for
             * makes the browser silently reset it to the first one. That is why a project
             * saved as "United Arab Emirates / UAE / Dubai" opened showing India, and why a
             * new project showed Andhra Pradesh while holding Telangana's cities.
             *
             * $nextTick runs after the x-for pass, so by then there is an option to select.
             */
            syncSelects() {
                this.$nextTick(() => {
                    if (this.$refs.countrySel) this.$refs.countrySel.value = this.country;
                    if (this.$refs.stateSel) this.$refs.stateSel.value = this.state;
                    if (this.$refs.citySel) this.$refs.citySel.value = this.city;
                });
            },

            get countries() {
                return Object.keys(this.tree);
            },

            statesOf(country) {
                return this.tree[country] ?? {};
            },

            get states() {
                return Object.keys(this.statesOf(this.country));
            },

            get cities() {
                return this.statesOf(this.country)[this.state] ?? [];
            },

            // Falling back to the first option, never to blank: city is required, and an
            // empty select would block the submit with no visible reason on a hidden step.
            onCountry() {
                this.state = this.states[0] ?? '';
                this.city = this.cities[0] ?? '';
                // Same reason as init(): changing country re-renders both dependent
                // option lists, so their values have to be re-applied afterwards.
                this.syncSelects();
            },

            onState() {
                this.city = this.cities[0] ?? '';
                this.syncSelects();
            },
        };
    }
</script>
@endpush
