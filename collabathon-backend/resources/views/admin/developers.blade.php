<x-layouts.admin active="developers" title="Developers" section="Manage">

    <x-page-header
        title="Developers"
        subtitle="Developer accounts are created here — they never self-register. A login is issued alongside the company record.">
        <x-slot:actions>
            <x-modal title="Create developer account"
                     subtitle="A login account is created and a temporary password generated on save."
                     width="max-w-2xl"
                     :open="$errors->any()">
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
                              .finally(() => $el.submit());
                      ">
                    @csrf

                    {{-- Company ------------------------------------------------------- --}}
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Company name" name="company_name" placeholder="e.g. Skyline Realty Group" required />
                            <x-field label="Contact person" name="contact_person" placeholder="Full name" required />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="Mobile number" name="mobile" placeholder="+971 5X XXX XXXX" icon="phone" required />
                            <x-field label="Email" name="email" type="email" placeholder="name@company.ae" icon="mail" required
                                     hint="This becomes their login." />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="City" name="city" placeholder="e.g. Dubai" icon="map-pin" required />
                            <x-field label="State / Emirate" name="state" placeholder="e.g. Dubai" />
                        </div>

                        {{-- Pre-filled so it can be copied/shared before saving; the admin
                             may also type their own, or clear it to have one generated. --}}
                        <x-password-field hint="Shown once more after saving, with share options." />
                    </div>

                    {{-- Credentials & branding ---------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="RERA / licence number" name="rera_number" placeholder="e.g. RERA-DXB-24817"
                                     hint="Shown to brokers as a trust signal." />
                            <x-file-field label="Company logo" name="logo" accept="image/*"
                                          hint="PNG or JPG, up to 2 MB." />
                        </div>

                        <x-field label="About the company" name="about" type="textarea" rows="3"
                                 placeholder="Track record, flagship projects, years in market…" />
                    </div>

                    {{-- Commercial ---------------------------------------------------- --}}
                    <div class="border-t border-line-soft space-y-3 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-field label="CP payout %" name="cp_payout_percent" type="number" step="0.01"
                                     placeholder="2.50" hint="Commission paid to the channel partner." required />
                            <x-select-field label="Status" name="status" :options="['active' => 'Active', 'paused' => 'Paused']" />
                        </div>

                        <x-switch-field label="Verified developer" name="verified"
                                        hint="Adds a verified badge on every listing this developer owns." />
                    </div>

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full"
                                  x-bind:disabled="busy">
                            <span x-show="! busy">Create &amp; generate credentials</span>
                            <span x-show="busy" x-cloak>Optimising logo…</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>


    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="building" label="Total developers" :value="$totals['all']" />
        <x-stat-card icon="check" label="Active" :value="$totals['active']" />
        <x-stat-card icon="list" label="Listings published" :value="$totals['listings']" />
        <x-stat-card icon="sparkles" label="Avg. CP payout" :value="$totals['avg_payout'] . '%'" />
    </div>

    <x-data-table
        :paginator="$developers"
        label="developers"
        search-placeholder="Search by company, contact or email…"
        empty-title="No developers match"
        empty-description="Adjust the search or filters to see more accounts.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
            <x-filter-select name="status" :options="['active' => 'Active', 'paused' => 'Paused']" placeholder="Any status" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Company</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="xl">City</x-th>
            <x-th align="right" sort="payout" hide="md">CP payout</x-th>
            <x-th align="right" sort="listings" hide="lg">Listings</x-th>
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
                        @if($dev->logo_path)
                            <img src="{{ Storage::disk('public')->url($dev->logo_path) }}"
                                 alt="" class="w-8 h-8 rounded-lg object-cover border border-line-soft shrink-0">
                        @else
                            <x-avatar :name="$dev->company_name" size="md" />
                        @endif
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 text-[13px] font-medium text-ink">
                                <a href="{{ route('admin.developers.show', $dev) }}"
                                   class="truncate hover:underline">{{ $dev->company_name }}</a>
                                @if($dev->verified)
                                    <x-icon name="shield" class="w-3.5 h-3.5 text-primary shrink-0"
                                            title="Verified developer" />
                                @endif
                            </p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $dev->rera_number ?: $dev->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $dev->contact_person }}</p>
                    <p class="text-[11.5px] text-ink-3 nums">{{ $dev->mobile }}</p>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 text-ink-3" />
                        {{ $dev->city }}
                    </span>
                </td>

                <td class="px-4 py-3 text-right text-[12.5px] text-ink nums font-medium hidden md:table-cell">
                    {{ rtrim(rtrim(number_format((float) $dev->cp_payout_percent, 2), '0'), '.') }}%
                </td>
                <td class="px-4 py-3 text-right text-[12.5px] text-ink-2 nums hidden lg:table-cell">{{ $dev->properties_count }}</td>
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
