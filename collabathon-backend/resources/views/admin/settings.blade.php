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
    'broker_registration' => 'Broker Registration',
    'property_listing' => 'Project Listing',
];

$tabs = [
    ['key' => 'forms',  'label' => 'Form fields'],
    ['key' => 'brand',  'label' => 'Branding'],
    ['key' => 'email',  'label' => 'Email'],
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
                     subtitle="Used to email approved brokers their sign-in details"
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
                                <span class="nums">{{ $mail['masked_key'] }}</span>. Approving a broker emails them
                                automatically.
                            @else
                                Until a key is saved here, approving a broker changes their access but sends no email.
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
                            <span>A broker is <strong class="text-ink">approved</strong> — they get their sign-in details.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-primary">&bull;</span>
                            <span>A broker's password is <strong class="text-ink">reset</strong> — the new one is emailed to them.</span>
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
