@php
    $requests = (int) $stats->requests;
    $accepted = (int) $stats->accepted;
    $pending = (int) $stats->pending;
    $declined = (int) $stats->declined;
    $acceptRate = $requests > 0 ? $accepted / $requests * 100 : 0;
@endphp

<x-layouts.admin active="leads" title="Approvals" section="Manage">

    <x-page-header
        title="Approvals"
        :subtitle="$selectedProperty
            ? 'Scoped to “' . $selectedProperty->name . '” — clear the listing filter to see every developer.'
            : 'Every developer, ranked by how many channel partners have requested their listings.'">
        <x-slot:actions>
            <x-export-menu :export="$export" />
        </x-slot:actions>
    </x-page-header>

    {{-- The privacy rule the whole feature hangs on --}}
    <div class="flex items-start gap-3 rounded-xl bg-primary-soft ring-1 ring-inset ring-primary-ring px-4 py-3 mb-5">
        <x-icon name="lock" class="w-4 h-4 text-primary-dark shrink-0 mt-0.5" />
        <p class="text-[12.5px] text-ink-2 leading-relaxed">
            <span class="font-medium text-ink">Contact details stay locked until a channel partner marks a project “Interested.”</span>
            A casual view never exposes the channel partner's phone number or email to the developer.
        </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-5">
        <x-stat-card icon="sparkles" label="Requests received" :value="$requests" />
        <x-stat-card icon="check" label="Accepted" :value="$accepted" />
        <x-stat-card icon="clock" label="Pending" :value="$pending" />
        <x-stat-card icon="x" label="Declined" :value="$declined" />
    </div>

    {{-- ============================== Analytics ============================== --}}
    {{-- Collapsed by default — this is a detour from the developer roster below, not
         the reason the page exists, so it asks before it takes the space. --}}
    <div x-data="{ open: false }" class="mb-5">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between gap-3 rounded-xl border border-line bg-panel px-4 py-3
                       shadow-card hover:border-primary-ring transition-colors">
            <span class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-lg bg-primary-soft flex items-center justify-center shrink-0">
                    <x-icon name="chart" class="w-4 h-4 text-primary-dark" />
                </span>
                <span class="text-left min-w-0">
                    <span class="block text-[13px] font-medium text-ink">Analytics</span>
                    <span class="block text-[11.5px] text-ink-3 truncate">Trend, acceptance rate and top developers</span>
                </span>
            </span>
            <x-icon name="chevron-down" class="w-4 h-4 text-ink-3 shrink-0 transition-transform duration-200"
                    x-bind:class="{'rotate-180': open}" />
        </button>

        <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="mt-4 space-y-4">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <x-panel title="Requests over time" subtitle="Last 12 weeks — requested, accepted, declined"
                         padded class="xl:col-span-2">
                    <x-chart-trend
                        :points="$trend"
                        :series="[
                            ['key' => 'requests', 'label' => 'Requested', 'color' => 'var(--color-chart-1)'],
                            ['key' => 'accepted', 'label' => 'Accepted', 'color' => 'var(--color-success)'],
                            ['key' => 'declined', 'label' => 'Declined', 'color' => 'var(--color-danger)'],
                        ]" />
                </x-panel>

                <x-panel title="Acceptance rate" subtitle="Requested → accepted, platform-wide" padded>
                    <div class="pt-1">
                        <p class="text-[32px] font-semibold text-ink tracking-[-0.02em] leading-none">
                            {{ number_format($acceptRate, 1) }}%
                        </p>
                        <p class="text-[11.5px] text-ink-3 mt-1.5">
                            {{ number_format($accepted) }} of {{ number_format($requests) }} requests accepted
                        </p>
                    </div>

                    {{-- Proportional split — accepted / pending / declined always sum to every
                         request, so a stacked bar reads this honestly; a funnel would imply a
                         progression these three don't have (they're mutually exclusive outcomes,
                         not sequential stages). --}}
                    <div class="mt-5 pt-4 border-t border-line-soft space-y-2.5">
                        @php
                            $split = [
                                ['label' => 'Accepted', 'value' => $accepted, 'class' => 'bg-success'],
                                ['label' => 'Pending', 'value' => $pending, 'class' => 'bg-warning'],
                                ['label' => 'Declined', 'value' => $declined, 'class' => 'bg-danger'],
                            ];
                        @endphp
                        <div class="flex h-2.5 gap-x-0.5 bg-canvas">
                            @foreach($split as $s)
                                @if($requests > 0 && $s['value'] > 0)
                                    <div class="{{ $s['class'] }}" style="width: {{ $s['value'] / $requests * 100 }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        @foreach($split as $s)
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="flex items-center gap-1.5 text-ink-2">
                                    <span class="w-2 h-2 rounded-full {{ $s['class'] }}"></span>
                                    {{ $s['label'] }}
                                </span>
                                <span class="text-ink-3 nums">{{ number_format($s['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            </div>

            @if(count($topDevelopers) > 0)
                <x-panel title="Top developers" subtitle="By request volume" padded>
                    <x-chart-bars :rows="$topDevelopers" />
                </x-panel>
            @endif
        </div>
    </div>

    {{-- ============================== Developers ============================== --}}
    <x-data-table
        :paginator="$developers"
        label="developers"
        search-placeholder="Search by company name…"
        empty-icon="users"
        empty-title="No developers match"
        empty-description="Adjust the search or clear the listing filter.">

        <x-slot:filters>
            <x-filter-select name="property_id" :options="$properties" placeholder="All listings" />
        </x-slot:filters>

        <x-slot:head>
            <x-th>Developer</x-th>
            <x-th hide="md">Location</x-th>
            <x-th align="right">Requests</x-th>
            <x-th align="right" hide="lg">Accepted</x-th>
            <x-th align="right" hide="lg">Pending</x-th>
            <x-th align="right" hide="xl">Declined</x-th>
            <x-th align="right"><span class="sr-only">Open</span></x-th>
        </x-slot:head>

        @foreach($developers as $dev)
            {{-- Opens this developer's project breakdown — the next tier down, not the
                 developer's own profile page (that's the Developers module's job). --}}
            <tr class="hover:bg-canvas transition-colors cursor-pointer"
                x-on:click="window.location = @js(route('admin.leads.developer', $dev))">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :name="$dev->company_name" :src="$dev->logo_path" shape="square" size="md" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.leads.developer', $dev) }}"
                               class="text-[13px] font-medium text-ink hover:underline truncate block">
                                {{ $dev->company_name }}
                            </a>
                            <p class="text-[11.5px] text-ink-3 truncate">{{ $dev->email }}</p>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3 hidden md:table-cell">
                    <span class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-2">
                        <x-icon name="map-pin" class="w-3.5 h-3.5 text-ink-3 shrink-0" />
                        {{ $dev->city ?: '—' }}
                    </span>
                </td>

                <td class="px-4 py-3 text-right">
                    <span class="text-[13px] font-medium text-ink nums">{{ number_format((int) $dev->requests_count) }}</span>
                </td>
                <td class="px-4 py-3 text-right hidden lg:table-cell">
                    <span class="text-[12.5px] text-success nums">{{ number_format((int) $dev->accepted_count) }}</span>
                </td>
                <td class="px-4 py-3 text-right hidden lg:table-cell">
                    <span class="text-[12.5px] text-warning nums">{{ number_format((int) $dev->pending_count) }}</span>
                </td>
                <td class="px-4 py-3 text-right hidden xl:table-cell">
                    <span class="text-[12.5px] text-danger nums">{{ number_format((int) $dev->declined_count) }}</span>
                </td>

                <td class="px-4 py-3 text-right">
                    <x-icon name="chevron-right" class="w-4 h-4 text-ink-3 inline-block" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
