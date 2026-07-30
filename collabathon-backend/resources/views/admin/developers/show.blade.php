@php
    // A failed edit redirects back here, so the dialog reopens with what was typed rather
    // than silently discarding it — `_form` marks the edit form as the source.
    $editReopen = $errors->any() && old('_form') === 'developer-edit';

    $sharePayload = \Illuminate\Support\Js::from([
        'name' => $developer->contact_person,
        'email' => $developer->user?->email ?? $developer->email,
        // Lets the share dialog offer "set a password" in one step.
        'resetAction' => $developer->user ? route('admin.developers.password', $developer) : null,
    ]);
    $resetPayload = \Illuminate\Support\Js::from([
        'name' => $developer->company_name,
        'action' => route('admin.developers.password', $developer),
    ]);
    $deletePayload = \Illuminate\Support\Js::from([
        'title' => 'Delete this developer?',
        'message' => trim(
            "{$developer->company_name}, its login account"
            . ($cascades['listings'] ? ", {$cascades['listings']} listing" . ($cascades['listings'] === 1 ? '' : 's') : '')
            . ($cascades['leads'] ? " and {$cascades['leads']} lead" . ($cascades['leads'] === 1 ? '' : 's') : '')
            . ' will be permanently deleted. This cannot be undone.'
        ),
        'confirmLabel' => 'Delete developer',
        'tone' => 'danger',
    ]);

    $payout = rtrim(rtrim(number_format((float) $developer->cp_payout_percent, 2), '0'), '.');
@endphp

<x-layouts.admin active="developers" :title="$developer->company_name" section="Manage">

    <a href="{{ route('admin.developers') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to developers
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 min-w-0">
            @if($developer->logo_path)
                <img src="{{ Storage::disk('public')->url($developer->logo_path) }}" alt=""
                     class="w-14 h-14 rounded-xl object-cover border border-line-soft shrink-0">
            @else
                <x-avatar :name="$developer->company_name" size="lg" class="w-14 h-14 shrink-0" />
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[19px] sm:text-[21px] font-semibold text-ink tracking-[-0.02em] leading-tight">
                        {{ $developer->company_name }}
                    </h1>
                    @if($developer->verified)
                        <x-badge tone="primary" size="sm">
                            <x-icon name="shield" class="w-3 h-3" /> Verified
                        </x-badge>
                    @endif
                    <x-badge :tone="$developer->status === 'active' ? 'success' : 'neutral'" size="sm" dot>
                        {{ ucfirst($developer->status) }}
                    </x-badge>
                </div>
                <p class="text-[13px] text-ink-2 mt-1">
                    {{ $developer->contact_person }}
                    @if($developer->city) · {{ $developer->city }}@endif
                    · Added {{ $developer->created_at->format('d M Y') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <x-button variant="outline" icon="external" tag="button" type="button"
                      x-on:click="$dispatch('share-credentials', {{ $sharePayload }})">
                Share
            </x-button>
            <x-button variant="gold" icon="cog" tag="button" type="button" x-on:click="$dispatch('open-developer-edit')">
                Edit
            </x-button>

            <x-dropdown>
                <x-slot:trigger>
                    <button type="button" aria-label="More actions"
                            class="h-9 w-9 flex items-center justify-center rounded-lg bg-panel border border-line
                                   text-ink-2 hover:text-ink hover:bg-canvas transition-colors">
                        <x-icon name="dots" class="w-4 h-4" />
                    </button>
                </x-slot:trigger>

                <x-dropdown-item icon="eye" tag="a" href="{{ route('admin.properties', ['developer_id' => $developer->id]) }}">
                    View listings
                </x-dropdown-item>

                @if($developer->user)
                    <x-dropdown-item icon="lock" tag="button" type="button"
                                     x-on:click="$dispatch('reset-password', {{ $resetPayload }}); close()">
                        Reset password
                    </x-dropdown-item>
                @endif

                <form method="POST" action="{{ route('admin.developers.update', $developer) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $developer->status === 'active' ? 'paused' : 'active' }}">
                    <x-dropdown-item :icon="$developer->status === 'active' ? 'x' : 'check'"
                                     tag="button" type="submit">
                        {{ $developer->status === 'active' ? 'Pause account' : 'Reactivate account' }}
                    </x-dropdown-item>
                </form>

                <div class="my-1 border-t border-line-soft"></div>

                <form method="POST" action="{{ route('admin.developers.destroy', $developer) }}"
                      x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el }); close()">
                    @csrf @method('DELETE')
                    <x-dropdown-item icon="x" tone="danger" tag="button" type="submit">
                        Delete developer
                    </x-dropdown-item>
                </form>
            </x-dropdown>
        </div>
    </div>

    {{-- ============================== Activity ============================== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="list" label="Listings" :value="$stats['listings']" />
        <x-stat-card icon="check" label="Active listings" :value="$stats['active']" />
        <x-stat-card icon="eye" label="Total views" :value="number_format($stats['views'])" />
        <x-stat-card icon="users" label="Leads" :value="$stats['leads']" />
    </div>

    {{-- ============================== Details ============================== --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="xl:col-span-2 space-y-4">
            <x-panel title="Company" flush>
                <dl class="divide-y divide-line-soft">
                    @foreach([
                        'Company name' => $developer->company_name,
                        'Contact person' => $developer->contact_person,
                        'Mobile' => $developer->mobile,
                        'City' => $developer->city,
                        'State / Emirate' => $developer->state,
                        'RERA / licence' => $developer->rera_number,
                    ] as $label => $value)
                        <div class="px-5 py-3 flex items-start gap-4">
                            <dt class="text-[12.5px] text-ink-3 w-[150px] shrink-0">{{ $label }}</dt>
                            <dd class="text-[13px] text-ink min-w-0 break-words">
                                {{ $value ?: '—' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-panel>

            <x-panel title="About the company" padded>
                <p class="text-[13px] text-ink-2 leading-relaxed whitespace-pre-line">
                    {{ $developer->about ?: 'No description added yet.' }}
                </p>
            </x-panel>
        </div>

        <div class="space-y-4">
            <x-panel title="Login account" flush>
                <dl class="divide-y divide-line-soft">
                    <div class="px-5 py-3">
                        <dt class="text-[12.5px] text-ink-3">Email / username</dt>
                        <dd class="text-[13px] text-ink font-mono break-all mt-0.5">
                            {{ $developer->user?->email ?? $developer->email ?? '—' }}
                        </dd>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-[12.5px] text-ink-3">Account status</dt>
                        <dd class="mt-1">
                            @if($developer->user)
                                <x-badge :tone="$developer->user->status === 'active' ? 'success' : 'neutral'" size="sm" dot>
                                    {{ ucfirst($developer->user->status) }}
                                </x-badge>
                            @else
                                <span class="text-[13px] text-ink-3">No login account</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($developer->user)
                    <div class="px-5 py-3 border-t border-line-soft">
                        <p class="text-[11.5px] text-ink-3 leading-relaxed">
                            Passwords are stored hashed and cannot be shown again — use
                            <span class="font-medium text-ink-2">Reset password</span> to issue a new one.
                        </p>
                    </div>
                @endif
            </x-panel>

            <x-panel title="Commercial" flush>
                <dl class="divide-y divide-line-soft">
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">CP payout</dt>
                        <dd class="text-[15px] font-semibold text-ink nums">{{ $payout }}%</dd>
                    </div>
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">Verified badge</dt>
                        <dd>
                            <x-badge :tone="$developer->verified ? 'primary' : 'neutral'" size="sm">
                                {{ $developer->verified ? 'Shown' : 'Hidden' }}
                            </x-badge>
                        </dd>
                    </div>
                    <div class="px-5 py-3 flex items-center justify-between gap-4">
                        <dt class="text-[12.5px] text-ink-3">Last updated</dt>
                        <dd class="text-[12.5px] text-ink-2 nums">{{ $developer->updated_at->format('d M Y') }}</dd>
                    </div>
                </dl>
            </x-panel>
        </div>
    </div>

    {{-- ============================== Edit dialog ============================== --}}
    <div x-data="{ open: @js($editReopen) }"
         x-on:open-developer-edit.window="open = true"
         x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[55] flex items-start justify-center px-4 py-10 overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="dev-edit-title">

        <div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-scrim"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative bg-panel rounded-2xl w-full max-w-2xl shadow-modal my-auto">

            <header class="px-6 py-4 border-b border-line flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 id="dev-edit-title" class="text-[15px] font-semibold text-ink tracking-[-0.01em]">Edit developer</h3>
                    <p class="text-[12.5px] text-ink-3 mt-0.5">
                        Changing the email changes the login this developer signs in with.
                    </p>
                </div>
                <button type="button" @click="open = false" aria-label="Close dialog"
                        class="text-ink-3 hover:text-ink hover:bg-canvas rounded-lg p-1.5 -m-1 transition-colors shrink-0">
                    <x-icon name="x" class="w-4.5 h-4.5" />
                </button>
            </header>

            {{-- submit(), not requestSubmit(): see the note on the create form. --}}
            <form method="POST" action="{{ route('admin.developers.update', $developer) }}"
                  enctype="multipart/form-data" class="px-6 py-5 space-y-5"
                  x-data="{ busy: false }"
                  x-on:submit="
                      $event.preventDefault();
                      busy = true;
                      Promise.resolve(window.compressFileInputs?.($el))
                          .catch((error) => console.error('Logo compression failed; uploading original.', error))
                          .finally(() => $el.submit());
                  ">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="developer-edit">

                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Company name" name="company_name" :value="$developer->company_name" required />
                        <x-field label="Contact person" name="contact_person" :value="$developer->contact_person" required />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Mobile number" name="mobile" :value="$developer->mobile" icon="phone" required />
                        <x-field label="Email" name="email" type="email" icon="mail" required
                                 :value="$developer->user?->email ?? $developer->email"
                                 hint="This is their login." />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="City" name="city" :value="$developer->city" icon="map-pin" required />
                        <x-field label="State / Emirate" name="state" :value="$developer->state" />
                    </div>
                </div>

                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="RERA / licence number" name="rera_number" :value="$developer->rera_number"
                                 hint="Shown to brokers as a trust signal." />
                        <x-file-field label="Replace logo" name="logo" accept="image/*"
                                      hint="Leave empty to keep the current logo." />
                    </div>

                    <x-field label="About the company" name="about" type="textarea" rows="3" :value="$developer->about" />
                </div>

                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="CP payout %" name="cp_payout_percent" type="number" step="0.01" required
                                 :value="$developer->cp_payout_percent"
                                 hint="Commission paid to the channel partner." />
                        <x-select-field label="Status" name="status" :selected="$developer->status"
                                        :options="['active' => 'Active', 'paused' => 'Paused']" />
                    </div>

                    <x-switch-field label="Verified developer" name="verified" :checked="$developer->verified"
                                    hint="Adds a verified badge on every listing this developer owns." />
                </div>

                <div class="pt-1 flex items-center justify-end gap-2.5">
                    <button type="button" @click="open = false"
                            class="h-9 px-4 rounded-lg text-[13px] font-medium text-ink-2 hover:text-ink hover:bg-line-soft transition-colors">
                        Cancel
                    </button>
                    <x-button variant="gold" tag="button" type="submit" icon="check" x-bind:disabled="busy">
                        <span x-show="! busy">Save changes</span>
                        <span x-show="busy" x-cloak>Saving…</span>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
