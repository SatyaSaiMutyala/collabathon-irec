<x-layouts.admin active="developers" title="Developers" section="Manage">

    <x-page-header
        title="Developers"
        subtitle="Developer accounts are created here — they never self-register. A login is issued alongside the company record.">
        <x-slot:actions>
            <x-modal title="Create developer account"
                     subtitle="A login account is created and a temporary password generated on save."
                     width="max-w-xl"
                     :open="$errors->any()">
                <x-slot:trigger>
                    <x-button variant="gold" icon="plus">Add developer</x-button>
                </x-slot:trigger>

                <form method="POST" action="{{ route('admin.developers.store') }}" class="space-y-4">
                    @csrf
                    <x-field label="Company name" name="company_name" placeholder="e.g. Skyline Realty Group" required />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Contact person" name="contact_person" placeholder="Full name" required />
                        <x-field label="City" name="city" placeholder="e.g. Dubai" required />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="Mobile number" name="mobile" placeholder="+971 5X XXX XXXX" icon="phone" required />
                        <x-field label="Email" name="email" type="email" placeholder="name@company.ae" icon="mail" required
                                 hint="This becomes their login." />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-field label="CP payout %" name="cp_payout_percent" type="number" step="0.01"
                                 placeholder="2.50" hint="Commission paid to the channel partner." required />
                        <x-select-field label="Status" name="status" :options="['active' => 'Active', 'paused' => 'Paused']" />
                    </div>

                    <div class="pt-1">
                        <x-button variant="gold" tag="button" type="submit" icon="check" class="w-full">
                            Create &amp; generate credentials
                        </x-button>
                    </div>
                </form>
            </x-modal>
        </x-slot:actions>
    </x-page-header>

    <x-flash />

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
            <x-th align="right"><span class="sr-only">Actions</span></x-th>
        </x-slot:head>

        @foreach($developers as $dev)
            <tr class="hover:bg-canvas/70 transition-colors">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$dev->company_name" size="md" />
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-ink truncate">{{ $dev->company_name }}</p>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $dev->email }}</p>
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

                <td class="px-4 py-3 text-right">
                    <x-dropdown>
                        <x-slot:trigger>
                            <button type="button" aria-label="Actions for {{ $dev->company_name }}"
                                    class="text-ink-3 hover:text-ink hover:bg-canvas rounded-md p-1.5 transition-colors">
                                <x-icon name="dots" class="w-4 h-4" />
                            </button>
                        </x-slot:trigger>

                        <x-dropdown-item icon="eye" tag="a"
                                         href="{{ route('admin.properties', ['developer_id' => $dev->id]) }}">
                            View listings
                        </x-dropdown-item>

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
                    </x-dropdown>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
