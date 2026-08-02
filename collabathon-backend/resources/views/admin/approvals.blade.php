<x-layouts.admin active="approvals" title="Broker Approvals" section="Manage">

    <x-page-header
        title="Broker Approvals"
        subtitle="Brokers cannot sign in to the mobile app until an admin approves their registration. Approving issues their access immediately.">
        <x-slot:actions>
            {{-- The decision history lives on its own page: this one is work outstanding,
                 that one is a record that only grows. --}}
            <x-button variant="subtle" tag="a" icon="check" href="{{ route('admin.approvals.decided') }}">
                Decided
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="clock" label="Awaiting review" :value="$stats['pending']" />
        <x-stat-card icon="check" label="Approved (30d)" :value="$stats['approved']" />
        <x-stat-card icon="x" label="Rejected (30d)" :value="$stats['rejected']" />
        <x-stat-card icon="sparkles" label="Decision backlog"
                     :value="$stats['pending'] === 0 ? 'Clear' : $stats['pending'] . ' open'" />
    </div>

    <x-data-table
        :paginator="$pending"
        label="registrations"
        search-placeholder="Search by name, company, email or RERA…"
        empty-icon="check"
        empty-title="Queue is clear"
        empty-description="Every broker registration has been reviewed.">

        <x-slot:filters>
            <x-filter-select name="city" :options="$cities" placeholder="All cities" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Broker</x-th>
            <x-th hide="lg">Contact</x-th>
            <x-th hide="md">RERA</x-th>
            <x-th hide="xl">Segments</x-th>
            <x-th hide="lg">Submitted</x-th>
            <x-th align="right">Decision</x-th>
        </x-slot:head>

        @foreach($pending as $broker)
            @php $profile = $broker->brokerProfile; @endphp
            {{-- The row opens the full registration. Clicks on the decision cell are
                 ignored so Approve/Review still work — a <tr> cannot hold an <a>. --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="if (! $event.target.closest('[data-row-actions]')) window.location = @js(route('admin.approvals.show', $broker))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$broker->name" :src="$broker->brokerProfile?->photo_path" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.approvals.show', $broker) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">{{ $broker->name }}</a>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $profile?->company_name ?: $broker->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                    <p class="text-[12.5px] text-ink-2 nums">{{ $broker->mobile }}</p>
                    <p class="text-[11.5px] text-ink-3 truncate">{{ $broker->email }}</p>
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
                    <span class="text-[12.5px] text-ink-3 nums">{{ $broker->created_at->format('d M Y') }}</span>
                </td>

                {{-- data-row-actions stops the row's click-through firing in here. --}}
                <td class="px-4 py-3" data-row-actions>
                    <div class="flex items-center justify-end gap-1.5">
                        {{-- The full registration is ~34 fields plus documents — too
                             much for a drawer, so review happens on its own page. --}}
                        <x-button variant="subtle" size="sm" tag="a"
                                  href="{{ route('admin.approvals.show', $broker) }}">
                            Review
                        </x-button>

                        <form method="POST" action="{{ route('admin.approvals.approve', $broker) }}">
                            @csrf
                            <x-button variant="success-ghost" size="sm" icon="check" tag="button" type="submit"
                                      aria-label="Approve {{ $broker->name }}" />
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
