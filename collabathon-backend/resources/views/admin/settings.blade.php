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

$tabs = [
    ['key' => 'forms',     'label' => 'Form fields'],
    ['key' => 'locations', 'label' => 'Locations'],
    ['key' => 'project-types', 'label' => 'Project types'],
    ['key' => 'brand',     'label' => 'Branding'],
    ['key' => 'email',     'label' => 'Email'],
    ['key' => 'access',    'label' => 'Access'],
];

// LocationController redirects back with ?tab=locations so a save reopens this tab
// instead of dropping the admin on Form fields. Whitelisted against $tabs so the query
// string cannot select a tab that does not exist.
$openTab = in_array(request()->query('tab'), array_column($tabs, 'key'), true)
    ? request()->query('tab')
    : 'forms';
@endphp

<x-layouts.admin active="settings" title="Settings" section="Configure">

    <x-page-header
        title="Settings"
        subtitle="Control what appears on registration and listing forms, and how the mobile apps are branded." />


    <div x-data="{ tab: '{{ $openTab }}' }">
        <x-tab-bar :tabs="$tabs" model="tab" class="mb-5" />

        {{-- ---------------------------- Form fields ---------------------------- --}}
        <div x-show="tab === 'forms'" class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 space-y-4">
                @foreach($fieldGroups as $form => $fields)
                    <x-panel :title="$formTitles[$form] ?? $form"
                             :subtitle="'Shown on the ' . strtolower($formTitles[$form] ?? $form) . ' form in the mobile app'"
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

                                    {{-- Real POST, not a local toggle — the mobile app reads this. --}}
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
                    <p>Disabling a field hides it from the mobile app immediately — existing records keep any data already captured.</p>
                    <p><span class="font-medium text-ink">Core</span> fields cannot be disabled: the registration flow depends on them.</p>
                    <div class="rounded-lg bg-canvas ring-1 ring-inset ring-line px-3.5 py-3">
                        <p class="text-[12px] text-ink-3">
                            Changes apply to <span class="font-medium text-ink">new submissions only</span>.
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

                            <form method="POST" action="{{ route('admin.settings.locations.countries.destroy', $country) }}"
                                  x-data
                                  @submit.prevent="if (confirm('Delete “{{ $country->name }}”? Its states and cities go too.')) $el.submit()">
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
                    <form method="POST" action="{{ route('admin.settings.locations.countries.store') }}"
                          class="flex items-end gap-2">
                        @csrf
                        <div class="flex-1"><x-field label="Country" name="name" placeholder="e.g. United Arab Emirates" /></div>
                        <div class="w-20"><x-field label="Code" name="code" placeholder="AE" /></div>
                        <x-button variant="primary" tag="button" type="submit" icon="plus" class="mb-3">Add</x-button>
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

                            <form method="POST" action="{{ route('admin.settings.locations.states.destroy', $state) }}"
                                  x-data
                                  @submit.prevent="if (confirm('Delete “{{ $state->name }}”? Its cities go too.')) $el.submit()">
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
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="mb-3">Add</x-button>
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

                            <form method="POST" action="{{ route('admin.settings.locations.cities.destroy', $city) }}"
                                  x-data
                                  @submit.prevent="if (confirm('Delete “{{ $city->name }}”?')) $el.submit()">
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
                            <x-button variant="primary" tag="button" type="submit" icon="plus" class="mb-3">Add</x-button>
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
                    <span class="text-[11.5px] text-ink-3 nums">{{ $projectTypes->count() }} total</span>
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
                                    <form method="POST" action="{{ route('admin.settings.project-types.update', $type) }}"
                                          id="type-form-{{ $type->id }}">
                                        @csrf @method('PATCH')
                                    </form>

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
                                        <input form="type-form-{{ $type->id }}" name="sort_order" type="number" min="0" max="65535"
                                               value="{{ $type->sort_order }}"
                                               class="w-full h-9 px-2 rounded-lg bg-panel border border-line text-[13px] text-ink nums
                                                      focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-ring">
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

        <div x-show="tab === 'brand'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="App accent colour"
                     subtitle="Sets the accent across the Channel Partner and Developer mobile apps"
                     padded class="xl:col-span-2">
                <form method="POST" action="{{ route('admin.settings.theme') }}" x-data="{ picked: '{{ $accentColor }}' }">
                    @csrf @method('PATCH')
                    <input type="hidden" name="accent_color" :value="picked">

                    <div class="flex flex-wrap gap-2.5">
                        @foreach($themeColors as $color)
                            <button type="button" @click="picked = '{{ $color['hex'] }}'"
                                    :class="picked === '{{ $color['hex'] }}' ? 'border-nav bg-canvas' : 'border-line hover:border-ink-3'"
                                    class="group flex items-center gap-2.5 rounded-xl border px-3 py-2.5 transition-colors">
                                <span class="w-7 h-7 rounded-lg shrink-0 ring-1 ring-inset ring-line"
                                      style="background-color: {{ $color['hex'] }}"></span>
                                <span class="text-left">
                                    <span class="block text-[12.5px] font-medium text-ink">{{ $color['name'] }}</span>
                                    <span class="block text-[11px] text-ink-3 nums uppercase">{{ $color['hex'] }}</span>
                                </span>
                                <x-icon name="check" class="w-4 h-4 text-primary-dark ml-1"
                                        x-show="picked === '{{ $color['hex'] }}'" x-cloak />
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-5 pt-4 border-t border-line-soft flex flex-wrap items-center justify-between gap-3">
                        <p class="text-[12px] text-ink-3">Applies on the next app launch for every user.</p>
                        <x-button variant="gold" tag="button" type="submit" icon="check">Save theme</x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Preview" padded class="self-start">
                <div class="rounded-xl bg-nav p-4">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-[10.5px] font-bold"
                              style="background-color: {{ $accentColor }}">iR</span>
                        <span class="text-white text-[12.5px] font-medium">iREC Channel Partner</span>
                    </div>
                    <div class="h-2 w-2/3 bg-nav-active mb-2"></div>
                    <div class="h-2 w-1/2 bg-nav-soft mb-4"></div>
                    <div class="h-8 rounded-lg flex items-center justify-center text-white text-[11.5px] font-semibold"
                         style="background-color: {{ $accentColor }}">
                        Submit for approval
                    </div>
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

            {{-- Firebase service account ----------------------------------- --}}
            @can('manage-team')
            <x-panel title="Push notifications"
                     subtitle="The Firebase service account this server sends with"
                     padded class="xl:col-span-2">

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

            {{-- Push announcement ------------------------------------------ --}}
            <x-panel title="Send a push notification"
                     subtitle="Reaches everyone in the audience who has the app installed"
                     padded class="xl:col-span-2">
                <form method="POST" action="{{ route('admin.settings.announce') }}" class="space-y-3"
                      x-on:submit.prevent="$dispatch('confirm-request', {
                          title: 'Send this to every device?',
                          message: 'Push notifications cannot be recalled once sent.',
                          confirmLabel: 'Send',
                          form: $el,
                      })">
                    @csrf

                    <x-select-field label="Audience" name="audience"
                                    :options="['brokers' => 'Channel partners', 'developers' => 'Developers', 'everyone' => 'Everyone']"
                                    :value="old('audience', 'everyone')" />

                    <x-field label="Title" name="title" :value="old('title')"
                             placeholder="New projects are live"
                             hint="Under 60 characters — Android truncates past that." />

                    <x-field label="Message" name="body" type="textarea" rows="2" :value="old('body')"
                             placeholder="Six new Dubai listings were added this week."
                             hint="Under 180 characters so it reads without expanding." />

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <p class="text-[11.5px] text-ink-3">
                            Lifecycle notifications — approvals, requests, decisions — send
                            automatically and are not affected by this.
                        </p>
                        <x-button variant="gold" size="sm" tag="button" type="submit" icon="bell">
                            Send
                        </x-button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Session" padded class="self-start">
                <p class="text-[12.5px] text-ink-2 leading-relaxed mb-4">
                    Signing out ends this browser session. Mobile tokens are unaffected.
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="outline" tag="button" type="submit" icon="logout" class="w-full">Sign out</x-button>
                </form>
            </x-panel>
        </div>
    </div>
</x-layouts.admin>
