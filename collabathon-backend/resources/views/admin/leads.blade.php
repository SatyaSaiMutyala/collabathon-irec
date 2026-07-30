@php
    $toneMap = ['viewed' => 'neutral', 'interested' => 'warning', 'accepted' => 'success', 'declined' => 'danger'];
    $viewed = (int) $counts->total;
    $accepted = (int) $counts->accepted;
@endphp

<x-layouts.admin active="leads" title="Approvals" section="Manage">

    <x-page-header
        title="Approvals"
        subtitle="Every view and interest recorded across all developers and projects, platform-wide.">
        <x-slot:actions>
            <x-button variant="outline" icon="download">Export CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- The privacy rule the whole feature hangs on --}}
    <div class="flex items-start gap-3 rounded-xl bg-primary-soft ring-1 ring-inset ring-primary-ring px-4 py-3 mb-5">
        <x-icon name="lock" class="w-4 h-4 text-primary-dark shrink-0 mt-0.5" />
        <p class="text-[12.5px] text-ink-2 leading-relaxed">
            <span class="font-medium text-ink">Contact details stay locked until a broker marks a project “Interested.”</span>
            A casual view never exposes the broker's phone number or email to the developer.
        </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="eye" label="Total activity" :value="$viewed" />
        <x-stat-card icon="sparkles" label="Contact unlocked" :value="(int) $counts->interested" />
        <x-stat-card icon="check" label="Accepted" :value="$accepted" />
        <x-stat-card icon="x" label="Declined" :value="(int) $counts->declined" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">

        <x-data-table
            class="xl:col-span-3"
            :paginator="$leads"
            label="leads"
            search-placeholder="Search by broker or project…"
            empty-title="No activity matches"
            empty-description="Adjust the search or filters to see more leads.">

            <x-slot:filters>
                <x-filter-select name="status"
                                 :options="['viewed' => 'Viewed', 'interested' => 'Interested', 'accepted' => 'Accepted', 'declined' => 'Declined']"
                                 placeholder="Any status" />
                <x-filter-select name="developer_id" :options="$developers->pluck('company_name', 'id')" placeholder="All developers" />
            </x-slot:filters>

            <x-slot:head>
                <x-th>Broker</x-th>
                <x-th hide="md">Project</x-th>
                <x-th hide="xl">Developer</x-th>
                <x-th sort="created_at" hide="lg">Date</x-th>
                <x-th hide="lg">Contact</x-th>
                <x-th align="right" sort="status">Status</x-th>
                <x-th align="right"><span class="sr-only">Open</span></x-th>
            </x-slot:head>

            @foreach($leads as $lead)
                {{-- Whole row opens the lead. A <tr> cannot hold an <a>, so the click is
                     delegated; the broker name is still a real link for keyboard and
                     middle-click users. --}}
                <tr class="hover:bg-canvas transition-colors cursor-pointer"
                    x-on:click="window.location = @js(route('admin.leads.show', $lead))">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <x-avatar :name="$lead->broker?->name ?? '—'" size="sm" />
                            <a href="{{ route('admin.leads.show', $lead) }}"
                               class="text-[13px] font-medium text-ink hover:text-primary transition-colors truncate">
                                {{ $lead->broker?->name ?? 'Deleted broker' }}
                            </a>
                        </div>
                    </td>

                    <td class="px-4 py-3 text-[12.5px] text-ink-2 truncate hidden md:table-cell">{{ $lead->property?->name }}</td>
                    <td class="px-4 py-3 text-[12.5px] text-ink-3 truncate hidden xl:table-cell">{{ $lead->developer?->company_name }}</td>
                    <td class="px-4 py-3 text-[12.5px] text-ink-3 nums whitespace-nowrap hidden lg:table-cell">
                        {{ $lead->created_at->format('d M Y') }}
                    </td>

                    <td class="px-4 py-3 hidden lg:table-cell">
                        @if($lead->contact_unlocked)
                            <span class="inline-flex items-center gap-1.5 text-[12px] text-success">
                                <x-icon name="check" class="w-3.5 h-3.5" /> Unlocked
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[12px] text-ink-3">
                                <x-icon name="lock" class="w-3.5 h-3.5" /> Locked
                            </span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-right">
                        <x-badge :tone="$toneMap[$lead->status] ?? 'neutral'" size="sm" dot>
                            {{ ucfirst($lead->status) }}
                        </x-badge>
                    </td>

                    <td class="px-4 py-3 text-right">
                        <x-icon name="chevron-right" class="w-4 h-4 text-ink-3 inline-block" />
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-panel title="Conversion funnel" subtitle="All recorded activity" padded class="self-start">
            <div class="pt-1">
                <x-chart-funnel :stages="$funnel" />
            </div>
            <div class="mt-5 pt-4 border-t border-line-soft">
                <p class="text-[11.5px] text-ink-3">View → accepted</p>
                <p class="text-[19px] font-semibold text-ink mt-0.5 tracking-[-0.01em]">
                    {{ $viewed > 0 ? number_format($accepted / $viewed * 100, 1) : '0.0' }}%
                </p>
                <p class="text-[11.5px] text-ink-3 mt-2 leading-relaxed">
                    Contact details unlocked for {{ number_format((int) $counts->interested) }}
                    of {{ number_format($viewed) }} interactions.
                </p>
            </div>
        </x-panel>
    </div>
</x-layouts.admin>
