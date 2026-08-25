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
@endphp

<x-layouts.admin active="developers" :title="$developer->company_name" section="Manage">

    <a href="{{ route('admin.developers') }}"
       class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2 hover:text-ink transition-colors mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" />
        Back to developers
    </a>

    {{-- ============================== Header ============================== --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-start gap-3.5 flex-1 min-w-[260px]">
            <x-avatar :name="$developer->company_name" :src="$developer->logo_path" shape="square" size="lg" class="shrink-0" />

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
            @php
                /**
                 * Paired two-up via x-detail-grid rather than a row per field: every value
                 * here is short, and a full-width row for "Pincode" left most of the line
                 * blank on a panel this wide.
                 *
                 * Ten short fields, so they chunk into five clean pairs. The address is the
                 * one genuinely long value and takes a row of its own; the coordinates ride
                 * underneath it as a muted second line rather than as a twelfth field,
                 * which would have left a half-empty row and separated them from the place
                 * they describe.
                 */
                $coords = $developer->latitude && $developer->longitude
                    ? number_format((float) $developer->latitude, 5) . ', ' . number_format((float) $developer->longitude, 5)
                    : null;

                $addressCell = new \Illuminate\Support\HtmlString(
                    filled($developer->address)
                        ? e($developer->address) . ($coords
                            ? '<span class="block text-[11.5px] text-ink-3 nums mt-1">' . e($coords) . '</span>'
                            : '')
                        : '—'
                );

                // One filled-in platform is the common case, not five — a row per
                // platform would be four "—"s more often than not. This renders only
                // the ones actually set, each as its own labelled link.
                $socialLinks = \App\Support\SocialPlatforms::linksFor($developer);
                $socialCell = new \Illuminate\Support\HtmlString(
                    count($socialLinks)
                        ? collect($socialLinks)->map(fn ($link) => sprintf(
                            '<a href="%s" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-primary-dark hover:underline"><span class="inline-flex shrink-0">%s</span><span>%s</span> <svg class="w-3 h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 13L13 7M13 7H8M13 7V12" stroke-linecap="round" stroke-linejoin="round"/></svg></a>',
                            e(\Illuminate\Support\Str::startsWith($link['value'], ['http://', 'https://']) ? $link['value'] : 'https://' . ltrim($link['value'], '@')),
                            // $link['key'] is one of SocialPlatforms::ALL's keys, which is
                            // also exactly the matching icon's name in icon.blade.php.
                            view('components.icon', ['name' => $link['key'], 'class' => 'w-3.5 h-3.5 shrink-0'])->render(),
                            e($link['label'])
                        ))->join('<span class="text-ink-3 mx-0.5">·</span>')
                        : '—'
                );
            @endphp

            <x-panel title="Company" flush>
                {{-- Eight short fields = four exact pairs, then Pincode and Address each take
                     a full-width row of their own — an odd field out left half a row empty
                     when just appended to the paired set instead. Designation rides with the
                     contact name rather than taking a field of its own, for the same reason. --}}
                <x-detail-grid :fields="[
                    ['label' => 'Company name', 'value' => $developer->company_name],
                    ['label' => 'Website', 'value' => $developer->website],
                    ['label' => 'Social media', 'value' => $socialCell],
                    ['label' => 'Contact person', 'value' => collect([$developer->contact_person, $developer->contact_designation])->filter()->join(' · ')],
                    ['label' => 'Mobile', 'value' => $developer->mobile],
                    ['label' => 'Country', 'value' => $developer->country],
                    ['label' => 'State / Emirate', 'value' => $developer->state],
                    ['label' => 'City', 'value' => $developer->city],
                    ['label' => 'Pincode', 'value' => $developer->pincode, 'wide' => true],
                    ['label' => 'Address', 'value' => $addressCell, 'wide' => true],
                ]" />
            </x-panel>

            {{-- Key contact. Separate panel, badged, so it reads as internal at a glance —
                 DeveloperResource omits these columns, so none of it reaches the app. --}}
            <x-panel flush>
                <x-slot:title>Key contact</x-slot:title>
                <x-slot:actions>
                    <x-badge tone="neutral" size="sm">Admin only · not shown to CPs</x-badge>
                </x-slot:actions>

                {{-- Four short fields = two exact pairs. Designation stays its own field
                     here, unlike the public contact, because it is the thing that tells an
                     admin whether this is the person who can actually decide anything. --}}
                <x-detail-grid :fields="[
                    ['label' => 'Name', 'value' => $developer->key_contact_person],
                    ['label' => 'Designation', 'value' => $developer->key_contact_designation],
                    ['label' => 'Mobile', 'value' => $developer->key_contact_mobile],
                    ['label' => 'Email', 'value' => $developer->key_contact_email],
                ]" />
            </x-panel>

            <x-panel title="About the company" padded>
                <p class="text-[13px] text-ink-2 leading-relaxed whitespace-pre-line">
                    {{ $developer->about ?: 'No description added yet.' }}
                </p>
            </x-panel>

            {{-- ---------------------------- Projects ----------------------------
                 The whole point of a developer record is what they are selling, so the
                 list belongs on the profile rather than one filter-click away on the
                 projects screen. --}}
            @php
                $overCap = $properties->count() > $projectCap;
                $shown = $properties->take($projectCap);

                $listingTone = ['active' => 'success', 'draft' => 'neutral', 'archived' => 'danger'];
            @endphp

            <x-panel flush>
                <x-slot:title>Listings</x-slot:title>
                <x-slot:actions>
                    @if($stats['listings'] > 0)
                        <a href="{{ route('admin.properties', ['developer_id' => $developer->id]) }}"
                           class="text-[12px] text-primary-dark hover:underline">
                            View all {{ $stats['listings'] }}
                        </a>
                    @endif
                </x-slot:actions>

                @forelse($shown as $project)
                    <a href="{{ route('admin.properties.show', $project) }}"
                       @class([
                           'flex items-center gap-3 px-5 py-3 hover:bg-canvas transition-colors',
                           'border-b border-line-soft' => ! $loop->last,
                       ])>
                        @if($project->cover_image_path)
                            <img src="{{ \App\Support\FileStorage::url($project->cover_image_path) }}" alt=""
                                 class="w-11 h-11 rounded-lg object-cover border border-line-soft shrink-0">
                        @else
                            <div class="w-11 h-11 rounded-lg bg-canvas border border-line-soft flex items-center justify-center shrink-0">
                                <x-icon name="building" class="w-4 h-4 text-ink-3" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-medium text-ink truncate">{{ $project->name }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">
                                {{ collect([$project->project_type, $project->project_status, $project->city])
                                    ->filter()->join(' · ') }}
                            </p>
                        </div>

                        {{-- Price and engagement are the two things that say whether a
                             listing is working; both hide before the name does. --}}
                        <div class="text-right shrink-0 hidden sm:block">
                            {{-- Millions, matching the projects list. A second unit for the
                                 same figure across two screens is a reading error waiting
                                 to happen. --}}
                            <p class="text-[12.5px] text-ink nums">
                                @if($project->price_min)
                                    {{ $project->currency }} {{ number_format($project->price_min / 1_000_000, 2) }}M
                                @else
                                    —
                                @endif
                            </p>
                            <p class="text-[11px] text-ink-3 nums">
                                {{ $project->views_count }} views · {{ $project->interests_count }} interested
                            </p>
                        </div>

                        <x-badge :tone="$listingTone[$project->listing_status] ?? 'neutral'" size="sm" dot>
                            {{ ucfirst($project->listing_status) }}
                        </x-badge>
                    </a>
                @empty
                    <x-empty-state icon="building"
                                   title="No projects yet"
                                   description="Listings created for this developer will appear here." />
                @endforelse

                @if($overCap)
                    <x-slot:footer>
                        <a href="{{ route('admin.properties', ['developer_id' => $developer->id]) }}"
                           class="text-[12px] text-primary-dark hover:underline">
                            {{ $stats['listings'] - $projectCap }} more —
                            see every project by {{ $developer->company_name }}
                        </a>
                    </x-slot:footer>
                @endif
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
                          .finally(() => {
                              window.dispatchEvent(new CustomEvent('navigate-start'));
                              $el.submit();
                          });
                  ">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="developer-edit">

                <div class="space-y-3">
                    <x-field label="Company name" name="company_name" :value="$developer->company_name" required />

                    <x-field label="Company website" name="website" :value="$developer->website"
                             placeholder="https://example.ae" />

                    <div>
                        <p class="text-[12.5px] font-medium text-ink mb-1.5">Social media</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach(\App\Support\SocialPlatforms::ALL as $key => $label)
                                <x-field :label="$label" :name="$key" :value="$developer->{$key}"
                                         placeholder="@handle or profile URL" />
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Contact person" name="contact_person" :value="$developer->contact_person" required />
                        <x-field label="Designation" name="contact_designation" :value="$developer->contact_designation"
                                 placeholder="e.g. Sales Head" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Mobile number" name="mobile" :value="$developer->mobile" icon="phone" required />
                        <x-field label="Email" name="email" type="email" icon="mail" required
                                 :value="$developer->user?->email ?? $developer->email"
                                 hint="This is their login." />
                    </div>
                </div>

                {{-- Key contact ------------------------------------------------- --}}
                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="flex items-center gap-2">
                        <h4 class="text-[13px] font-semibold text-ink">Key contact</h4>
                        <x-badge tone="neutral" size="sm">Admin only</x-badge>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Key contact person" name="key_contact_person"
                                 :value="$developer->key_contact_person" placeholder="Full name" />
                        <x-field label="Designation" name="key_contact_designation"
                                 :value="$developer->key_contact_designation" placeholder="e.g. Managing Director" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Key contact mobile" name="key_contact_mobile"
                                 :value="$developer->key_contact_mobile" icon="phone" />
                        <x-field label="Key contact email" name="key_contact_email" type="email"
                                 :value="$developer->key_contact_email" icon="mail" />
                    </div>
                </div>

                {{-- Location ---------------------------------------------------- --}}
                <div class="border-t border-line-soft space-y-3 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Same picker as the create form. Values typed before Locations
                             existed are kept as options so saving cannot blank them. --}}
                        <x-location-picker :country="$developer->country" :state="$developer->state"
                                           :city="$developer->city" required />
                    </div>

                    {{-- Same single address field as the create form; pincode and
                         coordinates ride along hidden, filled by the map lookup. --}}
                    <x-address-finder :address="$developer->address" :pincode="$developer->pincode"
                                      :latitude="$developer->latitude" :longitude="$developer->longitude" />
                </div>

                <div class="border-t border-line-soft space-y-3 pt-4">
                    <x-logo-field label="Replace logo" name="logo"
                                  hint="Leave empty to keep the current logo. Crop to fit — most logos are wide wordmarks, not square." />

                    <x-field label="About the company" name="about" type="textarea" rows="3" :value="$developer->about" />
                </div>

                <div class="border-t border-line-soft space-y-3 pt-4">
                    <x-select-field label="Status" name="status" :selected="$developer->status"
                                    :options="['active' => 'Active', 'paused' => 'Paused']" />

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
