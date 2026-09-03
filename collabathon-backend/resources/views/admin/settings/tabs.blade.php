@php
// Black is the brand default; the rest stay available as accents the admin can pick.
$themeColors = [
    ['name' => 'Black',     'hex' => '#000000'],
    ['name' => 'Deep Navy', 'hex' => '#28345C'],
    ['name' => 'Emerald',   'hex' => '#1F9254'],
    ['name' => 'Burgundy',  'hex' => '#8C2F39'],
    ['name' => 'Teal',      'hex' => '#0F7C82'],
];

$formTitles = [
    'broker_registration' => 'Channel Partner Registration',
    'property_listing' => 'Project Listing',
];

// Where each group's toggle is actually read — CompleteProfileScreen.js fetches
// GET /config (see ConfigController) for the broker form; property listings have no
// mobile-side create/edit form at all, so that toggle only ever gates the admin's own
// create/edit form instead (see PropertyController::fieldsEnabled()).
$formSurface = [
    'broker_registration' => 'the mobile app\'s Complete Profile screen',
    'property_listing' => 'this admin panel\'s own project create/edit form',
];

$tabs = [
    ['key' => 'forms',     'label' => 'Form fields'],
    ['key' => 'locations', 'label' => 'Locations'],
    ['key' => 'project-types', 'label' => 'Project types'],
    ['key' => 'unit-types', 'label' => 'Unit types'],
    ['key' => 'amenities', 'label' => 'Amenities'],
    ['key' => 'measurement-units', 'label' => 'Measurement units'],
    ['key' => 'brand',     'label' => 'Branding'],
    ['key' => 'email',     'label' => 'Email'],
    ['key' => 'kyc',       'label' => 'KYC Verification'],
    ['key' => 'whatsapp',  'label' => 'WhatsApp OTP'],
    ['key' => 'master-data', 'label' => 'Master Data'],
    ['key' => 'maps',      'label' => 'Maps'],
    ['key' => 'access',    'label' => 'Access'],
];

// LocationController redirects back with ?tab=locations so a save reopens this tab
// instead of dropping the admin on Form fields. Whitelisted against $tabs so the query
// string cannot select a tab that does not exist.
$openTab = in_array(request()->query('tab'), array_column($tabs, 'key'), true)
    ? request()->query('tab')
    : 'forms';
@endphp

    {{--
        This id is the AJAX refresh target — see the settings-page form interceptor in
        app.js. Every mutating form on this page submits via fetch instead of a real
        navigation (the `tab` below is client-side-only Alpine state, so a real
        redirect-and-reload always reset it back to the default), then refetches this
        same fragment with the current tab/selection preserved in the query string and
        swaps it in whole — one mechanism handles every save/add/delete on the page
        instead of each section needing its own bespoke patch logic.

        `data-ajax-panel` is the *other*, separate generic mechanism (see app.js) —
        GET-only, for the pagination links on the 4 master-data tables (and, as a free
        side effect, the Locations panel's own country/state selection links, which
        already happened to be plain `<a href>`s carrying the right `tab=` themselves).
        The two listeners don't conflict: this one explicitly ignores every POST form,
        which is all the other one ever handles.
    --}}
    <div id="settings-tabs" data-ajax-panel x-data="{ tab: '{{ $openTab }}' }">
        <x-tab-bar :tabs="$tabs" model="tab" class="mb-5" />

        {{-- ---------------------------- Form fields ---------------------------- --}}
        <div x-show="tab === 'forms'" class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 space-y-4">
                @foreach($fieldGroups as $form => $fields)
                    <x-panel :title="$formTitles[$form] ?? $form"
                             :subtitle="'Shown on ' . ($formSurface[$form] ?? 'its form')"
                             flush>
                        <x-slot:actions>
                            <span class="text-[11.5px] text-ink-3 nums">
                                {{ $fields->where('enabled', true)->count() }}/{{ $fields->count() }} enabled
                            </span>
                        </x-slot:actions>

                        <div class="divide-y divide-line-soft">
                            @foreach($fields as $field)
                                <div class="px-5 py-3 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <p class="text-[13px] text-ink truncate">{{ $field->label }}</p>
                                        @if($field->required)
                                            <x-badge tone="neutral" size="sm">Required</x-badge>
                                        @endif
                                        @if($field->is_core)
                                            <x-badge tone="primary" size="sm">Core</x-badge>
                                        @endif
                                    </div>

                                    {{-- Real POST, not a local toggle — see $formSurface above for
                                         exactly where each group's toggle is actually read. --}}
                                    <form method="POST" action="{{ route('admin.settings.field', $field) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="enabled" value="{{ $field->enabled ? 0 : 1 }}">
                                        <button type="submit"
                                                role="switch"
                                                aria-checked="{{ $field->enabled ? 'true' : 'false' }}"
                                                aria-label="{{ $field->enabled ? 'Disable' : 'Enable' }} {{ $field->label }}"
                                                @disabled($field->is_core)
                                                @class([
                                                    'inline-flex items-center shrink-0 w-[38px] h-[21px] px-[2px] transition-colors',
                                                    'bg-primary' => $field->enabled,
                                                    'bg-line' => ! $field->enabled,
                                                    'opacity-50 cursor-not-allowed' => $field->is_core,
                                                    'cursor-pointer' => ! $field->is_core,
                                                ])>
                                            <span @class([
                                                'block w-[17px] h-[17px] bg-white shadow-sm transition-transform duration-150',
                                                'translate-x-[17px]' => $field->enabled,
                                            ])></span>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </x-panel>
                @endforeach
            </div>

            <x-panel title="How this works" padded class="self-start">
                <div class="space-y-3.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <p>Disabling a field hides it from the form it belongs to — Channel Partner Registration on the
                        mobile app, Project Listing on this admin panel's own create/edit form — immediately, not
                        just for the next release. Existing records keep any data already captured.</p>
                    <p><span class="font-medium text-ink">Core</span> fields cannot be disabled: the form's own flow depends on them.</p>
                    <div class="rounded-lg bg-canvas ring-1 ring-inset ring-line px-3.5 py-3">
                        <p class="text-[12px] text-ink-3">
                            A channel partner already partway through registration keeps whatever the form showed
                            when they opened it — the toggle takes effect the next time someone opens the form fresh.
                        </p>
                    </div>
                </div>
            </x-panel>
        </div>

        {{-- ---------------------------- Branding ---------------------------- --}}
        {{-- ---------------------------- Locations ----------------------------
             Three columns, each scoped to the selection on its left. Selecting is a
             plain link rather than a JS filter so the chosen country/state survives a
             save, a validation error and the browser back button — the cascade's
             position lives in the URL, not in component state. --}}
        <div x-show="tab === 'locations'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Countries --}}
            <x-panel title="Countries" :subtitle="$countries->count() . ' total'" flush>
                <ul class="divide-y divide-line-soft max-h-[26rem] overflow-y-auto scrollbar-slim">
                    @forelse($countries as $country)
                        @php $isOn = $selectedCountry?->id === $country->id; @endphp
                        <li @class(['px-4 py-2.5 flex items-center gap-2', 'bg-canvas' => $isOn])>
                            <a href="{{ route('admin.settings', ['tab' => 'locations', 'country' => $country->id]) }}"
                               class="flex-1 min-w-0 group">
                                <span @class(['text-[13px] truncate block', 'font-semibold text-ink' => $isOn, 'text-ink-2 group-hover:text-ink' => ! $isOn])>
                                    {{ $country->name }}
                                    @if($country->code)
                                        <span class="text-[11px] text-ink-3 nums">· {{ $country->code }}</span>
                                    @endif
                                </span>
                                <span class="text-[11px] text-ink-3">{{ $country->states_count }} {{ Str::plural('state', $country->states_count) }}</span>
                            </a>

                            @php
                                $deletePayload = \Illuminate\Support\Js::from([
                                    'title' => 'Delete this country?',
                                    'message' => 'Delete “' . $country->name . '”? Its states and cities go too.',
                                    'confirmLabel' => 'Delete',
                                    'tone' => 'danger',
                                ]);
                            @endphp
                            <form method="POST" action="{{ route('admin.settings.locations.countries.destroy', $country) }}"
                                  data-confirm
                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-ink-3 hover:text-danger p-1" aria-label="Delete {{ $country->name }}">
                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-[12.5px] text-ink-3 text-center">No countries yet. Add one to begin.</li>
                    @endforelse
                </ul>

                <x-slot:footer>
                    {{-- Country + Code stacked above the button rather than crammed into one row —
                         this panel is only a third of the page width, so a flex-1 Country field
                         sharing a row with Code and the Add button left almost no room to type. --}}
                    <form method="POST" action="{{ route('admin.settings.locations.countries.store') }}"
                          class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <x-field label="Country" name="name" placeholder="e.g. United Arab Emirates" />
                            <x-field label="Code" name="code" placeholder="AE" />
                        </div>
                        <x-button variant="primary" tag="button" type="submit" icon="plus" class="w-full">Add</x-button>
                    </form>
                </x-slot:footer>
            </x-panel>

            {{-- States --}}
            <x-panel title="States / Emirates"
                     :subtitle="$selectedCountry ? 'In ' . $selectedCountry->name : 'Select a country first'" flush>
                <ul class="divide-y divide-line-soft max-h-[26rem] overflow-y-auto scrollbar-slim">
                    @forelse($states as $state)
                        @php $isOn = $selectedState?->id === $state->id; @endphp
                        <li @class(['px-4 py-2.5 flex items-center gap-2', 'bg-canvas' => $isOn])>
                            <a href="{{ route('admin.settings', ['tab' => 'locations', 'country' => $selectedCountry->id, 'state' => $state->id]) }}"
                               class="flex-1 min-w-0 group">
                                <span @class(['text-[13px] truncate block', 'font-semibold text-ink' => $isOn, 'text-ink-2 group-hover:text-ink' => ! $isOn])>
                                    {{ $state->name }}
                                </span>
                                <span class="text-[11px] text-ink-3">{{ $state->cities_count }} {{ Str::plural('city', $state->cities_count) }}</span>
                            </a>

                            @php
                                $deletePayload = \Illuminate\Support\Js::from([
                                    'title' => 'Delete this state?',
                                    'message' => 'Delete “' . $state->name . '”? Its cities go too.',
                                    'confirmLabel' => 'Delete',
                                    'tone' => 'danger',
                                ]);
                            @endphp
                            <form method="POST" action="{{ route('admin.settings.locations.states.destroy', $state) }}"
                                  data-confirm
                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-ink-3 hover:text-danger p-1" aria-label="Delete {{ $state->name }}">
                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-[12.5px] text-ink-3 text-center">
                            {{ $selectedCountry ? 'No states in ' . $selectedCountry->name . ' yet.' : 'Add a country first.' }}
                        </li>
                    @endforelse
                </ul>

                @if($selectedCountry)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.settings.locations.states.store') }}"
                              class="flex items-end gap-2">
                            @csrf
                            <input type="hidden" name="country_id" value="{{ $selectedCountry->id }}">
                            <div class="flex-1"><x-field label="State / Emirate" name="name" placeholder="e.g. Dubai" /></div>
                            {{-- See the matching note on the Countries footer above. --}}
                            <div>
                                <span class="block text-[12.5px] font-medium mb-1.5 invisible" aria-hidden="true">Add</span>
                                <x-button variant="primary" tag="button" type="submit" icon="plus">Add</x-button>
                            </div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-panel>

            {{-- Cities --}}
            <x-panel title="Cities"
                     :subtitle="$selectedState ? 'In ' . $selectedState->name : 'Select a state first'" flush>
                <ul class="divide-y divide-line-soft max-h-[26rem] overflow-y-auto scrollbar-slim">
                    @forelse($cities as $city)
                        <li class="px-4 py-2.5 flex items-center gap-2">
                            <span class="flex-1 min-w-0 text-[13px] text-ink-2 truncate">{{ $city->name }}</span>

                            @php
                                $deletePayload = \Illuminate\Support\Js::from([
                                    'title' => 'Delete this city?',
                                    'message' => 'Delete “' . $city->name . '”?',
                                    'confirmLabel' => 'Delete',
                                    'tone' => 'danger',
                                ]);
                            @endphp
                            <form method="POST" action="{{ route('admin.settings.locations.cities.destroy', $city) }}"
                                  data-confirm
                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el })">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-ink-3 hover:text-danger p-1" aria-label="Delete {{ $city->name }}">
                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-[12.5px] text-ink-3 text-center">
                            {{ $selectedState ? 'No cities in ' . $selectedState->name . ' yet.' : 'Add a state first.' }}
                        </li>
                    @endforelse
                </ul>

                @if($selectedState)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.settings.locations.cities.store') }}"
                              class="flex items-end gap-2">
                            @csrf
                            <input type="hidden" name="state_id" value="{{ $selectedState->id }}">
                            <div class="flex-1"><x-field label="City" name="name" placeholder="e.g. Deira" /></div>
                            {{-- See the matching note on the Countries footer above. --}}
                            <div>
                                <span class="block text-[12.5px] font-medium mb-1.5 invisible" aria-hidden="true">Add</span>
                                <x-button variant="primary" tag="button" type="submit" icon="plus">Add</x-button>
                            </div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-panel>
        </div>

        {{-- ---------------------------- Project types ----------------------------
             Master data for the project intake form's type list. Each row is its own
             PATCH form so a single Save touches only that type, and the panel needs no
             client-side state to track which row is dirty. --}}
        <div x-show="tab === 'project-types'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            <x-panel title="Project types" flush class="xl:col-span-2">
                <x-slot:actions>
                    <span class="text-[11.5px] text-ink-3 nums">{{ $projectTypes->total() }} total</span>
                </x-slot:actions>

                <div class="overflow-x-auto scrollbar-slim">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line-soft">
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Name</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[130px]">
                                    Possession date
                                </th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[90px]">Active</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[80px]">Order</th>
                                <th scope="col" class="px-4 py-2.5 w-[120px]"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @forelse($projectTypes as $type)
                                <tr class="hover:bg-canvas transition-colors align-middle">
                                    <td class="px-4 py-2.5">
                                        <input form="type-form-{{ $type->id }}" name="name" value="{{ $type->name }}"
                                               required maxlength="96"
                                               class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                                      focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                        @if($type->projects_count)
                                            <p class="text-[11px] text-ink-3 mt-1">
                                                {{ $type->projects_count }} {{ Str::plural('project', $type->projects_count) }} —
                                                renaming updates {{ $type->projects_count === 1 ? 'it' : 'them' }} too
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5">
                                        {{-- Paired hidden input so "off" posts 0 rather than dropping the key. --}}
                                        <input form="type-form-{{ $type->id }}" type="hidden" name="requires_possession_date" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="type-form-{{ $type->id }}" type="checkbox"
                                                   name="requires_possession_date" value="1"
                                                   @checked($type->requires_possession_date)
                                                   class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                                            <span class="text-[12px] text-ink-2">Required</span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <input form="type-form-{{ $type->id }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="type-form-{{ $type->id }}" type="checkbox" name="is_active" value="1"
                                                   @checked($type->is_active)
                                                   class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                                            <span class="text-[12px] text-ink-2">On</span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <x-number-stepper form="type-form-{{ $type->id }}" name="sort_order"
                                                           :value="$type->sort_order" />
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-button variant="subtle" size="sm" tag="button" type="submit"
                                                      form="type-form-{{ $type->id }}">Save</x-button>

                                            @php
                                                $typeDelete = \Illuminate\Support\Js::from([
                                                    'title' => 'Delete this project type?',
                                                    'message' => $type->projects_count
                                                        ? "“{$type->name}” is used by {$type->projects_count} project"
                                                            . ($type->projects_count === 1 ? '' : 's')
                                                            . ' and cannot be deleted — turn it off instead.'
                                                        : "“{$type->name}” will no longer be offered on the project form.",
                                                    'confirmLabel' => 'Delete type',
                                                    'tone' => 'danger',
                                                ]);
                                            @endphp
                                            <form method="POST" action="{{ route('admin.settings.project-types.destroy', $type) }}"
                                                  data-confirm
                                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $typeDelete }}, form: $el })">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-ink-3 hover:text-danger p-1.5 rounded-md transition-colors"
                                                        aria-label="Delete {{ $type->name }}">
                                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-[12.5px] text-ink-3 text-center">
                                        No project types yet. Add one so the project form has something to offer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-line-soft">
                        <x-pagination :paginator="$projectTypes" label="project types" />
                    </div>

                    {{--
                        Each row's own PATCH form, rendered OUTSIDE the table rather than as a
                        <tr>'s direct child. A <form> is not valid table content — the HTML
                        parser (per spec, "in table" insertion mode) inserts it and then
                        immediately pops it back off again, so its @csrf/@method hidden inputs
                        never actually land inside it. Every row's Save button still finds its
                        real, empty content via the form="…" attribute, same as it always did;
                        only this now-real <form> tag moved. This was invisible under the old
                        full-page-reload flow (a fresh navigation reset the parser state on
                        every single save), but broke a second same-session save with a fetch-
                        based one: only the first row-form actually parsed as a real element,
                        so every other row's _token/_method silently never left the browser.
                    --}}
                    @foreach($projectTypes as $type)
                        <form method="POST" action="{{ route('admin.settings.project-types.update', $type) }}"
                              id="type-form-{{ $type->id }}" class="hidden" aria-hidden="true">
                            @csrf @method('PATCH')
                        </form>
                    @endforeach
                </div>
            </x-panel>

            <div class="space-y-4">
                <x-panel title="Add a project type" padded class="self-start">
                    <form method="POST" action="{{ route('admin.settings.project-types.store') }}" class="space-y-3.5">
                        @csrf
                        <x-field label="Name" name="name" placeholder="e.g. Farmhouse" required maxlength="96" />

                        <input type="hidden" name="requires_possession_date" value="0">
                        <x-switch-field label="Needs a possession date" name="requires_possession_date"
                                        hint="RERA mandates a completion date for built units. Leave off for land-only types." />

                        <input type="hidden" name="is_active" value="0">
                        <x-switch-field label="Offer on the project form" name="is_active" :checked="true"
                                        hint="Turn off to retire a type without touching existing projects." />

                        <div class="pt-1">
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="w-full">
                                Add project type
                            </x-button>
                        </div>
                    </form>
                </x-panel>

                <x-panel title="How this works" padded class="self-start">
                    <div class="space-y-3 text-[12.5px] text-ink-2 leading-relaxed">
                        <p>
                            These are the options the project intake form offers under
                            <span class="font-medium text-ink">Project type</span>, and the choices in the
                            Projects list filter.
                        </p>
                        <p>
                            Types marked <span class="font-medium text-ink">Possession date required</span> make
                            <span class="font-medium text-ink">Possession date (as per RERA)</span> appear and become
                            mandatory on that project.
                        </p>
                        <p>
                            Renaming a type updates every project already using it. A type still in use cannot be
                            deleted — turn it off instead, which hides it from new projects while leaving existing
                            ones untouched.
                        </p>
                    </div>
                </x-panel>
            </div>
        </div>

        {{-- ---------------------------- Unit types ------------------------------
             Master data for the unit rows on the project intake form. Same shape as
             Project types: one PATCH form per row, so a Save touches only that row and
             the panel needs no client-side dirty tracking. Form ids are prefixed
             `unit-form-` so they cannot collide with the project-type forms above. --}}
        <div x-show="tab === 'unit-types'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            <x-panel title="Unit types" flush class="xl:col-span-2">
                <x-slot:actions>
                    <span class="text-[11.5px] text-ink-3 nums">{{ $unitTypes->total() }} total</span>
                </x-slot:actions>

                <div class="overflow-x-auto scrollbar-slim">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line-soft">
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Name</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[90px]">Active</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[80px]">Order</th>
                                <th scope="col" class="px-4 py-2.5 w-[120px]"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @forelse($unitTypes as $unit)
                                <tr class="hover:bg-canvas transition-colors align-middle">
                                    <td class="px-4 py-2.5">
                                        <input form="unit-form-{{ $unit->id }}" name="name" value="{{ $unit->name }}"
                                               required maxlength="96"
                                               class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                                      focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                        @if($unit->usage_count)
                                            <p class="text-[11px] text-ink-3 mt-1">
                                                {{ $unit->usage_count }} {{ Str::plural('unit row', $unit->usage_count) }} —
                                                renaming updates {{ $unit->usage_count === 1 ? 'it' : 'them' }} too
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5">
                                        {{-- Paired hidden input so "off" posts 0 rather than dropping the key. --}}
                                        <input form="unit-form-{{ $unit->id }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="unit-form-{{ $unit->id }}" type="checkbox" name="is_active" value="1"
                                                   @checked($unit->is_active)
                                                   class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                                            <span class="text-[12px] text-ink-2">On</span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <x-number-stepper form="unit-form-{{ $unit->id }}" name="sort_order"
                                                           :value="$unit->sort_order" />
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-button variant="subtle" size="sm" tag="button" type="submit"
                                                      form="unit-form-{{ $unit->id }}">Save</x-button>

                                            @php
                                                $unitDelete = \Illuminate\Support\Js::from([
                                                    'title' => 'Delete this unit type?',
                                                    'message' => $unit->usage_count
                                                        ? "“{$unit->name}” is used on {$unit->usage_count} unit row"
                                                            . ($unit->usage_count === 1 ? '' : 's')
                                                            . ' and cannot be deleted — turn it off instead.'
                                                        : "“{$unit->name}” will no longer be offered on the project form.",
                                                    'confirmLabel' => 'Delete type',
                                                    'tone' => 'danger',
                                                ]);
                                            @endphp
                                            <form method="POST" action="{{ route('admin.settings.unit-types.destroy', $unit) }}"
                                                  data-confirm
                                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $unitDelete }}, form: $el })">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-ink-3 hover:text-danger p-1.5 rounded-md transition-colors"
                                                        aria-label="Delete {{ $unit->name }}">
                                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-[12.5px] text-ink-3 text-center">
                                        No unit types yet. Add one so the project form's unit rows have something to offer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-line-soft">
                        <x-pagination :paginator="$unitTypes" label="unit types" />
                    </div>

                    {{-- See the matching note on the Project types table above. --}}
                    @foreach($unitTypes as $unit)
                        <form method="POST" action="{{ route('admin.settings.unit-types.update', $unit) }}"
                              id="unit-form-{{ $unit->id }}" class="hidden" aria-hidden="true">
                            @csrf @method('PATCH')
                        </form>
                    @endforeach
                </div>
            </x-panel>

            <div class="space-y-4">
                <x-panel title="Add a unit type" padded class="self-start">
                    <form method="POST" action="{{ route('admin.settings.unit-types.store') }}" class="space-y-3.5">
                        @csrf
                        <x-field label="Name" name="name" input-id="unit-type-name"
                                 placeholder="e.g. 6BHK" required maxlength="96" />

                        <input type="hidden" name="is_active" value="0">
                        <x-switch-field label="Offer on the project form" name="is_active" input-id="unit-type-active"
                                        :checked="true"
                                        hint="Turn off to retire a type without touching projects already using it." />

                        <div class="pt-1">
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="w-full">
                                Add unit type
                            </x-button>
                        </div>
                    </form>
                </x-panel>

                <x-panel title="How this works" padded class="self-start">
                    <p class="text-[12.5px] text-ink-2 leading-relaxed">
                        These are the options in the <span class="font-medium text-ink">Unit type</span> dropdown on
                        each unit row in step 3 of the project form.
                    </p>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-3">
                        Projects store the <span class="font-medium text-ink">name</span>, not a reference, so renaming
                        a type updates every unit row using it in the same save. A type still in use cannot be deleted —
                        turn it off instead, and it stays valid where it already appears.
                    </p>
                </x-panel>
            </div>
        </div>

        {{-- ---------------------------- Measurement units -----------------------
             Master data for the "Project extent metric" dropdown on the intake form. Same shape as
             Project types: one PATCH form per row, so a Save touches only that row and
             the panel needs no client-side dirty tracking. Form ids are prefixed
             `metric-form-` so they cannot collide with the other panels' forms. --}}
        <div x-show="tab === 'measurement-units'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            <x-panel title="Measurement units" flush class="xl:col-span-2">
                <x-slot:actions>
                    <span class="text-[11.5px] text-ink-3 nums">{{ $measurementUnits->total() }} total</span>
                </x-slot:actions>

                <div class="overflow-x-auto scrollbar-slim">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line-soft">
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Name</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[90px]">Active</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[80px]">Order</th>
                                <th scope="col" class="px-4 py-2.5 w-[120px]"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @forelse($measurementUnits as $metric)
                                <tr class="hover:bg-canvas transition-colors align-middle">
                                    <td class="px-4 py-2.5">
                                        <input form="metric-form-{{ $metric->id }}" name="name" value="{{ $metric->name }}"
                                               required maxlength="96"
                                               class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                                      focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                        @if($metric->usage_count)
                                            <p class="text-[11px] text-ink-3 mt-1">
                                                {{ $metric->usage_count }} {{ Str::plural('project', $metric->usage_count) }} —
                                                renaming updates {{ $metric->usage_count === 1 ? 'it' : 'them' }} too
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5">
                                        {{-- Paired hidden input so "off" posts 0 rather than dropping the key. --}}
                                        <input form="metric-form-{{ $metric->id }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="metric-form-{{ $metric->id }}" type="checkbox" name="is_active" value="1"
                                                   @checked($metric->is_active)
                                                   class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                                            <span class="text-[12px] text-ink-2">On</span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <x-number-stepper form="metric-form-{{ $metric->id }}" name="sort_order"
                                                           :value="$metric->sort_order" />
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-button variant="subtle" size="sm" tag="button" type="submit"
                                                      form="metric-form-{{ $metric->id }}">Save</x-button>

                                            @php
                                                $metricDelete = \Illuminate\Support\Js::from([
                                                    'title' => 'Delete this measurement unit?',
                                                    'message' => $metric->usage_count
                                                        ? "“{$metric->name}” is used by {$metric->usage_count} project"
                                                            . ($metric->usage_count === 1 ? '' : 's')
                                                            . ' and cannot be deleted — turn it off instead.'
                                                        : "“{$metric->name}” will no longer be offered on the project form.",
                                                    'confirmLabel' => 'Delete unit',
                                                    'tone' => 'danger',
                                                ]);
                                            @endphp
                                            <form method="POST" action="{{ route('admin.settings.measurement-units.destroy', $metric) }}"
                                                  data-confirm
                                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $metricDelete }}, form: $el })">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-ink-3 hover:text-danger p-1.5 rounded-md transition-colors"
                                                        aria-label="Delete {{ $metric->name }}">
                                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-[12.5px] text-ink-3 text-center">
                                        No measurement units yet. Add one so the extent metric dropdown has something to offer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-line-soft">
                        <x-pagination :paginator="$measurementUnits" label="measurement units" />
                    </div>

                    {{-- See the matching note on the Project types table above. --}}
                    @foreach($measurementUnits as $metric)
                        <form method="POST" action="{{ route('admin.settings.measurement-units.update', $metric) }}"
                              id="metric-form-{{ $metric->id }}" class="hidden" aria-hidden="true">
                            @csrf @method('PATCH')
                        </form>
                    @endforeach
                </div>
            </x-panel>

            <div class="space-y-4">
                <x-panel title="Add a measurement unit" padded class="self-start">
                    <form method="POST" action="{{ route('admin.settings.measurement-units.store') }}" class="space-y-3.5">
                        @csrf
                        <x-field label="Name" name="name" input-id="metric-name"
                                 placeholder="e.g. Sq. yards" required maxlength="96" />

                        <input type="hidden" name="is_active" value="0">
                        <x-switch-field label="Offer on the project form" name="is_active" input-id="metric-active"
                                        :checked="true"
                                        hint="Turn off to retire a type without touching projects already using it." />

                        <div class="pt-1">
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="w-full">
                                Add measurement unit
                            </x-button>
                        </div>
                    </form>
                </x-panel>

                <x-panel title="How this works" padded class="self-start">
                    <p class="text-[12.5px] text-ink-2 leading-relaxed">
                        These are the options in the <span class="font-medium text-ink">Project extent metric</span>
                        dropdown in step 3 of the project form.
                    </p>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-3">
                        Projects store the <span class="font-medium text-ink">name</span>, not a reference, so renaming
                        a unit updates every project using it in the same save. A unit still in use cannot be deleted —
                        turn it off instead, and it stays valid where it already appears.
                    </p>
                </x-panel>
            </div>
        </div>

        {{-- ---------------------------- Amenities -------------------------------
             Master data for the amenity checkboxes on the project intake form. Same
             shape as Unit types: one PATCH form per row, so a Save touches only that
             row and the panel needs no client-side dirty tracking. Form ids are
             prefixed `amenity-form-` so they cannot collide with the panels above. --}}
        <div x-show="tab === 'amenities'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            <x-panel title="Amenities" flush class="xl:col-span-2">
                <x-slot:actions>
                    <span class="text-[11.5px] text-ink-3 nums">
                        {{ $amenitiesActiveCount }} of {{ $amenities->total() }} offered
                    </span>
                </x-slot:actions>

                <div class="overflow-x-auto scrollbar-slim">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line-soft">
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3">Name</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[90px]">Active</th>
                                <th scope="col" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-3 w-[80px]">Order</th>
                                <th scope="col" class="px-4 py-2.5 w-[120px]"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @forelse($amenities as $amenity)
                                <tr class="hover:bg-canvas transition-colors align-middle">
                                    <td class="px-4 py-2.5">
                                        <input form="amenity-form-{{ $amenity->id }}" name="name" value="{{ $amenity->name }}"
                                               required maxlength="96"
                                               class="w-full h-9 px-3 rounded-lg bg-panel border border-line text-[13px] text-ink
                                                      focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
                                        @if($amenity->usage_count)
                                            <p class="text-[11px] text-ink-3 mt-1">
                                                {{ $amenity->usage_count }} {{ Str::plural('project', $amenity->usage_count) }} —
                                                renaming updates {{ $amenity->usage_count === 1 ? 'it' : 'them' }} too
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2.5">
                                        {{-- Paired hidden input so "off" posts 0 rather than dropping the key. --}}
                                        <input form="amenity-form-{{ $amenity->id }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="amenity-form-{{ $amenity->id }}" type="checkbox" name="is_active" value="1"
                                                   @checked($amenity->is_active)
                                                   class="w-4 h-4 rounded border-line text-primary focus:ring-primary-ring">
                                            <span class="text-[12px] text-ink-2">On</span>
                                        </label>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <x-number-stepper form="amenity-form-{{ $amenity->id }}" name="sort_order"
                                                           :value="$amenity->sort_order" />
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-button variant="subtle" size="sm" tag="button" type="submit"
                                                      form="amenity-form-{{ $amenity->id }}">Save</x-button>

                                            @php
                                                $amenityDelete = \Illuminate\Support\Js::from([
                                                    'title' => 'Delete this amenity?',
                                                    'message' => $amenity->usage_count
                                                        ? "“{$amenity->name}” is listed on {$amenity->usage_count} project"
                                                            . ($amenity->usage_count === 1 ? '' : 's')
                                                            . ' and cannot be deleted — turn it off instead.'
                                                        : "“{$amenity->name}” will no longer be offered on the project form.",
                                                    'confirmLabel' => 'Delete amenity',
                                                    'tone' => 'danger',
                                                ]);
                                            @endphp
                                            <form method="POST" action="{{ route('admin.settings.amenities.destroy', $amenity) }}"
                                                  data-confirm
                                                  x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $amenityDelete }}, form: $el })">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-ink-3 hover:text-danger p-1.5 rounded-md transition-colors"
                                                        aria-label="Delete {{ $amenity->name }}">
                                                    <x-icon name="x" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-[12.5px] text-ink-3 text-center">
                                        No amenities yet. Add one so the project form has something to offer.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-line-soft">
                        <x-pagination :paginator="$amenities" label="amenities" />
                    </div>

                    {{-- See the matching note on the Project types table above. --}}
                    @foreach($amenities as $amenity)
                        <form method="POST" action="{{ route('admin.settings.amenities.update', $amenity) }}"
                              id="amenity-form-{{ $amenity->id }}" class="hidden" aria-hidden="true">
                            @csrf @method('PATCH')
                        </form>
                    @endforeach
                </div>
            </x-panel>

            <div class="space-y-4">
                <x-panel title="Add an amenity" padded class="self-start">
                    <form method="POST" action="{{ route('admin.settings.amenities.store') }}" class="space-y-3.5">
                        @csrf
                        <x-field label="Name" name="name" input-id="amenity-name"
                                 placeholder="e.g. Co-working Lounge" required maxlength="96" />

                        <input type="hidden" name="is_active" value="0">
                        <x-switch-field label="Offer on the project form" name="is_active" input-id="amenity-active"
                                        :checked="true"
                                        hint="Turn off to retire an amenity without touching projects already listing it." />

                        <div class="pt-1">
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="w-full">
                                Add amenity
                            </x-button>
                        </div>
                    </form>
                </x-panel>

                <x-panel title="How this works" padded class="self-start">
                    <p class="text-[12.5px] text-ink-2 leading-relaxed">
                        These are the checkboxes under <span class="font-medium text-ink">Amenities</span> in step 4
                        of the project form. <span class="font-medium text-ink">Order</span> sets the reading order of
                        the grid, so it is worth keeping the common ones near the top.
                    </p>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-3">
                        Projects store the <span class="font-medium text-ink">name</span>, not a reference, so renaming
                        an amenity updates every project listing it in the same save. One still in use cannot be
                        deleted — turn it off instead, and it stays valid where it already appears.
                    </p>
                    <p class="text-[12.5px] text-ink-2 leading-relaxed mt-3">
                        Anything typed into the project form's <span class="font-medium text-ink">Other amenities</span>
                        box is saved on that project but never added here. Add it above to offer it as a checkbox.
                    </p>
                </x-panel>
            </div>
        </div>

        <div x-show="tab === 'brand'" x-cloak class="grid grid-cols-1 gap-4">
            <x-panel title="App accent colour"
                     subtitle="The accent colour currently set for the Channel Partner and Developer mobile apps"
                     padded>
                <div class="flex flex-wrap gap-2.5">
                    @foreach($themeColors as $color)
                        <div @class([
                                'flex items-center gap-2.5 rounded-xl border px-3 py-2.5',
                                'border-nav bg-canvas' => $accentColor === $color['hex'],
                                'border-line' => $accentColor !== $color['hex'],
                            ])>
                            <span class="w-7 h-7 rounded-lg shrink-0 ring-1 ring-inset ring-line"
                                  style="background-color: {{ $color['hex'] }}"></span>
                            <span class="text-left">
                                <span class="block text-[12.5px] font-medium text-ink">{{ $color['name'] }}</span>
                                <span class="block text-[11px] text-ink-3 nums uppercase">{{ $color['hex'] }}</span>
                            </span>
                            @if($accentColor === $color['hex'])
                                <x-icon name="check" class="w-4 h-4 text-primary-dark ml-1" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-panel>
        </div>

        {{-- ---------------------------- Access ---------------------------- --}}
        {{-- ---------------------------- Email ---------------------------- --}}
        <div x-show="tab === 'email'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="Mailjet"
                     subtitle="Used to email approved channel partners their sign-in details"
                     padded class="xl:col-span-2">

                <div @class([
                    'flex items-start gap-2.5 px-3.5 py-3 mb-5 border',
                    'bg-success-soft border-success-ring' => $mail['configured'],
                    'bg-warning-soft border-warning-ring' => ! $mail['configured'],
                ])>
                    <x-icon :name="$mail['configured'] ? 'check' : 'clock'"
                            class="w-4 h-4 shrink-0 mt-px {{ $mail['configured'] ? 'text-success' : 'text-warning' }}" />
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium {{ $mail['configured'] ? 'text-success' : 'text-warning' }}">
                            {{ $mail['configured'] ? 'Connected' : 'Not configured' }}
                        </p>
                        <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">
                            @if($mail['configured'])
                                Sending as <span class="nums">{{ $mail['from_address'] }}</span> with key
                                <span class="nums">{{ $mail['masked_key'] }}</span>. Approving a channel partner emails them
                                automatically.
                            @else
                                Until a key is saved here, approving a channel partner changes their access but sends no email.
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.mail') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="API key" name="mailjet_api_key" required
                                 :value="$mail['api_key']"
                                 placeholder="27aa5174…"
                                 hint="Mailjet account → API Key Management." />
                        {{-- Blank means keep. The stored secret is encrypted and is never
                             rendered back, so there is nothing to prefill it with. --}}
                        <x-field label="Secret key" name="mailjet_secret_key" type="password"
                                 :required="! $mail['configured']"
                                 :placeholder="$mail['has_secret'] ? 'Saved — leave blank to keep' : 'Secret key'"
                                 :hint="$mail['has_secret'] ? 'Stored encrypted. Enter a new one only to replace it.' : 'Stored encrypted, never shown again.'" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="From address" name="mail_from_address" type="email" required
                                 :value="$mail['from_address']"
                                 placeholder="no-reply@yourdomain.com"
                                 hint="Must be a sender Mailjet has verified, or mail is rejected." />
                        <x-field label="From name" name="mail_from_name" required
                                 :value="$mail['from_name']"
                                 placeholder="{{ config('app.name') }}" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            <div class="space-y-4 self-start">
                <x-panel title="Send a test" subtitle="Proves the key works before it matters" padded>
                    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="space-y-3">
                        @csrf
                        <x-field label="Send to" name="test_email" type="email" required
                                 :value="auth()->user()->email"
                                 hint="Delivers the real approval email, marked as a test." />
                        <x-button type="submit" variant="subtle" icon="mail" class="w-full"
                                  :disabled="! $mail['configured']">
                            Send test email
                        </x-button>
                    </form>
                </x-panel>

                <x-panel title="When email goes out" padded>
                    <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                        <li class="flex gap-2">
                            <span class="text-primary">&bull;</span>
                            <span>A channel partner is <strong class="text-ink">approved</strong> — they get their sign-in details.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-primary">&bull;</span>
                            <span>A channel partner's password is <strong class="text-ink">reset</strong> — the new one is emailed to them.</span>
                        </li>
                    </ul>
                    <p class="text-[12px] text-ink-3 mt-4 pt-3 border-t border-line-soft leading-relaxed">
                        Sending never blocks a decision. If mail fails, the approval still stands and
                        the banner on that page says so.
                    </p>
                </x-panel>
            </div>
        </div>

        {{-- ---------------------------- KYC Verification (Surepass) ---------------------------- --}}
        <div x-show="tab === 'kyc'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="Surepass"
                     subtitle="Verifies GST, Aadhaar (offline XML/QR) and PAN on the Complete Profile screen"
                     padded class="xl:col-span-2">

                <div @class([
                    'flex items-start gap-2.5 px-3.5 py-3 mb-5 border',
                    'bg-success-soft border-success-ring' => $surepass['configured'],
                    'bg-warning-soft border-warning-ring' => ! $surepass['configured'],
                ])>
                    <x-icon :name="$surepass['configured'] ? 'check' : 'clock'"
                            class="w-4 h-4 shrink-0 mt-px {{ $surepass['configured'] ? 'text-success' : 'text-warning' }}" />
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium {{ $surepass['configured'] ? 'text-success' : 'text-warning' }}">
                            {{ $surepass['configured'] ? 'Connected' : 'Not configured' }}
                            — running in {{ $surepass['environment'] === 'production' ? 'Production' : 'Sandbox' }}
                        </p>
                        <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">
                            @if($surepass['configured'])
                                A token is saved for this environment. Verification calls on the Complete
                                Profile screen will use it once that integration is wired in.
                            @else
                                Until a token is saved for the active environment below, GST/Aadhaar/PAN
                                fields on Complete Profile go unverified.
                            @endif
                        </p>
                    </div>
                </div>

                {{-- `env` mirrors the environment select so each token's client-side `required`
                     can react to it — only the token for the environment you're about to save
                     should ever block submission; the other stays optional until you switch to
                     it. Matches the server-side `required_if` in SettingsController::updateSurepass()
                     exactly, just live instead of round-tripping a page load to see it. --}}
                <form method="POST" action="{{ route('admin.settings.surepass') }}" class="space-y-4"
                      x-data="{ env: '{{ $surepass['environment'] }}' }">
                    @csrf
                    @method('PATCH')

                    <x-select-field label="Active environment" name="surepass_environment" required
                                     x-model="env"
                                     :selected="$surepass['environment']"
                                     :options="['sandbox' => 'Sandbox (testing)', 'production' => 'Production (live)']"
                                     hint="Matches the Sandbox/Production toggle in the Surepass console. Build and test on Sandbox first." />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Blank means keep — same reasoning as the Mailjet secret key: the
                             stored token is encrypted and never rendered back. --}}
                        <x-field label="Sandbox token" name="surepass_sandbox_token" type="password"
                                 :required="false"
                                 x-bind:required="env === 'sandbox' && {{ $surepass['has_sandbox_token'] ? 'false' : 'true' }}"
                                 :placeholder="$surepass['has_sandbox_token'] ? 'Saved — leave blank to keep' : 'Bearer token'"
                                 :hint="$surepass['has_sandbox_token'] ? 'Stored encrypted (' . $surepass['masked_sandbox_token'] . '). Enter a new one only to replace it.' : 'Surepass console → Credential (Sandbox) → Verify Identity to View.'" />
                        <x-field label="Production token" name="surepass_production_token" type="password"
                                 :required="false"
                                 x-bind:required="env === 'production' && {{ $surepass['has_production_token'] ? 'false' : 'true' }}"
                                 :placeholder="$surepass['has_production_token'] ? 'Saved — leave blank to keep' : 'Bearer token'"
                                 :hint="$surepass['has_production_token'] ? 'Stored encrypted (' . $surepass['masked_production_token'] . '). Enter a new one only to replace it.' : 'Add once Sandbox is verified working.'" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="What this covers" padded class="self-start">
                <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span><strong class="text-ink">GST</strong> — company name/address lookup by GSTIN.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span><strong class="text-ink">Aadhaar</strong> — live UIDAI verification through DigiLocker: the channel partner signs in with their own Aadhaar-linked mobile and OTP, and the verified record comes straight back. Replaces the earlier offline XML/QR upload route, which Surepass never enabled on this account.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span><strong class="text-ink">PAN</strong> — verified against Income Tax / Protean records.</span>
                    </li>
                </ul>
                <p class="text-[12px] text-ink-3 mt-4 pt-3 border-t border-line-soft leading-relaxed">
                    This panel only stores the credential. The GST/Aadhaar/PAN verification calls
                    themselves are the next step, once the exact Surepass endpoint contracts are on hand.
                </p>
            </x-panel>
        </div>

        {{-- ---------------------------- WhatsApp OTP (MSG91) ---------------------------- --}}
        <div x-show="tab === 'whatsapp'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="MSG91 WhatsApp"
                     subtitle="Delivers the mobile-number sign-in code over WhatsApp instead of SMS"
                     padded class="xl:col-span-2">

                <div @class([
                    'flex items-start gap-2.5 px-3.5 py-3 mb-5 border',
                    'bg-success-soft border-success-ring' => $whatsapp['configured'],
                    'bg-warning-soft border-warning-ring' => ! $whatsapp['configured'],
                ])>
                    <x-icon :name="$whatsapp['configured'] ? 'check' : 'clock'"
                            class="w-4 h-4 shrink-0 mt-px {{ $whatsapp['configured'] ? 'text-success' : 'text-warning' }}" />
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium {{ $whatsapp['configured'] ? 'text-success' : 'text-warning' }}">
                            {{ $whatsapp['configured'] ? 'Connected' : 'Not configured' }}
                            — running in {{ $whatsapp['environment'] === 'production' ? 'Production' : 'Sandbox' }}
                        </p>
                        <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">
                            @if($whatsapp['configured'])
                                An auth key and template are saved for this environment. The mobile-number
                                sign-in flow sends its code through WhatsApp instead of logging it.
                            @else
                                Until an auth key and template are saved below, the code is only logged
                                server-side (and returned to the app directly outside production) —
                                nothing is actually sent to the broker's phone.
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Same `env`-reactive required pattern as Surepass above — see the note
                     there for why the plain `required` prop is forced off and this drives
                     it instead. --}}
                <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="space-y-4"
                      x-data="{ env: '{{ $whatsapp['environment'] }}' }">
                    @csrf
                    @method('PATCH')

                    <x-select-field label="Active environment" name="whatsapp_environment" required
                                     x-model="env"
                                     :selected="$whatsapp['environment']"
                                     :options="['sandbox' => 'Sandbox (testing)', 'production' => 'Production (live)']"
                                     hint="MSG91's API endpoint never changes — only the auth key, number and template do. Build and test on Sandbox first." />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Sandbox auth key" name="whatsapp_sandbox_token" type="password"
                                 :required="false"
                                 x-bind:required="env === 'sandbox' && {{ $whatsapp['has_sandbox_token'] ? 'false' : 'true' }}"
                                 :placeholder="$whatsapp['has_sandbox_token'] ? 'Saved — leave blank to keep' : 'MSG91 auth key'"
                                 :hint="$whatsapp['has_sandbox_token'] ? 'Stored encrypted (' . $whatsapp['masked_sandbox_token'] . '). Enter a new one only to replace it.' : 'MSG91 dashboard → API → Auth Key.'" />
                        <x-field label="Production auth key" name="whatsapp_production_token" type="password"
                                 :required="false"
                                 x-bind:required="env === 'production' && {{ $whatsapp['has_production_token'] ? 'false' : 'true' }}"
                                 :placeholder="$whatsapp['has_production_token'] ? 'Saved — leave blank to keep' : 'MSG91 auth key'"
                                 :hint="$whatsapp['has_production_token'] ? 'Stored encrypted (' . $whatsapp['masked_production_token'] . '). Enter a new one only to replace it.' : 'Add once Sandbox is verified working.'" />
                    </div>

                    <div class="border-t border-line-soft pt-4 space-y-3">
                        <p class="text-[12.5px] font-medium text-ink">Template &amp; number</p>
                        <p class="text-[11.5px] text-ink-3 -mt-2">
                            Not secret — visible on the MSG91 dashboard already, and the same for both environments.
                        </p>

                        <x-field label="Integrated WhatsApp number" name="whatsapp_integrated_number" required
                                 :value="$whatsapp['integrated_number']"
                                 placeholder="e.g. 919876543210"
                                 hint="The business's own WhatsApp number registered with MSG91, with country code." />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Template name" name="whatsapp_template_name" required
                                     :value="$whatsapp['template_name']"
                                     placeholder="e.g. otp_login"
                                     hint="Must be an approved Authentication-category template." />
                            <x-field label="Template language code" name="whatsapp_template_language" required
                                     :value="$whatsapp['template_language']"
                                     placeholder="en" />
                        </div>

                        <x-field label="Template namespace" name="whatsapp_template_namespace"
                                 :value="$whatsapp['template_namespace']"
                                 placeholder="Optional — only if your MSG91 account requires one"
                                 hint="Leave blank unless MSG91's own template page shows a namespace to copy." />
                    </div>

                    {{-- ---------------------------- Credentials template (optional) ---------------------------- --}}
                    <div class="border-t border-line-soft pt-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <p class="text-[12.5px] font-medium text-ink">Credentials template</p>
                            <span @class([
                                'text-[10.5px] font-semibold px-1.5 py-0.5 rounded-badge',
                                'bg-success-soft text-success' => $whatsapp['credentials_configured'],
                                'bg-line-soft text-ink-3' => ! $whatsapp['credentials_configured'],
                            ])>{{ $whatsapp['credentials_configured'] ? 'Connected' : 'Optional — not set' }}</span>
                        </div>
                        <p class="text-[11.5px] text-ink-3 -mt-2 leading-relaxed">
                            A second, separate template — same auth key and number above, but its own
                            approval — used to send a new developer or channel partner their sign-in
                            email and password over WhatsApp. Sending a password needs a
                            <strong class="text-ink">Utility</strong>-category template (Meta's Authentication
                            category above is locked to a bare code and can't carry it). Leave this blank
                            until that template is approved — credentials still go out by email either way.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Template name" name="whatsapp_credentials_template_name"
                                     :value="$whatsapp['credentials_template_name']"
                                     placeholder="e.g. account_credentials"
                                     hint="Must be an approved Utility-category template with 3 body variables: name, email, password (in that order)." />
                            <x-field label="Template language code" name="whatsapp_credentials_template_language"
                                     :value="$whatsapp['credentials_template_language']"
                                     placeholder="en" />
                        </div>

                        <x-field label="Template namespace" name="whatsapp_credentials_template_namespace"
                                 :value="$whatsapp['credentials_template_namespace']"
                                 placeholder="Optional — only if your MSG91 account requires one" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="What this needs" padded class="self-start">
                <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>A WhatsApp Business Account verified on the MSG91 dashboard, with its own number.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>An <strong class="text-ink">Authentication</strong>-category template approved by Meta — WhatsApp's own rule for OTP messages, the same for every provider.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Only mobile-number sign-in uses this. Email sign-in still goes through Mailjet, unaffected.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>For credentials over WhatsApp: a second, <strong class="text-ink">Utility</strong>-category template — a separate submission on the same dashboard.</span>
                    </li>
                </ul>
                <p class="text-[12px] text-ink-3 mt-4 pt-3 border-t border-line-soft leading-relaxed">
                    Which one a broker sees is set on the Access tab's Channel Partner login method.
                </p>
            </x-panel>
        </div>

        {{-- ---------------------------- Master Data (irecexpo.com) ---------------------------- --}}
        <div x-show="tab === 'master-data'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="Master Data API"
                     subtitle="Feeds the Master Data page — developer/project registrations from irecexpo.com"
                     padded class="xl:col-span-2">

                <div @class([
                    'flex items-start gap-2.5 px-3.5 py-3 mb-5 border',
                    'bg-success-soft border-success-ring' => $masterData['configured'],
                    'bg-warning-soft border-warning-ring' => ! $masterData['configured'],
                ])>
                    <x-icon :name="$masterData['configured'] ? 'check' : 'clock'"
                            class="w-4 h-4 shrink-0 mt-px {{ $masterData['configured'] ? 'text-success' : 'text-warning' }}" />
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium {{ $masterData['configured'] ? 'text-success' : 'text-warning' }}">
                            {{ $masterData['configured'] ? 'Connected' : 'Not configured' }}
                        </p>
                        <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">
                            @if($masterData['configured'])
                                An API key is saved. The Master Data page can fetch registrations.
                            @else
                                Until an API key is saved below, the Master Data page can't load anything.
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.master-data') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <x-field label="API base URL" name="master_data_base_url" required
                             :value="$masterData['base_url']"
                             hint="The vendor's own endpoint — only change this if they move it." />

                    <x-field label="API key" name="master_data_api_key" type="password"
                             :required="false"
                             :placeholder="$masterData['has_key'] ? 'Saved — leave blank to keep' : 'irec_sec_…'"
                             :hint="$masterData['has_key'] ? 'Stored encrypted (' . $masterData['masked_key'] . '). Enter a new one only to replace it.' : 'Sent as the X-API-KEY header on every request.'" />

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="What this needs" padded class="self-start">
                <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>An API key issued by irecexpo.com — sent as <code class="text-[11.5px]">X-API-KEY</code> on every request.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Converting a registration creates a real Developer account and emails/WhatsApps its sign-in details — same delivery as adding one by hand.</span>
                    </li>
                </ul>
            </x-panel>
        </div>

        {{-- ---------------------------- Maps ---------------------------- --}}
        <div x-show="tab === 'maps'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="Google Maps"
                     subtitle="Powers the mobile app's &quot;Choose from Map&quot; location picker"
                     padded class="xl:col-span-2">

                <div @class([
                    'flex items-start gap-2.5 px-3.5 py-3 mb-5 border',
                    'bg-success-soft border-success-ring' => $googleMaps['configured'],
                    'bg-warning-soft border-warning-ring' => ! $googleMaps['configured'],
                ])>
                    <x-icon :name="$googleMaps['configured'] ? 'check' : 'clock'"
                            class="w-4 h-4 shrink-0 mt-px {{ $googleMaps['configured'] ? 'text-success' : 'text-warning' }}" />
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium {{ $googleMaps['configured'] ? 'text-success' : 'text-warning' }}">
                            {{ $googleMaps['configured'] ? 'Key saved' : 'Not configured' }}
                        </p>
                        <p class="text-[12.5px] text-ink-2 mt-0.5 leading-relaxed">
                            @if($googleMaps['configured'])
                                Saved here for the app to read, and encrypted the same way the KYC tokens
                                above are. Android's map view still needs this copied into the mobile
                                project's Android build config and the app rebuilt — saving it here alone
                                does not update an already-installed app.
                            @else
                                Until a key is saved, the map screen won't render on Android (iOS uses
                                Apple Maps and needs no key at all).
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.settings.google-maps') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    {{-- Blank means keep — same reasoning as the KYC tokens: the stored
                         key is encrypted and never rendered back. --}}
                    <x-field label="Maps API key" name="google_maps_api_key" type="password"
                             :required="! $googleMaps['configured']"
                             :placeholder="$googleMaps['configured'] ? 'Saved — leave blank to keep' : 'AIza…'"
                             :hint="$googleMaps['configured'] ? 'Stored encrypted (' . $googleMaps['masked'] . '). Enter a new one only to replace it.' : 'Google Cloud Console → APIs &amp; Services → Credentials — create a key restricted to Maps SDK for Android.'" />

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Why a rebuild is needed" padded class="self-start">
                <ul class="space-y-2.5 text-[12.5px] text-ink-2 leading-relaxed">
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Android's native Google Maps SDK reads its API key from the compiled app,
                            before any of the app's own code runs — there's no way for it to fetch a
                            fresh value at that point.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>Saving a key here still needs someone to copy it into the mobile project
                            and rebuild the Android app before it takes effect — this page is where the
                            key lives, not a way around that step.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-primary">&bull;</span>
                        <span>iOS needs nothing here at all — the map screen uses Apple's own maps on
                            iOS, which ships with no API key.</span>
                    </li>
                </ul>
                <p class="text-[12px] text-ink-3 mt-4 pt-3 border-t border-line-soft leading-relaxed">
                    Restrict the key in Google Cloud Console to the Maps SDK for Android, scoped to this
                    app's package name and signing certificate (SHA-1) — an unrestricted key billed to
                    this project is usable by anyone who finds it.
                </p>
            </x-panel>
        </div>

        <div x-show="tab === 'access'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="Admin account" subtitle="The platform runs on a single admin login" padded class="xl:col-span-2">
                <dl class="space-y-3.5 max-w-md">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">Name</dt>
                        <dd class="text-[13px] text-ink">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-3.5 border-t border-line-soft">
                        <dt class="text-[12.5px] text-ink-3">Email (login)</dt>
                        <dd class="text-[13px] text-ink">{{ auth()->user()->email }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-3.5 border-t border-line-soft">
                        <dt class="text-[12.5px] text-ink-3">Last sign-in</dt>
                        <dd class="text-[13px] text-ink nums">
                            {{ auth()->user()->last_login_at?->format('d M Y, H:i') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </x-panel>

            {{-- Channel partner sign-in method -------------------------------
                 Both flows (email OTP, mobile OTP) are fully built server- and
                 app-side either way — this only decides which one a channel
                 partner without an account yet lands on from the mobile app's
                 Welcome screen. See ConfigController for how the app reads it. --}}
            <x-panel title="Channel partner sign-in method"
                     subtitle="Which screen a channel partner sees first, before any account exists"
                     padded class="xl:col-span-2">
                {{-- Submitted like every other form on this page — via the generic
                     fetch interceptor in app.js, not a real navigation. This page's
                     `tab` is client-side-only Alpine state, so a plain form submit's
                     redirect-and-reload silently threw the admin back to the Form
                     fields tab after every save here. --}}
                <form method="POST" action="{{ route('admin.settings.cp-login-method') }}"
                      x-data="{ picked: '{{ $cpLoginMethod }}' }">
                    @csrf @method('PATCH')
                    <input type="hidden" name="cp_login_method" :value="picked">

                    <div class="flex flex-wrap gap-2.5">
                        <button type="button" @click="picked = 'email'"
                                :class="picked === 'email' ? 'border-nav bg-canvas' : 'border-line hover:border-ink-3'"
                                class="group flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 transition-colors">
                            <x-icon name="mail" class="w-4 h-4 text-ink-2 shrink-0" />
                            <span class="text-[12.5px] font-medium text-ink">Email + OTP</span>
                            <x-icon name="check" class="w-4 h-4 text-primary-dark ml-1"
                                    x-show="picked === 'email'" x-cloak />
                        </button>
                        <button type="button" @click="picked = 'mobile'"
                                :class="picked === 'mobile' ? 'border-nav bg-canvas' : 'border-line hover:border-ink-3'"
                                class="group flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 transition-colors">
                            <x-icon name="phone" class="w-4 h-4 text-ink-2 shrink-0" />
                            <span class="text-[12.5px] font-medium text-ink">Mobile number + OTP</span>
                            <x-icon name="check" class="w-4 h-4 text-primary-dark ml-1"
                                    x-show="picked === 'mobile'" x-cloak />
                        </button>
                    </div>

                    <div class="mt-5 pt-4 border-t border-line-soft flex flex-wrap items-center justify-between gap-3">
                        <p class="text-[12px] text-ink-3">Applies the next time the app is opened.</p>
                        <x-button variant="gold" tag="button" type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>

            {{-- Company document sharing -------------------------------------
                 An individual's PAN/RERA/GST always has to be unique (enforced
                 unconditionally in AuthController::documentSharingRule()) — this
                 only controls the exception for a *company* registration, where the
                 same PAN/RERA/GST legitimately belongs to more than one person at
                 that company. --}}
            @can('manage-team')
            <x-panel title="Company document sharing"
                     subtitle="How many channel partner accounts may register as a company using the same PAN, RERA, or GST number"
                     padded class="xl:col-span-2">
                <form method="POST" action="{{ route('admin.settings.document-sharing') }}" class="space-y-3">
                    @csrf @method('PATCH')

                    <div class="max-w-xs">
                        <x-field label="Maximum accounts per document" name="document_share_limit"
                                 type="number" min="1" max="50"
                                 :value="old('document_share_limit', $documentShareLimit)"
                                 hint="1 = no sharing at all — a company's documents must be as unique as an individual's, same as today." />
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button variant="gold" size="sm" tag="button" type="submit" icon="check">Save</x-button>
                    </div>
                </form>
            </x-panel>
            @endcan

            {{-- Firebase service account ----------------------------------- --}}
            @can('manage-team')
            <x-panel title="Push notifications"
                     subtitle="The Firebase service account this server sends with"
                     padded class="xl:col-span-2">

                {{-- Sending itself lives on its own page now — see the note on
                     AnnouncementController. This panel is credential setup only. --}}
                <a href="{{ route('admin.push-notifications') }}"
                   class="flex items-center gap-1.5 text-[12px] text-primary-dark hover:underline mb-4">
                    <x-icon name="bell" class="w-3.5 h-3.5" />
                    Go to Push Notifications to send one
                    <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                </a>

                @if($firebase['configured'])
                    <div class="flex items-start gap-3 bg-success-soft ring-1 ring-inset ring-success-ring px-4 py-3 mb-4">
                        <x-icon name="check" class="w-4 h-4 text-success shrink-0 mt-0.5" />
                        <div class="min-w-0">
                            <p class="text-[12.5px] font-medium text-ink">Connected to {{ $firebase['project_id'] }}</p>
                            <p class="text-[11.5px] text-ink-2 mt-0.5 truncate">{{ $firebase['client_email'] }}</p>
                            @if($firebase['uploaded_at'])
                                <p class="text-[11px] text-ink-3 mt-0.5">
                                    Uploaded {{ \Carbon\Carbon::createFromTimestamp($firebase['uploaded_at'])->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-3 bg-warning-soft ring-1 ring-inset ring-warning-ring px-4 py-3 mb-4">
                        <x-icon name="lock" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                        <div>
                            <p class="text-[12.5px] font-medium text-ink">No service account saved</p>
                            <p class="text-[11.5px] text-ink-2 mt-0.5 leading-relaxed">
                                Notifications will not send until one is uploaded. The key is
                                deliberately kept out of the repository, so it does not arrive
                                with a deploy — upload it here once per environment.
                            </p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.firebase') }}"
                      enctype="multipart/form-data" class="space-y-3">
                    @csrf

                    <div>
                        <label for="credentials" class="block text-[12.5px] font-medium text-ink mb-1.5">
                            Service account JSON
                        </label>
                        <input type="file" id="credentials" name="credentials" accept=".json,application/json"
                               class="block w-full text-[12.5px] text-ink-2
                                      file:mr-3 file:py-2 file:px-3.5 file:border-0
                                      file:text-[12.5px] file:font-medium
                                      file:bg-primary-soft file:text-primary-dark
                                      hover:file:bg-primary-ring file:cursor-pointer
                                      border border-line bg-panel py-1.5 pr-3
                                      focus:outline-none focus:border-primary-ring transition-colors">
                        @error('credentials')
                            <p class="text-[11.5px] text-danger mt-1.5">{{ $message }}</p>
                        @enderror
                        <p class="text-[11.5px] text-ink-3 mt-1.5 leading-relaxed">
                            Firebase Console → Project Settings → Service accounts → Generate new
                            private key. Not <span class="font-medium text-ink-2">google-services.json</span> —
                            that one ships inside the app, this one stays on the server.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <x-button variant="gold" size="sm" tag="button" type="submit" icon="check">
                            {{ $firebase['configured'] ? 'Replace key' : 'Upload key' }}
                        </x-button>
                    </div>
                </form>

                @if($firebase['configured'])
                    <div class="flex flex-wrap items-center gap-2.5 mt-3 pt-3 border-t border-line-soft">
                        <form method="POST" action="{{ route('admin.settings.firebase.test') }}">
                            @csrf
                            <x-button variant="outline" size="sm" tag="button" type="submit" icon="bell">
                                Test connection
                            </x-button>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.firebase.forget') }}"
                              data-confirm
                              x-on:submit.prevent="$dispatch('confirm-request', {
                                  title: 'Remove the service account?',
                                  message: 'Push notifications stop immediately until a key is uploaded again.',
                                  confirmLabel: 'Remove',
                                  tone: 'danger',
                                  form: $el,
                              })">
                            @csrf @method('DELETE')
                            <x-button variant="outline" size="sm" tag="button" type="submit" icon="x">
                                Remove
                            </x-button>
                        </form>

                        <p class="text-[11px] text-ink-3 ml-auto">
                            Stored outside the public directory and never shown back.
                        </p>
                    </div>
                @endif
            </x-panel>
            @endcan

            <x-panel title="Session" padded class="self-start">
                <p class="text-[12.5px] text-ink-2 leading-relaxed mb-4">
                    Signing out ends this browser session. Mobile tokens are unaffected.
                </p>
                {{-- data-no-ajax: signing out has to be a real navigation — it ends the
                     session, and an AJAX POST here would just leave the admin looking
                     at a page that thinks it's still logged in. --}}
                <form method="POST" action="{{ route('logout') }}" data-no-ajax>
                    @csrf
                    <x-button variant="outline" tag="button" type="submit" icon="logout" class="w-full">Sign out</x-button>
                </form>
            </x-panel>
        </div>
    </div>
