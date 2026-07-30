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
    $steps = [
        1 => ['label' => 'Project basics',   'icon' => 'building',    'hint' => 'Identity, RERA and the owning developer'],
        2 => ['label' => 'Location',         'icon' => 'map-pin',     'hint' => 'Address, zone and connectivity'],
        3 => ['label' => 'Configuration',    'icon' => 'list',        'hint' => 'Unit types, areas and pricing'],
        4 => ['label' => 'Specifications',   'icon' => 'sparkles',    'hint' => 'Land, build quality and amenities'],
        5 => ['label' => 'Timeline & legal', 'icon' => 'clock',       'hint' => 'Dates, approvals and bank tie-ups'],
        6 => ['label' => 'Media',            'icon' => 'palette',     'hint' => 'Gallery, plans and brochures'],
        7 => ['label' => 'Commercial terms', 'icon' => 'chart',       'hint' => 'Payment plans, charges and CP payout'],
        8 => ['label' => 'Contact & sales',  'icon' => 'phone',       'hint' => 'Sales office and booking process'],
        9 => ['label' => 'Compliance',       'icon' => 'shield',      'hint' => 'Certificates and trust signals'],
    ];

    // A failed submit lands back here — open the first step that actually has an error
    // rather than step 1, so the message is not hidden behind the rail.
    $stepFields = [
        1 => ['name', 'developer_id', 'project_type', 'project_status', 'tagline', 'description',
              'logo', 'rera_number', 'rera_registered_at', 'rera_valid_till', 'listing_status'],
        2 => ['state', 'city', 'locality', 'full_address', 'landmark', 'pincode', 'zone',
              'latitude', 'longitude', 'maps_link', 'connectivity_highlights', 'nearby_infrastructure'],
        3 => ['price_min', 'price_max', 'price_per_sqft', 'currency', 'total_units', 'towers',
              'floors_per_tower', 'flats_per_floor', 'parking_details', 'unit_types', 'unit_plans'],
        4 => ['land_parcel_acres', 'total_project_area_sqft', 'open_space_percent',
              'construction_specifications', 'amenities', 'amenities_extra', 'amenities_size',
              'amenities_count', 'green_certification', 'vastu_compliant'],
        5 => ['launch_date', 'possession_date', 'construction_progress', 'approving_authorities', 'bank_approvals'],
        6 => ['cover_image', 'gallery', 'site_layout', 'master_plan', 'brochure', 'price_list',
              'video_url', 'virtual_tour_url', 'payment_schedule_file', 'payment_schedule'],
        7 => ['payment_plan_options', 'booking_amount', 'cp_commission_percent', 'special_incentives',
              'cashback_schemes', 'registration_stamp_duty', 'maintenance_charges', 'floor_rise',
              'plc_charges', 'other_charges'],
        8 => ['sales_office_address', 'site_visit_timings', 'sales_contact_name', 'sales_contact_number', 'booking_process'],
        9 => ['rera_certificate', 'legal_due_diligence', 'awards'],
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
                                 selection itself, on edit as well as after a failed submit. --}}
                            <x-select-field label="Developer / builder" name="developer_id" required
                                            :options="$developers->pluck('company_name', 'id')"
                                            hint="Leads from this listing route to this developer.">
                                <option value="">Select a developer…</option>
                            </x-select-field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-select-field label="Project type" name="project_type"
                                            :options="['Residential', 'Commercial', 'Mixed-use', 'Plotted Development', 'Villa', 'Row House']" />
                            <x-select-field label="Project status" name="project_status"
                                            :options="['New Launch', 'Under Construction', 'Ready to Move', 'Nearing Completion']" />
                        </div>

                        <x-field label="Tagline / USP" name="tagline" placeholder="Short marketing line" />
                        <x-field label="Project description" name="description" type="textarea" rows="4"
                                 placeholder="Detailed overview of the project" />

                        <x-file-field label="Project logo / branding" name="logo" accept="image/*" :current="$property?->logo_path"
                                      hint="PNG or JPG, up to 2 MB." />

                        <div class="border-t border-line-soft pt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="RERA registration number" name="rera_number" placeholder="e.g. RERA-DXB-24817" />
                            <x-field label="RERA registered on" name="rera_registered_at" type="date" />
                            <x-field label="RERA valid till" name="rera_valid_till" type="date" />
                        </div>
                    </section>

                    {{-- 2 · Location Details -------------------------------------------- --}}
                    <section x-show="step === 2" data-step="2" x-cloak class="space-y-4">
                        <x-wizard-heading :step="2" :of="count($steps)" title="Location details"
                                          subtitle="Where the project sits, and what it is close to." />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="State / Emirate" name="state" placeholder="e.g. Dubai" />
                            <x-field label="City" name="city" placeholder="e.g. Dubai" icon="map-pin" required />
                            <x-field label="Locality / area" name="locality" placeholder="e.g. Dubai Marina" />
                        </div>

                        <x-field label="Full address" name="full_address" type="textarea"
                                 placeholder="Plot, street, community, city" />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="Landmark / reference point" name="landmark" placeholder="e.g. opposite Marina Mall" />
                            <x-field label="Pincode" name="pincode" placeholder="e.g. 00000" />
                            <x-select-field label="Zone" name="zone" :options="['East', 'West', 'North', 'South', 'Central']">
                                <option value="">Not set</option>
                            </x-select-field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- step="any": a fixed 7-dp step makes a pasted coordinate with more
                                 precision a step-mismatch, which is a needless way to fail. The
                                 column is decimal(10,7) and the server range-checks it. --}}
                            <x-field label="Latitude" name="latitude" type="number" step="any" placeholder="25.0762" />
                            <x-field label="Longitude" name="longitude" type="number" step="any" placeholder="55.1390" />
                            <x-field label="Google Maps link" name="maps_link" type="url" placeholder="https://maps.app.goo.gl/…" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Connectivity highlights" name="connectivity_highlights" type="textarea" rows="4"
                                     placeholder="Metro station — 800 m&#10;Airport — 22 km&#10;Highway access — 5 min"
                                     hint="One per line." />
                            <x-field label="Nearby social infrastructure" name="nearby_infrastructure" type="textarea" rows="4"
                                     placeholder="GEMS School — 1.2 km&#10;Mediclinic — 2 km&#10;Marina Mall — 900 m"
                                     hint="One per line — schools, hospitals, malls, IT parks." />
                        </div>
                    </section>

                    {{-- 3 · Configuration & Pricing ------------------------------------- --}}
                    <section x-show="step === 3" data-step="3" x-cloak class="space-y-4">
                        <x-wizard-heading :step="3" :of="count($steps)" title="Configuration & pricing"
                                          subtitle="Project-wide price band, then one row per unit type." />

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <x-select-field label="Currency" name="currency" :options="['AED', 'INR', 'USD']" />
                            <x-field label="Price from" name="price_min" type="number" placeholder="1800000" required />
                            <x-field label="Price to" name="price_max" type="number" placeholder="3200000" required />
                            <x-field label="Price per sq.ft." name="price_per_sqft" type="number" placeholder="1450" />
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <x-field label="Total units" name="total_units" type="number" placeholder="480" />
                            <x-field label="Towers / blocks" name="towers" type="number" placeholder="3" />
                            <x-field label="Floors per tower" name="floors_per_tower" type="number" placeholder="24" />
                            <x-field label="Flats per floor" name="flats_per_floor" type="number" placeholder="6" />
                        </div>

                        <x-field label="Parking details" name="parking_details"
                                 placeholder="e.g. 2 covered bays per unit, 1:1.5 visitor ratio" />

                        {{-- Repeatable unit types ------------------------------------- --}}
                        <div class="border-t border-line-soft pt-4">
                            <div class="flex items-center justify-between gap-3 mb-2.5">
                                <div>
                                    <p class="text-[12.5px] font-medium text-ink">Unit types available</p>
                                    <p class="text-[11.5px] text-ink-3 mt-0.5">
                                        Areas are in sq.ft.; sq.m. is shown to brokers automatically.
                                    </p>
                                </div>
                                <x-button variant="outline" size="sm" tag="button" type="button" icon="plus"
                                          x-on:click="rows.push({})">Add unit type</x-button>
                            </div>

                            <div class="space-y-2.5">
                                <template x-for="(row, i) in rows" :key="i">
                                    <div class="rounded-lg border border-line bg-canvas p-3 space-y-2.5">
                                        {{-- Carries a saved floor plan through the rebuild. --}}
                                        <input type="hidden" :name="`unit_types[${i}][existing_floor_plan]`"
                                               :value="rows[i].existing_floor_plan ?? ''">

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
                                                    @foreach(['1BHK', '2BHK', '3BHK', '4BHK', 'Villa', 'Plot', 'Studio', 'Commercial unit'] as $label)
                                                        <option value="{{ $label }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Carpet area</span>
                                                <input type="number" :name="`unit_types[${i}][carpet_area_sqft]`"
                                                       x-model="rows[i].carpet_area_sqft" placeholder="sq.ft."
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Built-up area</span>
                                                <input type="number" :name="`unit_types[${i}][built_up_area_sqft]`"
                                                       x-model="rows[i].built_up_area_sqft" placeholder="sq.ft."
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Super built-up</span>
                                                <input type="number" :name="`unit_types[${i}][super_built_up_area_sqft]`"
                                                       x-model="rows[i].super_built_up_area_sqft" placeholder="sq.ft."
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Price from</span>
                                                <input type="number" :name="`unit_types[${i}][price_min]`"
                                                       x-model="rows[i].price_min" placeholder="1800000"
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Price to</span>
                                                <input type="number" :name="`unit_types[${i}][price_max]`"
                                                       x-model="rows[i].price_max" placeholder="2400000"
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">No. of units</span>
                                                <input type="number" :name="`unit_types[${i}][units_count]`"
                                                       x-model="rows[i].units_count" placeholder="120"
                                                       class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink placeholder:text-ink-3 focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                            </label>

                                            <label class="block">
                                                <span class="block text-[11.5px] text-ink-2 mb-1">Floor plan</span>
                                                <input type="file" :name="`unit_types[${i}][floor_plan]`"
                                                       accept="image/*,application/pdf"
                                                       class="w-full h-9 text-[11.5px] text-ink-2 file:mr-2 file:h-9 file:px-2.5 file:rounded-lg file:border-0 file:bg-canvas file:text-[11.5px] file:text-ink-2 file:cursor-pointer">
                                            </label>
                                        </div>
                                    </div>
                                </template>
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
                                          subtitle="Land, build quality and the amenity set." />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="Land parcel size" name="land_parcel_acres" type="number" step="0.01"
                                     placeholder="12.50" hint="In acres." />
                            <x-field label="Total project area" name="total_project_area_sqft" type="number"
                                     placeholder="544500" hint="In sq.ft." />
                            <x-field label="Open / green space %" name="open_space_percent" type="number"
                                     min="0" max="100" placeholder="65" />
                        </div>

                        <x-field label="Construction specifications" name="construction_specifications" type="textarea" rows="4"
                                 placeholder="Structure type, flooring, fittings, brand names — kitchen, bathroom fixtures, etc." />

                        <div class="border-t border-line-soft pt-4 space-y-3">
                            <x-checkbox-group label="Amenities" name="amenities" :options="$amenityOptions" :columns="3" />

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <x-field label="Other amenities" name="amenities_extra"
                                         placeholder="Comma separated" hint="Anything not listed above." />
                                <x-field label="Amenities area / size" name="amenities_size" placeholder="e.g. 40,000 sq.ft. clubhouse" />
                                <x-field label="Number of amenities" name="amenities_count" type="number" placeholder="24" />
                            </div>
                        </div>

                        <div class="border-t border-line-soft pt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                            <x-field label="Green building certification" name="green_certification"
                                     placeholder="e.g. IGBC Gold / LEED Platinum" hint="Leave blank if none." />
                            <x-switch-field label="Vastu compliant" name="vastu_compliant" class="pb-2" />
                        </div>
                    </section>

                    {{-- 5 · Timeline & Legal -------------------------------------------- --}}
                    <section x-show="step === 5" data-step="5" x-cloak class="space-y-4">
                        <x-wizard-heading :step="5" :of="count($steps)" title="Timeline & legal"
                                          subtitle="Delivery dates, statutory approvals and financing tie-ups." />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-field label="Launch date" name="launch_date" type="date" />
                            <x-field label="Possession date" name="possession_date" type="date" hint="Expected handover." />
                            <x-field label="Construction progress %" name="construction_progress" type="number"
                                     min="0" max="100" placeholder="35" hint="Updated periodically." />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Approving authorities" name="approving_authorities" type="textarea" rows="4"
                                     placeholder="Dubai Municipality&#10;Dubai Development Authority"
                                     hint="One per line." />
                            <x-field label="Bank approvals" name="bank_approvals" type="textarea" rows="4"
                                     placeholder="Emirates NBD&#10;ADCB&#10;Mashreq"
                                     hint="One per line — banks offering loans on this project." />
                        </div>
                    </section>

                    {{-- 6 · Media & Marketing Assets ------------------------------------ --}}
                    <section x-show="step === 6" data-step="6" x-cloak class="space-y-4">
                        <x-wizard-heading :step="6" :of="count($steps)" title="Media & marketing assets"
                                          subtitle="Everything brokers see and share." />

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
                                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt=""
                                                 class="w-full aspect-[4/3] object-cover rounded-lg border border-line
                                                        transition-opacity peer-checked:opacity-30">
                                            <span class="absolute inset-x-1 bottom-1 flex items-center justify-center gap-1
                                                         rounded-md bg-panel/90 py-1 text-[10.5px] font-medium text-ink-2
                                                         opacity-0 group-hover:opacity-100 peer-checked:opacity-100
                                                         peer-checked:text-danger transition-opacity">
                                                <span class="peer-checked:hidden">Remove</span>
                                            </span>
                                            <span class="absolute top-1.5 right-1.5 hidden peer-checked:grid place-items-center
                                                         w-5 h-5 rounded-full bg-danger text-white">
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
                            <x-field label="Project video / walkthrough link" name="video_url" type="url"
                                     placeholder="https://youtube.com/…" icon="external" />
                            <x-field label="Virtual tour / 3D walkthrough link" name="virtual_tour_url" type="url"
                                     placeholder="https://my.matterport.com/…" icon="external" />
                        </div>

                        <div class="border-t border-line-soft pt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-file-field label="Payment schedule" name="payment_schedule_file" accept="application/pdf" hint="PDF." :current="$firstMedia('payment_schedule')?->path" />
                            <x-field label="Payment schedule notes" name="payment_schedule" type="textarea"
                                     placeholder="20% on booking, 50% construction-linked, 30% on handover" />
                        </div>
                    </section>

                    {{-- 7 · Commercial Terms -------------------------------------------- --}}
                    <section x-show="step === 7" data-step="7" x-cloak class="space-y-4">
                        <x-wizard-heading :step="7" :of="count($steps)" title="Commercial terms"
                                          subtitle="What the buyer pays, and what the channel partner earns." />

                        <x-checkbox-group label="Payment plan options" name="payment_plan_options"
                                          :options="['Construction-linked', 'Down payment', 'Flexi plan']" :columns="3" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Booking amount" name="booking_amount" type="number" placeholder="100000" />
                            <x-field label="CP commission / brokerage %" name="cp_commission_percent" type="number" step="0.01"
                                     placeholder="2.50" hint="Overrides the developer default for this project." />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Special CP incentives / schemes" name="special_incentives" type="textarea"
                                     placeholder="Time-bound offers for channel partners" />
                            <x-field label="Cashback / discount schemes" name="cashback_schemes" type="textarea"
                                     placeholder="Active buyer-side offers" />
                        </div>

                        <div class="border-t border-line-soft pt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Registration & stamp duty" name="registration_stamp_duty"
                                     placeholder="e.g. 4% of value (indicative)" />
                            <x-field label="Maintenance charges" name="maintenance_charges"
                                     placeholder="e.g. AED 14 per sq.ft. / month" />
                            <x-field label="Floor rise" name="floor_rise" placeholder="e.g. AED 15 per sq.ft. per floor" />
                            <x-field label="PLC charges" name="plc_charges" placeholder="e.g. 3% for park-facing" />
                        </div>

                        <x-field label="Other charges" name="other_charges" type="textarea" rows="4"
                                 placeholder="Club membership — AED 25,000&#10;Legal — AED 5,000&#10;Infrastructure development — AED 40,000"
                                 hint="One per line — itemised where possible." />
                    </section>

                    {{-- 8 · Contact & Sales Info ---------------------------------------- --}}
                    <section x-show="step === 8" data-step="8" x-cloak class="space-y-4">
                        <x-wizard-heading :step="8" :of="count($steps)" title="Contact & sales info"
                                          subtitle="Where brokers take clients, and who they ask for." />

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

                    {{-- 9 · Compliance & Trust Signals ---------------------------------- --}}
                    <section x-show="step === 9" data-step="9" x-cloak class="space-y-4">
                        <x-wizard-heading :step="9" :of="count($steps)" title="Compliance & trust signals"
                                          subtitle="Optional, but these are what make a broker confident enough to pitch." />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-file-field label="RERA QR code / certificate" name="rera_certificate"
                                          accept="image/*,application/pdf" hint="Image or PDF."
                                          :current="$firstMedia('rera_certificate')?->path" />
                            <x-file-field label="Legal due diligence report" name="legal_due_diligence"
                                          accept="application/pdf" hint="PDF, if the developer shared one."
                                          :current="$detail?->legal_due_diligence_path" />
                        </div>

                        <x-field label="Project awards / recognitions" name="awards" type="textarea" rows="4"
                                 placeholder="Best Residential Project — Arabian Property Awards 2025"
                                 hint="One per line." />
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
