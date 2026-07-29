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

    <x-flash />

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
                    <tr class="hover:bg-canvas transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-avatar :name="$broker->name" size="md" />
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium text-ink truncate">{{ $broker->name }}</p>
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

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-drawer :title="$broker->name"
                                          :subtitle="($profile?->company_name ?: 'Independent') . ' · ' . ($profile?->city ?: '—')">
                                    <x-slot:trigger>
                                        <x-button variant="subtle" size="sm">Review</x-button>
                                    </x-slot:trigger>

                                    <div class="space-y-5">
                                        <div class="flex items-center gap-3">
                                            <x-avatar :name="$broker->name" size="lg" />
                                            <div class="min-w-0">
                                                <p class="text-[14px] font-medium text-ink">{{ $broker->name }}</p>
                                                <p class="text-[12.5px] text-ink-3">
                                                    Submitted {{ ($profile?->submitted_at ?? $broker->created_at)->format('d M Y') }}
                                                </p>
                                            </div>
                                            <x-badge tone="warning" size="sm" dot class="ml-auto">Pending</x-badge>
                                        </div>

                                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3.5 pt-1">
                                            @foreach([
                                                'Company' => $profile?->company_name ?: '—',
                                                'City' => $profile?->city ?: '—',
                                                'Mobile' => $broker->mobile ?: '—',
                                                'Email' => $broker->email,
                                                'RERA' => $profile?->rera_number ?: '—',
                                                'Experience' => $profile?->years_of_experience ? $profile->years_of_experience . ' years' : '—',
                                            ] as $label => $value)
                                                <div class="min-w-0">
                                                    <dt class="text-[11px] uppercase tracking-[0.05em] text-ink-3">{{ $label }}</dt>
                                                    <dd class="text-[13px] text-ink mt-0.5 truncate">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>

                                        @if($profile?->segments)
                                            <div>
                                                <p class="text-[11px] uppercase tracking-[0.05em] text-ink-3 mb-1.5">Segments</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($profile->segments as $segment)
                                                        <x-badge tone="primary" size="sm">{{ $segment }}</x-badge>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if($profile?->zones)
                                            <div>
                                                <p class="text-[11px] uppercase tracking-[0.05em] text-ink-3 mb-1.5">Operating zones</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($profile->zones as $zone)
                                                        <x-badge tone="neutral" size="sm">{{ $zone }}</x-badge>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="rounded-lg bg-warning-soft ring-1 ring-inset ring-warning-ring px-3.5 py-3">
                                            <p class="text-[12.5px] text-ink-2 leading-relaxed">
                                                <span class="font-medium text-ink">Verify the RERA number</span> against the
                                                regulator's registry before approving. Approval grants mobile-app access immediately.
                                            </p>
                                        </div>
                                    </div>

                                    <x-slot:footer>
                                        <div class="flex items-center gap-2.5">
                                            <form method="POST" action="{{ route('admin.approvals.approve', $broker) }}" class="flex-1">
                                                @csrf
                                                <x-button variant="primary" tag="button" type="submit" icon="check" class="w-full">
                                                    Approve broker
                                                </x-button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.approvals.reject', $broker) }}" class="flex-1"
                                                  x-data @submit="$refs.reason.value = $refs.reason.value || prompt('Reason for rejection?') || ''">
                                                @csrf
                                                <input type="hidden" name="reason" x-ref="reason" value="">
                                                <x-button variant="outline" tag="button" type="submit" icon="x" class="w-full">
                                                    Reject
                                                </x-button>
                                            </form>
                                        </div>
                                    </x-slot:footer>
                                </x-drawer>

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
                    <tr class="hover:bg-canvas transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <x-avatar :name="$decision->broker?->name ?? '—'" size="sm" />
                                <p class="text-[13px] font-medium text-ink truncate">{{ $decision->broker?->name }}</p>
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
