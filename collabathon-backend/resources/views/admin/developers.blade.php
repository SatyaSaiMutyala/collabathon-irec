<x-layouts.admin active="developers" title="Developers" section="Manage">

    <x-page-header
        title="Developers"
        subtitle="Developer accounts are created here — they never self-register. A login is issued alongside the company record.">
        <x-slot:actions>
            <x-modal title="Create developer account"
                     subtitle="A login account is created and a temporary password generated on save."
                     width="max-w-2xl"
                     :open="$errors->any() && old('_form') !== 'bulk-import'">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add developer</x-button>
                </x-slot:trigger>

                {{-- Every field here is visible, so native validation runs first and this
                     handler only fires once the form is valid. All it does is re-encode the
                     logo — a phone photo would otherwise blow the 2 MB rule — which is async,
                     so the submit is cancelled and re-issued when compression settles.

                     The re-issue uses submit(), not requestSubmit(). requestSubmit() fires the
                     submit event again, which re-enters this handler and re-runs constraint
                     validation, and returns silently while the form's "firing submission
                     events" flag is set — a pile of ways for the save to quietly do nothing.
                     submit() fires no event and skips validation, which is exactly right here
                     because validation already passed to get us into this handler at all.

                     Compression is an optimisation, never a gate: the optional call keeps a
                     missing bundle from throwing before .finally is attached (that stranded
                     the form on "Optimising logo…"), and compressFileInputs self-limits, so a
                     decode that never settles cannot block the save either. --}}
                <form method="POST" action="{{ route('admin.developers.store') }}"
                      enctype="multipart/form-data" class="space-y-5"
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
                    @csrf

                    {{-- Company ------------------------------------------------------- --}}
                    <div class="space-y-3">
                        <x-field label="Company name" name="company_name" placeholder="e.g. Skyline Realty Group" required />

                        <x-field label="Company website" name="website" placeholder="https://example.ae" />

                        {{-- One field per platform rather than a single free-text "social
                             media" box — a developer can run more than one channel, and a
                             named field is what lets the show page render each as its own
                             labelled link instead of trying to parse free text. --}}
                        <div>
                            <p class="text-[12.5px] font-medium text-ink mb-1.5">Social media</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach(\App\Support\SocialPlatforms::ALL as $key => $label)
                                    <x-field :label="$label" :name="$key" placeholder="@handle or profile URL" />
                                @endforeach
                            </div>
                        </div>

                        {{-- Contact person: the developer's public point of contact. This
                             is the one channel partners see, so it sits with the login
                             details rather than with the internal key contact below. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Contact person" name="contact_person" placeholder="Full name" required />
                            <x-field label="Designation" name="contact_designation" placeholder="e.g. Sales Head" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Mobile number" name="mobile" placeholder="+971 5X XXX XXXX" icon="phone" required />
                            <x-field label="Email" name="email" type="email" placeholder="name@company.ae" icon="mail" required
                                     hint="This becomes their login." />
                        </div>

                        {{-- Pre-filled so it can be copied/shared before saving; the admin
                             may also type their own, or clear it to have one generated. --}}
                        <x-password-field hint="Shown once more after saving, with share options." />
                    </div>

                    {{-- Key contact ---------------------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <div class="flex items-center gap-2">
                            <h4 class="text-[13px] font-semibold text-ink">Key contact</h4>
                            <x-badge tone="neutral" size="sm">Admin only</x-badge>
                        </div>
                        <p class="text-[11.5px] text-ink-3 -mt-1">
                            The relationship owner inside the developer. Never sent to the mobile app —
                            channel partners only ever see the contact person above.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Key contact person" name="key_contact_person" placeholder="Full name" />
                            <x-field label="Designation" name="key_contact_designation" placeholder="e.g. Managing Director" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Key contact mobile" name="key_contact_mobile" placeholder="+971 5X XXX XXXX" icon="phone" />
                            <x-field label="Key contact email" name="key_contact_email" type="email"
                                     placeholder="name@company.ae" icon="mail" />
                        </div>
                    </div>

                    {{-- Location & geo-fence ------------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <h4 class="text-[13px] font-semibold text-ink">Location</h4>

                        {{-- Country -> state -> city, each narrowing the next. Defaults to
                             the trio most developers added here use; all remain changeable,
                             and a failed submit keeps whatever was chosen. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-location-picker country="India" state="Telangana" city="Hyderabad" required />
                        </div>

                        {{-- One address field. Pincode and coordinates are no longer typed:
                             they come from the map lookup inside this component, or stay
                             empty when the address is entered by hand. --}}
                        <x-address-finder />
                    </div>

                    {{-- Credentials & branding ---------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <x-logo-field label="Company logo" name="logo"
                                      hint="Crop to fit — most logos are wide wordmarks, not square." />

                        <x-field label="About the company" name="about" type="textarea" rows="3"
                                 placeholder="Track record, flagship projects, years in market…" />
                    </div>

                    {{-- No Commercial section at creation. CP payout, status and the
                         verified badge are all set from the developer's own page once the
                         account exists; store() applies the defaults. See the note there
                         on why payout is not simply left at the column default of 0. --}}

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full"
                                  x-bind:disabled="busy">
                            <span x-show="! busy">Create &amp; generate credentials</span>
                            <span x-show="busy" x-cloak>Optimising logo…</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>

            <x-modal title="Bulk upload developers"
                     subtitle="One row per developer. Logos and other attachments aren't part of the sheet — add those on each developer's own page afterward."
                     width="max-w-lg"
                     :open="$errors->any() && old('_form') === 'bulk-import'">
                <x-slot:trigger>
                    <x-button variant="outline" icon="upload">Bulk upload</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.developers.bulk-import') }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="bulk-import">

                    {{-- Button rather than a link inside the sentence — see the note on the
                         matching block in cp.blade.php. --}}
                    <div class="rounded-lg bg-canvas border border-line px-3.5 py-3">
                        <p class="text-[12.5px] text-ink-2 leading-relaxed">
                            Start from the template so the column names match exactly.
                            <span class="text-ink-3">Company name, contact person, email, mobile and city are
                                required per row; everything else is optional.</span>
                        </p>

                        <x-button variant="outline" size="sm" tag="a" icon="download" class="mt-3" download
                                  href="{{ route('admin.developers.bulk-import.template') }}">
                            Download CSV template
                        </x-button>
                    </div>

                    <x-file-field label="CSV file" name="file" accept=".csv,text/csv" required
                                  hint="Exported from Excel/Sheets as CSV — .xlsx is not read directly." />

                    <x-button variant="gold" tag="button" type="submit" icon="upload" class="w-full">
                        Upload &amp; create
                    </x-button>
                </form>
            </x-modal>

            <x-export-menu :export="$export" />
        </x-slot:actions>
    </x-page-header>


    {{-- Coverage rather than commercials: on a directory of companies the useful summary
         is where they are, not how many listings they hold. --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5 mb-5">
        <x-stat-card icon="building" label="Total developers" :value="$totals['all']" />

        {{-- The one filterable tile: jumps the list below to status=active, keeping
             whatever search/country/city filter is already applied. The other four
             tiles are coverage counts with no matching row-level filter, so they stay
             plain reads rather than links to nowhere. --}}
        <a href="{{ request()->fullUrlWithQuery(['status' => 'active', 'page' => null]) }}" class="block h-full">
            <x-stat-card icon="check" label="Active" :value="$totals['active']"
                :class="(request('status') === 'active' ? 'border-primary-ring shadow-md ' : '')
                    . 'h-full hover:border-primary-ring hover:shadow-md
                       transition-[border-color,box-shadow] duration-200 ease-out cursor-pointer'" />
        </a>

        <x-stat-card icon="map-pin" label="Countries" :value="$totals['countries']" />
        <x-stat-card icon="map-pin" label="States" :value="$totals['states']" />
        <x-stat-card icon="map-pin" label="Cities" :value="$totals['cities']" />
    </div>

    <x-data-table
        :paginator="$developers"
        label="developers"
        search-placeholder="Search by company, contact or email…"
        empty-title="No developers match"
        empty-description="Adjust the search or filters to see more accounts.">

        <x-slot:filters>
            <x-filter-select name="country" :options="$countries" placeholder="All countries" />
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
            <x-filter-select name="status" :options="['active' => 'Active', 'paused' => 'Paused']" placeholder="Any status" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Company</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">City / State</x-th>
            <x-th hide="lg">Country</x-th>
            <x-th hide="xl">Pincode</x-th>
            <x-th sort="created_at" hide="xl">Created</x-th>
            <x-th>Status</x-th>
            <x-th align="right">Actions</x-th>
        </x-slot:head>

        @foreach($developers as $dev)
            {{-- The whole row opens the record. The click handler is on <tr> rather than a
                 wrapping <a> because a table row cannot contain one, and it ignores clicks
                 that land on the actions menu so those still work. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.developers.show', $dev))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$dev->company_name" :src="$dev->logo_path" shape="square" size="md" />
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 text-[13px] font-medium text-ink">
                                <a href="{{ route('admin.developers.show', $dev) }}"
                                   class="truncate hover:underline">{{ $dev->company_name }}</a>
                                @if($dev->verified)
                                    <x-icon name="shield" class="w-3.5 h-3.5 text-primary shrink-0"
                                            title="Verified developer" />
                                @endif
                            </p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $dev->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $dev->contact_person }}</p>
                    <p class="text-[11.5px] text-ink-3 nums">{{ $dev->mobile }}</p>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                        {{ $dev->city ?: '—' }}
                    </span>
                    {{-- State sits under the city rather than in its own column: the two are
                         read together, and a separate column would be the first thing to
                         get hidden at this breakpoint anyway. --}}
                    <p class="text-[11.5px] text-ink-3 truncate pl-5">{{ $dev->state ?: '—' }}</p>
                </td>

                <td class="px-4 py-3 text-[12.5px] text-ink-2 hidden lg:table-cell">{{ $dev->country ?: '—' }}</td>
                <td class="px-4 py-3 text-[12.5px] text-ink-2 nums hidden xl:table-cell">{{ $dev->pincode ?: '—' }}</td>
                <td class="px-4 py-3 text-[12.5px] text-ink-3 nums hidden xl:table-cell">{{ $dev->created_at->format('d M Y') }}</td>

                <td class="px-4 py-3">
                    <x-badge :tone="$dev->status === 'active' ? 'success' : 'neutral'" size="sm" dot>
                        {{ ucfirst($dev->status) }}
                    </x-badge>
                </td>

                {{-- data-row-actions keeps the row's click-through from firing in here. --}}
                <td class="px-4 py-3 text-right" data-row-actions>
                    <x-dropdown>
                        <x-slot:trigger>
                            <button type="button" aria-label="Actions for {{ $dev->company_name }}"
                                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                <x-icon name="dots" class="w-4 h-4" />
                            </button>
                        </x-slot:trigger>

                        <x-dropdown-item icon="eye" tag="a" href="{{ route('admin.developers.show', $dev) }}">
                            View details
                        </x-dropdown-item>

                        <x-dropdown-item icon="list" tag="a"
                                         href="{{ route('admin.properties', ['developer_id' => $dev->id]) }}">
                            View listings
                        </x-dropdown-item>

                        {{-- Payloads use Js::from + {{ }} rather than @js() — Blade does not
                             compile directives inside a component's attribute string. --}}
                        @php
                            $sharePayload = \Illuminate\Support\Js::from([
                                'name' => $dev->contact_person,
                                'email' => $dev->user?->email ?? $dev->email,
                                // Lets the share dialog offer "set a password" in one step.
                                'resetAction' => $dev->user ? route('admin.developers.password', $dev) : null,
                            ]);
                            $resetPayload = \Illuminate\Support\Js::from([
                                'name' => $dev->company_name,
                                'action' => route('admin.developers.password', $dev),
                            ]);
                            // properties and leads both cascade on developers.id, so the
                            // count goes in the prompt rather than being a nasty surprise.
                            $deletePayload = \Illuminate\Support\Js::from([
                                'title' => 'Delete this developer?',
                                'message' => "{$dev->company_name}, its login account and "
                                    . $dev->properties_count . ' listing' . ($dev->properties_count === 1 ? '' : 's')
                                    . ' will be permanently deleted. This cannot be undone.',
                                'confirmLabel' => 'Delete developer',
                                'tone' => 'danger',
                            ]);
                        @endphp

                        <x-dropdown-item icon="external" tag="button" type="button"
                                         x-on:click="$dispatch('share-credentials', {{ $sharePayload }}); close()">
                            Share sign-in details
                        </x-dropdown-item>

                        @if($dev->user)
                            <x-dropdown-item icon="lock" tag="button" type="button"
                                             x-on:click="$dispatch('reset-password', {{ $resetPayload }}); close()">
                                Reset password
                            </x-dropdown-item>
                        @endif

                        <div class="my-1 border-t border-line-soft"></div>

                        <form method="POST" action="{{ route('admin.developers.update', $dev) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $dev->status === 'active' ? 'paused' : 'active' }}">
                            <x-dropdown-item :icon="$dev->status === 'active' ? 'x' : 'check'"
                                             :tone="$dev->status === 'active' ? 'danger' : 'default'"
                                             tag="button" type="submit">
                                {{ $dev->status === 'active' ? 'Pause account' : 'Reactivate account' }}
                            </x-dropdown-item>
                        </form>

                        <form method="POST" action="{{ route('admin.developers.destroy', $dev) }}"
                              x-on:submit.prevent="$dispatch('confirm-request', { ...{{ $deletePayload }}, form: $el }); close()">
                            @csrf @method('DELETE')
                            <x-dropdown-item icon="x" tone="danger" tag="button" type="submit">
                                Delete developer
                            </x-dropdown-item>
                        </form>
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
