@php
$themeColors = [
    ['name' => 'Gold',      'hex' => '#C9A227'],
    ['name' => 'Deep Navy', 'hex' => '#28345C'],
    ['name' => 'Emerald',   'hex' => '#1F9254'],
    ['name' => 'Burgundy',  'hex' => '#8C2F39'],
    ['name' => 'Teal',      'hex' => '#0F7C82'],
];

$formTitles = [
    'broker_registration' => 'Broker Registration',
    'property_listing' => 'Project Listing',
];

$tabs = [
    ['key' => 'forms',  'label' => 'Form fields'],
    ['key' => 'brand',  'label' => 'Branding'],
    ['key' => 'access', 'label' => 'Access'],
];
@endphp

<x-layouts.admin active="settings" title="Settings" section="Configure">

    <x-page-header
        title="Settings"
        subtitle="Control what appears on registration and listing forms, and how the mobile apps are branded." />


    <div x-data="{ tab: 'forms' }">
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
                                                    'inline-flex items-center shrink-0 w-[38px] h-[21px] rounded-full px-[2px] transition-colors',
                                                    'bg-primary' => $field->enabled,
                                                    'bg-line' => ! $field->enabled,
                                                    'opacity-50 cursor-not-allowed' => $field->is_core,
                                                    'cursor-pointer' => ! $field->is_core,
                                                ])>
                                            <span @class([
                                                'block w-[17px] h-[17px] rounded-full bg-white shadow-sm transition-transform duration-150',
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
        <div x-show="tab === 'brand'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <x-panel title="App accent colour"
                     subtitle="Sets the accent across the Broker and Developer mobile apps"
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
                        <span class="text-white text-[12.5px] font-medium">iREC Broker</span>
                    </div>
                    <div class="h-2 w-2/3 rounded-full bg-nav-active mb-2"></div>
                    <div class="h-2 w-1/2 rounded-full bg-nav-soft mb-4"></div>
                    <div class="h-8 rounded-lg flex items-center justify-center text-white text-[11.5px] font-semibold"
                         style="background-color: {{ $accentColor }}">
                        Submit for approval
                    </div>
                </div>
            </x-panel>
        </div>

        {{-- ---------------------------- Access ---------------------------- --}}
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
