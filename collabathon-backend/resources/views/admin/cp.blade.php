<x-layouts.admin active="cp" title="Channel Partners" section="Manage">

    <x-page-header
        title="Channel Partners"
        subtitle="Channel partners who are through approval and able to sign in. New registrations are reviewed on the approvals queue before they appear here.">
        <x-slot:actions>
            <x-button variant="subtle" tag="a" icon="clock" href="{{ route('admin.approvals') }}">
                Approval queue
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="users" label="Active partners" :value="$stats['total']" />
        <x-stat-card icon="building" label="Registered as company" :value="$stats['companies']" />
        <x-stat-card icon="sparkles" label="Joined (30d)" :value="$stats['joined_30d']" />
        <x-stat-card icon="list" label="Cities covered" :value="$stats['cities']" />
    </div>

    <x-data-table
        :paginator="$partners"
        label="partners"
        search-placeholder="Search by name, company, email, mobile or RERA…"
        empty-icon="users"
        empty-title="No channel partners yet"
        empty-description="Approved channel partners appear here. Try clearing the filters, or review the approval queue.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
            <x-filter-select name="state" :options="$states" placeholder="All states" />
            <x-filter-select name="segment" :options="$segments" placeholder="All categories" />
            <x-filter-select name="type"
                             :options="['company' => 'Company', 'individual' => 'Individual']"
                             placeholder="Any type" />
        </x-slot:filters>

        <x-slot:head>
            <x-th sort="name">Partner</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">RERA</x-th>
            <x-th hide="xl">Categories</x-th>
            <x-th sort="city" hide="lg">Location</x-th>
            <x-th sort="created_at" hide="xl">Joined</x-th>
            <x-th align="right">Projects</x-th>
        </x-slot:head>

        @foreach($partners as $partner)
            @php $profile = $partner->brokerProfile; @endphp
            {{-- Opens the same review page the approvals queue uses — an active partner's
                 paperwork is the same record, just at a later stage. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="window.location = @js(route('admin.approvals.show', $partner))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$partner->name" :src="$profile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', $partner) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $partner->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">
                                {{ $profile?->company_name ?: 'Individual' }}
                            </p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $partner->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $partner->email }}</p>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $profile?->rera_number ?: '—' }}</span>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @foreach(($profile?->segments ?? []) as $segment)
                            <x-badge tone="neutral" size="sm">{{ $segment }}</x-badge>
                        @endforeach
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 truncate">{{ $profile?->city ?: '—' }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->state ?: '' }}</p>
                </td>

                <td class="px-4 py-3 hidden xl:table-cell">
                    <span class="text-[12.5px] text-ink-3 nums">{{ $partner->created_at->format('d M Y') }}</span>
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="text-[12.5px] text-ink-2 nums">{{ $partner->accepted_leads_count }}</span>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
