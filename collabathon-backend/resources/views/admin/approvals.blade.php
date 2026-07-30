@php
    $tabs = [
        ['key' => 'pending', 'label' => 'Pending', 'count' => $pending->total()],
        ['key' => 'decided', 'label' => 'Decided', 'count' => $decided->total()],
    ];
@endphp

<x-layouts.admin active="approvals" title="Broker Approvals" section="Manage">

    <x-page-header
        title="Broker Approvals"
        subtitle="Brokers cannot sign in to the mobile app until an admin approves their registration. Approving issues their access immediately." />


    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="clock" label="Awaiting review" :value="$stats['pending']" />
        <x-stat-card icon="check" label="Approved (30d)" :value="$stats['approved']" />
        <x-stat-card icon="x" label="Rejected (30d)" :value="$stats['rejected']" />
        <x-stat-card icon="sparkles" label="Decision backlog"
                     :value="$stats['pending'] === 0 ? 'Clear' : $stats['pending'] . ' open'" />
    </div>

    {{-- Tab starts on whichever list the query string is paging, so a deep link lands right. --}}
    <div x-data="{ tab: '{{ request()->has('decided_page') || request('outcome') ? 'decided' : 'pending' }}' }">
        <x-tab-bar :tabs="$tabs" model="tab" class="mb-4" />

        {{-- ---------------------------- Pending ---------------------------- --}}
        <div x-show="tab === 'pending'">
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
                                <x-avatar :name="$broker->name" size="md" />
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
        </div>

        {{-- ---------------------------- Decided ---------------------------- --}}
        <div x-show="tab === 'decided'" x-cloak>
            <x-data-table
                :paginator="$decided"
                label="decisions"
                :searchable="false"
                empty-title="No decisions recorded"
                empty-description="Approved and rejected registrations appear here.">

                <x-slot:filters>
                    <x-filter-select name="outcome"
                                     :options="['approved' => 'Approved', 'rejected' => 'Rejected']"
                                     placeholder="Any outcome" />
                </x-slot:filters>

                <x-slot:head>
                    <x-th>Broker</x-th>
                    <x-th hide="md">Company</x-th>
                    <x-th hide="lg">Decided</x-th>
                    <x-th hide="xl">Reviewer</x-th>
                    <x-th hide="xl">Reason</x-th>
                    <x-th align="right">Outcome</x-th>
                </x-slot:head>

                @foreach($decided as $decision)
                    {{-- Decided rows open the same page: an approved broker's paperwork
                         still needs to be auditable after the fact. --}}
                    <tr @class(['hover:bg-canvas transition-colors', 'cursor-pointer' => $decision->broker])
                        @if($decision->broker)
                            x-on:click="window.location = @js(route('admin.approvals.show', $decision->broker))"
                        @endif>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-avatar :name="$decision->broker?->name ?? '—'" size="sm" />
                                @if($decision->broker)
                                    <a href="{{ route('admin.approvals.show', $decision->broker) }}"
                                       class="text-[13px] font-medium text-ink hover:underline truncate">{{ $decision->broker->name }}</a>
                                @else
                                    <p class="text-[13px] font-medium text-ink truncate">—</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-ink-2 hidden md:table-cell">
                            {{ $decision->broker?->brokerProfile?->company_name ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-ink-3 nums hidden lg:table-cell">
                            {{ $decision->created_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-ink-2 hidden xl:table-cell">{{ $decision->decider?->name ?: 'System' }}</td>
                        <td class="px-4 py-3 text-[12.5px] text-ink-3 max-w-[28ch] truncate hidden xl:table-cell">
                            {{ $decision->reason ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-badge :tone="$decision->decision === 'approved' ? 'success' : 'danger'" size="sm" dot>
                                {{ ucfirst($decision->decision) }}
                            </x-badge>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-layouts.admin>
